<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Referral (name + type) capture on quotations.
 *
 * The referral name + config-driven referral type are persisted on create
 * and replaced on update. The type label resolves from `quotationReferralTypes`.
 */
class QuotationReferralTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['slug' => 'loan_advisor'], ['name' => 'Loan Advisor']);
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

    private function input(array $overrides = []): array
    {
        return array_merge([
            'customerName' => 'Test Customer',
            'customerType' => 'salaried',
            'loanAmount' => 1500000,
            'selectedTenures' => [10],
            'banks' => [
                [
                    'name' => 'TestBank',
                    'roiMin' => 8.5,
                    'roiMax' => 9.5,
                    'charges' => ['total' => 50000],
                    'emiByTenure' => [
                        10 => ['emi' => 18000, 'totalInterest' => 660000, 'totalPayment' => 2160000],
                    ],
                ],
            ],
            'documents' => [
                ['en' => 'Aadhar Card', 'gu' => 'આધાર કાર્ડ', 'excluded' => false],
            ],
        ], $overrides);
    }

    public function test_create_persists_referral_name_and_type(): void
    {
        config(['app.skip_pdf_generation' => true]);
        Bank::create(['name' => 'TestBank', 'is_active' => true]);
        $user = $this->makeUser();

        $result = app(QuotationService::class)->generate(
            $this->input(['referralName' => 'Ramesh Patel', 'referralType' => 'dsa']),
            $user->id,
        );

        $this->assertTrue($result['success'] ?? false);
        $quotation = $result['quotation'];

        $this->assertSame('Ramesh Patel', $quotation->referral_name);
        $this->assertSame('dsa', $quotation->referral_type);
        $this->assertSame('DSA/Connector', $quotation->referral_type_label);
    }

    public function test_blank_referral_is_stored_as_null(): void
    {
        config(['app.skip_pdf_generation' => true]);
        Bank::create(['name' => 'TestBank', 'is_active' => true]);
        $user = $this->makeUser();

        $result = app(QuotationService::class)->generate(
            $this->input(['referralName' => '', 'referralType' => '']),
            $user->id,
        );

        $quotation = $result['quotation'];

        $this->assertNull($quotation->referral_name);
        $this->assertNull($quotation->referral_type);
        $this->assertNull($quotation->referral_type_label);
    }

    public function test_update_replaces_referral(): void
    {
        config(['app.skip_pdf_generation' => true]);
        Bank::create(['name' => 'TestBank', 'is_active' => true]);
        $user = $this->makeUser();

        $created = app(QuotationService::class)->generate(
            $this->input(['referralName' => 'Old Name', 'referralType' => 'walk_in']),
            $user->id,
        )['quotation'];

        app(QuotationService::class)->update(
            $created,
            $this->input(['referralName' => 'New Name', 'referralType' => 'builder']),
        );

        $fresh = Quotation::find($created->id);
        $this->assertSame('New Name', $fresh->referral_name);
        $this->assertSame('builder', $fresh->referral_type);
        $this->assertSame('Builder/Developer', $fresh->referral_type_label);
    }
}
