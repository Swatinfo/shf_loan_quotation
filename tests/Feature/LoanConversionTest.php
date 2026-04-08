<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationBank;
use App\Models\QuotationDocument;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanConversionTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    private Branch $branch;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(StageSeeder::class);
        $this->bank = Bank::create(['name' => 'HDFC Bank']);
        $this->branch = Branch::create(['name' => 'Main Branch', 'code' => 'MB01']);
        $this->product = Product::create(['name' => 'Home Loan', 'bank_id' => $this->bank->id]);
    }

    private function createUser(string $role = 'admin', array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_active' => true,
            'task_role' => 'loan_advisor',
        ], $overrides));
    }

    private function createQuotationWithBank(User $user): Quotation
    {
        $quotation = Quotation::create([
            'user_id' => $user->id,
            'customer_name' => 'Test Customer',
            'customer_type' => 'proprietor',
            'loan_amount' => 5000000,
            'pdf_filename' => 'test.pdf',
            'pdf_path' => '/tmp/test.pdf',
            'selected_tenures' => [5, 10],
        ]);

        QuotationBank::create([
            'quotation_id' => $quotation->id,
            'bank_name' => 'HDFC Bank',
            'roi_min' => 8.50,
            'roi_max' => 9.00,
            'pf_charge' => 1.00,
            'admin_charge' => 5000,
            'stamp_notary' => 1000,
            'registration_fee' => 0,
            'advocate_fees' => 3000,
            'iom_charge' => 500,
            'tc_report' => 0,
            'total_charges' => 9500,
        ]);

        QuotationDocument::create([
            'quotation_id' => $quotation->id,
            'document_name_en' => 'PAN Card',
            'document_name_gu' => 'પાન કાર્ડ',
        ]);

        return $quotation->fresh();
    }

    private function conversionPayload(array $overrides = []): array
    {
        return array_merge([
            'bank_index' => 0,
            'branch_id' => $this->branch->id,
            'product_id' => $this->product->id,
            'customer_phone' => '9876543210',
            'assigned_advisor' => $overrides['assigned_advisor'] ?? User::where('task_role', 'loan_advisor')->first()?->id ?? auth()->id(),
        ], $overrides);
    }

    public function test_can_convert_quotation_to_loan(): void
    {
        $user = $this->createUser();
        $quotation = $this->createQuotationWithBank($user);

        $response = $this->actingAs($user)->post(
            route('quotations.convert.store', $quotation),
            $this->conversionPayload()
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('loan_details', [
            'customer_name' => 'Test Customer',
            'customer_type' => 'proprietor',
            'loan_amount' => 5000000,
            'bank_name' => 'HDFC Bank',
            'status' => 'active',
        ]);

        // Quotation should be linked
        $quotation->refresh();
        $this->assertNotNull($quotation->loan_id);

        // Documents should be copied
        $loan = LoanDetail::where('customer_name', 'Test Customer')->first();
        $this->assertGreaterThan(0, $loan->documents()->count());

        // Stages should be initialized
        $this->assertGreaterThan(0, $loan->stageAssignments()->count());

        // Stages 1 & 2 (inquiry, document_selection) should be auto-completed
        $inquiry = $loan->stageAssignments()->where('stage_key', 'inquiry')->first();
        $this->assertEquals('completed', $inquiry->status);

        $docSelection = $loan->stageAssignments()->where('stage_key', 'document_selection')->first();
        $this->assertEquals('completed', $docSelection->status);

        // Current stage should be document_collection
        $this->assertEquals('document_collection', $loan->current_stage);
    }

    public function test_cannot_convert_already_converted_quotation(): void
    {
        $user = $this->createUser();
        $quotation = $this->createQuotationWithBank($user);

        // First conversion
        $this->actingAs($user)->post(
            route('quotations.convert.store', $quotation),
            $this->conversionPayload()
        );

        $this->assertEquals(1, LoanDetail::count());

        // Second conversion — quotation is already converted
        $response = $this->actingAs($user)->post(
            route('quotations.convert.store', $quotation->fresh()),
            $this->conversionPayload()
        );

        $response->assertRedirect();
        // Should still only have 1 loan
        $this->assertEquals(1, LoanDetail::count());
    }

    public function test_staff_with_permission_can_convert(): void
    {
        $staff = $this->createUser('staff');
        $quotation = $this->createQuotationWithBank($staff);

        $response = $this->actingAs($staff)->post(
            route('quotations.convert.store', $quotation),
            $this->conversionPayload()
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_details', [
            'customer_name' => 'Test Customer',
        ]);
    }

    public function test_can_create_direct_loan(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->post(route('loans.store'), [
            'customer_name' => 'Direct Loan Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 3000000,
            'bank_id' => $this->bank->id,
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'customer_phone' => '9876543210',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_details', [
            'customer_name' => 'Direct Loan Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 3000000,
            'status' => 'active',
            'current_stage' => 'inquiry',
        ]);
    }

    public function test_loan_number_is_generated(): void
    {
        $user = $this->createUser();
        $quotation = $this->createQuotationWithBank($user);

        $this->actingAs($user)->post(
            route('quotations.convert.store', $quotation),
            $this->conversionPayload()
        );

        $loan = LoanDetail::first();
        $this->assertNotNull($loan);
        $this->assertStringStartsWith('SHF-', $loan->loan_number);
    }

    public function test_convert_form_loads_for_unconverted_quotation(): void
    {
        $user = $this->createUser();
        $quotation = $this->createQuotationWithBank($user);

        $response = $this->actingAs($user)->get(
            route('quotations.convert', $quotation)
        );

        $response->assertOk();
    }

    public function test_conversion_copies_documents_from_quotation(): void
    {
        $user = $this->createUser();
        $quotation = $this->createQuotationWithBank($user);

        // Add another doc
        QuotationDocument::create([
            'quotation_id' => $quotation->id,
            'document_name_en' => 'Aadhar Card',
            'document_name_gu' => 'આધાર કાર્ડ',
        ]);

        $this->actingAs($user)->post(
            route('quotations.convert.store', $quotation->fresh()),
            $this->conversionPayload()
        );

        $loan = LoanDetail::first();
        $this->assertNotNull($loan);
        $this->assertEquals(2, $loan->documents()->count());
        $this->assertDatabaseHas('loan_documents', [
            'loan_id' => $loan->id,
            'document_name_en' => 'PAN Card',
            'status' => 'pending',
        ]);
    }

    public function test_conversion_with_extra_fields(): void
    {
        $user = $this->createUser();
        $quotation = $this->createQuotationWithBank($user);

        $response = $this->actingAs($user)->post(
            route('quotations.convert.store', $quotation),
            $this->conversionPayload([
                'customer_email' => 'test@example.com',
                'notes' => 'Urgent processing required',
            ])
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_details', [
            'customer_phone' => '9876543210',
            'customer_email' => 'test@example.com',
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_conversion_fails_with_missing_required_fields(): void
    {
        $user = $this->createUser();
        $quotation = $this->createQuotationWithBank($user);

        $response = $this->actingAs($user)->post(
            route('quotations.convert.store', $quotation),
            ['bank_index' => 0] // Missing branch_id, product_id, customer_phone
        );

        $response->assertSessionHasErrors(['branch_id', 'product_id', 'customer_phone']);
        $this->assertEquals(0, LoanDetail::count());
    }
}
