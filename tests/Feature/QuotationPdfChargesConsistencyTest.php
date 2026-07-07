<?php

namespace Tests\Feature;

use App\Http\Controllers\QuotationController;
use App\Models\Bank;
use App\Models\Role;
use App\Models\User;
use App\Services\ConfigService;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The charges table must be identical between the branded PDF (built at create
 * time from the form payload via QuotationService::buildTemplateDataFromInput)
 * and the plain/regenerated PDF (built from the saved row via
 * QuotationController::buildTemplateData). A key mismatch previously dropped
 * Stamp/Notary + Registration rows and showed PF as the percent, not the amount.
 */
class QuotationPdfChargesConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['slug' => 'loan_advisor'], ['name' => 'Loan Advisor']);
        app(ConfigService::class)->updateSection('gstPercent', 18);
    }

    private function makeUser(): User
    {
        $user = User::create([
            'name' => 'U'.uniqid(),
            'email' => uniqid().'@test',
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);
        $user->roles()->sync(Role::where('slug', 'loan_advisor')->pluck('id'));

        return $user->fresh('roles');
    }

    public function test_branded_and_plain_charges_match(): void
    {
        config(['app.skip_pdf_generation' => true]);
        Bank::create(['name' => 'TestBank', 'is_active' => true]);
        $user = $this->makeUser();

        $loanAmount = 3500000;
        $gst = 18;
        $pfPercent = 0.30;
        $pfBase = (int) round($loanAmount * $pfPercent / 100);
        $pf = $pfBase + (int) round($pfBase * $gst / 100);
        $adminBase = 5000;
        $admin = $adminBase + (int) round($adminBase * $gst / 100);
        $stamp = 1500;
        $reg = 6000;
        $advocate = 2500;
        $iom = 7000;
        $tc = 1000;
        $extra1 = 2000;
        $total = $pf + $admin + $stamp + $reg + $advocate + $iom + $tc + $extra1;

        $input = [
            'customerName' => 'Charges Test',
            'customerType' => 'salaried',
            'loanAmount' => $loanAmount,
            'selectedTenures' => [10],
            'banks' => [[
                'name' => 'TestBank',
                'roiMin' => 8.5,
                'roiMax' => 9.5,
                'charges' => [
                    'pf' => $pf, 'pfPercent' => $pfPercent,
                    'admin' => $admin, 'adminBase' => $adminBase,
                    'stamp_notary' => $stamp, 'registration_fee' => $reg,
                    'advocate' => $advocate, 'iom' => $iom, 'tc' => $tc,
                    'extra1Name' => 'Insurance', 'extra1Amt' => $extra1,
                    'total' => $total,
                ],
                'emiByTenure' => [10 => ['emi' => 18000, 'totalInterest' => 660000, 'totalPayment' => 2160000]],
            ]],
            'documents' => [['en' => 'PAN', 'gu' => '', 'excluded' => false]],
        ];

        $service = app(QuotationService::class);

        // Branded: charges as the create-time template builds them.
        $brandedMethod = (new \ReflectionClass($service))->getMethod('buildTemplateDataFromInput');
        $brandedMethod->setAccessible(true);
        $branded = $brandedMethod->invoke($service, $input)['banks'][0]['charges'];

        // Persist, then regenerate the plain template data from the saved row.
        $quotation = $service->generate($input, $user->id)['quotation'];

        $controller = app(QuotationController::class);
        $plainMethod = (new \ReflectionClass($controller))->getMethod('buildTemplateData');
        $plainMethod->setAccessible(true);
        $plain = $plainMethod->invoke($controller, $quotation->fresh(['banks.emiEntries', 'documents']), false)['banks'][0]['charges'];

        foreach (['pf', 'admin', 'stamp_notary', 'registration_fee', 'advocate', 'iom', 'tc', 'extra1Amt', 'extra2Amt', 'total'] as $key) {
            $this->assertSame((int) $branded[$key], (int) $plain[$key], "charge '{$key}' differs between branded and plain");
        }
        $this->assertSame('Insurance', $plain['extra1Name']);
    }
}
