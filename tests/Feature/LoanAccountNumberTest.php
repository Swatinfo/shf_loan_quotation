<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\DisbursementDetail;
use App\Models\DisbursementEntry;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LoanDetail::loan_account_numbers — distinct account numbers across active
 * disbursement tranches; also surfaced in the loans-list JSON.
 */
class LoanAccountNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
    }

    private function admin(): User
    {
        $u = User::create(['name' => 'A-'.uniqid(), 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->roles()->sync(Role::where('slug', 'super_admin')->pluck('id'));

        return $u->fresh('roles');
    }

    private function loan(User $owner): LoanDetail
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'Product-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        return LoanDetail::create([
            'loan_number' => 'SHF-'.uniqid(), 'customer_name' => 'C', 'customer_type' => 'salaried',
            'loan_amount' => 1000000, 'status' => 'active', 'current_stage' => 'disbursement',
            'bank_id' => $bank->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'created_by' => $owner->id, 'assigned_advisor' => $owner->id,
        ]);
    }

    private function entry(LoanDetail $loan, ?string $acct, bool $active = true): void
    {
        $detail = DisbursementDetail::firstOrCreate(
            ['loan_id' => $loan->id],
            ['disbursement_type' => 'fund_transfer']
        );

        DisbursementEntry::create([
            'loan_id' => $loan->id, 'disbursement_detail_id' => $detail->id,
            'disbursement_date' => now()->toDateString(), 'method' => 'fund_transfer',
            'loan_account_number' => $acct, 'amount' => 100000, 'is_active' => $active,
        ]);
    }

    public function test_empty_before_disbursement(): void
    {
        $loan = $this->loan($this->admin());
        $this->assertSame('', $loan->fresh()->loan_account_numbers);
    }

    public function test_distinct_active_account_numbers_joined(): void
    {
        $loan = $this->loan($this->admin());
        $this->entry($loan, 'ACCT-111');
        $this->entry($loan, 'ACCT-222');
        $this->entry($loan, 'ACCT-111'); // duplicate → deduped
        $this->entry($loan, 'ACCT-999', active: false); // inactive → excluded

        $result = $loan->fresh()->loan_account_numbers;

        $this->assertStringContainsString('ACCT-111', $result);
        $this->assertStringContainsString('ACCT-222', $result);
        $this->assertStringNotContainsString('ACCT-999', $result);
        // Deduped: 111 appears once.
        $this->assertSame(1, substr_count($result, 'ACCT-111'));
    }

    public function test_loans_list_json_includes_account_numbers(): void
    {
        $admin = $this->admin();
        $loan = $this->loan($admin);
        $this->entry($loan, 'ACCT-777');

        $response = $this->actingAs($admin)->getJson(route('loans.data'))->assertOk();
        $row = collect($response->json('data'))->firstWhere('loan_number_raw', $loan->loan_number);

        $this->assertNotNull($row);
        $this->assertSame('ACCT-777', $row['loan_account_numbers']);
    }
}
