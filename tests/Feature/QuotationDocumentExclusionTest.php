<?php

namespace Tests\Feature;

use App\Http\Controllers\QuotationController;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Quotation;
use App\Models\QuotationBank;
use App\Models\QuotationDocument;
use App\Models\Role;
use App\Models\User;
use App\Services\ConfigService;
use App\Services\NotificationService;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Document strike-out feature for quotations.
 *
 * Persists every doc row (excluded + included) to `quotation_documents`.
 * The PDF render path filters out `is_excluded=true`. Toggle endpoint
 * flips the flag and clears the cached PDF.
 */
class QuotationDocumentExclusionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function seedRoles(): void
    {
        foreach (['loan_advisor', 'super_admin', 'bank_employee'] as $slug) {
            Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => ucwords(str_replace('_', ' ', $slug))],
            );
        }
    }

    private function makeUser(string $slug = 'loan_advisor'): User
    {
        $user = User::create([
            'name' => 'U'.uniqid(),
            'email' => uniqid().'@test',
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);
        $user->roles()->sync(Role::where('slug', $slug)->pluck('id'));

        return $user->fresh('roles');
    }

    private function makeQuotation(?User $user = null): Quotation
    {
        $user ??= $this->makeUser();
        $branch = Branch::create(['name' => 'B-'.uniqid(), 'is_active' => true]);

        return Quotation::create([
            'user_id' => $user->id,
            'customer_name' => 'Test Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 1500000,
            'pdf_filename' => 'cached.pdf',
            'pdf_path' => 'storage/app/pdfs/cached.pdf',
            'selected_tenures' => [10, 15, 20],
            'branch_id' => $branch->id,
            'status' => Quotation::STATUS_ACTIVE,
        ]);
    }

    private function input(array $overrides = []): array
    {
        return array_merge([
            'customerName' => 'Test Customer',
            'customerType' => 'salaried',
            'loanAmount' => 1500000,
            'selectedTenures' => [10, 15],
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
                ['en' => 'PAN Card', 'gu' => 'પાન કાર્ડ', 'excluded' => true],
                ['en' => 'Passport', 'gu' => '', 'excluded' => false],
            ],
        ], $overrides);
    }

    public function test_create_persists_all_docs_including_excluded_ones(): void
    {
        config(['app.skip_pdf_generation' => true]);

        Bank::create(['name' => 'TestBank', 'is_active' => true]);
        $user = $this->makeUser();

        $service = app(QuotationService::class);
        $result = $service->generate($this->input(), $user->id);

        $this->assertTrue($result['success'] ?? false);
        $quotation = $result['quotation'];
        $docs = $quotation->documents()->orderBy('sequence')->get();

        $this->assertCount(3, $docs);
        $this->assertSame('Aadhar Card', $docs[0]->document_name_en);
        $this->assertFalse($docs[0]->is_excluded);
        $this->assertSame('PAN Card', $docs[1]->document_name_en);
        $this->assertTrue($docs[1]->is_excluded);
        $this->assertSame('Passport', $docs[2]->document_name_en);
        $this->assertFalse($docs[2]->is_excluded);
    }

    public function test_template_data_filters_excluded_docs_for_pdf(): void
    {
        $service = app(QuotationService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildTemplateDataFromInput');
        $method->setAccessible(true);

        $templateData = $method->invoke($service, $this->input());

        // documentsAll holds every row (3); documents only the included ones (2).
        $this->assertCount(3, $templateData['documentsAll']);
        $this->assertCount(2, $templateData['documents']);
        $names = array_column($templateData['documents'], 'en');
        $this->assertContains('Aadhar Card', $names);
        $this->assertContains('Passport', $names);
        $this->assertNotContains('PAN Card', $names);
    }

    public function test_show_template_data_filters_excluded_docs(): void
    {
        $quotation = $this->makeQuotation();
        QuotationBank::create([
            'quotation_id' => $quotation->id,
            'bank_name' => 'TestBank',
            'roi_min' => 8.5,
            'roi_max' => 9.5,
            'pf_charge' => 1.5,
            'admin_charge' => 5000,
            'stamp_notary' => 2000,
            'registration_fee' => 1000,
            'advocate_fees' => 1500,
            'iom_charge' => 1000,
            'tc_report' => 500,
            'extra1_amount' => 0,
            'extra2_amount' => 0,
            'total_charges' => 11000,
        ]);
        QuotationDocument::create([
            'quotation_id' => $quotation->id,
            'document_name_en' => 'Included Doc',
            'is_excluded' => false,
            'sequence' => 0,
        ]);
        QuotationDocument::create([
            'quotation_id' => $quotation->id,
            'document_name_en' => 'Excluded Doc',
            'is_excluded' => true,
            'sequence' => 1,
        ]);

        $controller = new QuotationController(
            app(ConfigService::class),
            app(QuotationService::class),
            app(NotificationService::class),
        );
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('buildTemplateData');
        $method->setAccessible(true);

        $data = $method->invoke($controller, $quotation->fresh(['banks.emiEntries', 'documents']), true);

        $names = array_column($data['documents'], 'en');
        $this->assertContains('Included Doc', $names);
        $this->assertNotContains('Excluded Doc', $names);
    }
}
