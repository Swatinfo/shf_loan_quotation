<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationDocument;
use App\Models\Role;
use App\Models\User;
use App\Services\LoanDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks in the rule that a quotation's `is_excluded` flag is purely cosmetic
 * for the quotation PDF — it must NOT propagate to the loan's document list.
 * When a quotation is converted, the loan should receive every doc with
 * `is_required = true` regardless of strike-out state.
 */
class LoanConversionDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        Role::firstOrCreate(['slug' => 'loan_advisor'], ['name' => 'Loan Advisor']);
        $user = User::create([
            'name' => 'U'.uniqid(),
            'email' => uniqid().'@test',
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);
        $user->roles()->sync(Role::where('slug', 'loan_advisor')->pluck('id'));

        return $user->fresh('roles');
    }

    private function makeQuotation(): Quotation
    {
        $branch = Branch::create(['name' => 'B-'.uniqid(), 'is_active' => true]);
        $user = $this->makeUser();

        return Quotation::create([
            'user_id' => $user->id,
            'customer_name' => 'Convertee',
            'customer_type' => 'salaried',
            'loan_amount' => 1500000,
            'pdf_filename' => 'cached.pdf',
            'pdf_path' => 'storage/app/pdfs/cached.pdf',
            'selected_tenures' => [10, 15],
            'branch_id' => $branch->id,
            'status' => Quotation::STATUS_ACTIVE,
        ]);
    }

    private function makeLoan(Quotation $quotation): LoanDetail
    {
        $bank = Bank::create(['name' => 'B-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'P-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);
        $user = User::first() ?? $this->makeUser();

        return LoanDetail::create([
            'loan_number' => 'L-'.uniqid(),
            'quotation_id' => $quotation->id,
            'customer_name' => $quotation->customer_name,
            'customer_type' => $quotation->customer_type,
            'loan_amount' => $quotation->loan_amount,
            'status' => 'active',
            'current_stage' => 'document_collection',
            'bank_id' => $bank->id,
            'branch_id' => $quotation->branch_id,
            'product_id' => $product->id,
            'created_by' => $user->id,
            'assigned_advisor' => $user->id,
        ]);
    }

    public function test_excluded_quotation_documents_still_become_required_loan_documents(): void
    {
        $quotation = $this->makeQuotation();

        // Three docs: included, struck-out, included. The PDF would skip the
        // middle row; the loan must NOT — every doc flows through as required.
        QuotationDocument::create([
            'quotation_id' => $quotation->id,
            'document_name_en' => 'Aadhar Card',
            'document_name_gu' => 'આધાર કાર્ડ',
            'is_excluded' => false,
            'sequence' => 0,
        ]);
        QuotationDocument::create([
            'quotation_id' => $quotation->id,
            'document_name_en' => 'Form 16',
            'document_name_gu' => 'ફોર્મ 16',
            'is_excluded' => true,  // ← struck on the quotation
            'sequence' => 1,
        ]);
        QuotationDocument::create([
            'quotation_id' => $quotation->id,
            'document_name_en' => 'PAN Card',
            'document_name_gu' => 'પાન કાર્ડ',
            'is_excluded' => false,
            'sequence' => 2,
        ]);

        $loan = $this->makeLoan($quotation);

        app(LoanDocumentService::class)->populateFromQuotation($loan, $quotation);

        $loanDocs = $loan->documents()->orderBy('sort_order')->get();

        // All three propagate — strike-out is cosmetic on the quotation only.
        $this->assertCount(3, $loanDocs);
        $names = $loanDocs->pluck('document_name_en')->all();
        $this->assertSame(['Aadhar Card', 'Form 16', 'PAN Card'], $names);

        // All three required — including the struck Form 16. The loan team
        // must still collect it for the actual disbursement.
        $loanDocs->each(function ($doc) {
            $this->assertTrue((bool) $doc->is_required, "{$doc->document_name_en} must be required on the loan");
            $this->assertSame('pending', $doc->status);
        });
    }
}
