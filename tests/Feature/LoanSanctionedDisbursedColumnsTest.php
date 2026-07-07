<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\DisbursementDetail;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\StageAssignment;
use App\Models\User;
use App\Services\DisbursementService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * `sanctioned_amount` and `disbursed_amount` are real columns on `loan_details`,
 * populated at docket login / disbursement, and shown as separate columns in
 * the loans listing.
 */
class LoanSanctionedDisbursedColumnsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
    }

    private function admin(): User
    {
        $user = User::create([
            'name' => 'Admin '.uniqid(),
            'email' => uniqid().'@test',
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);
        $user->roles()->sync(Role::where('slug', 'super_admin')->pluck('id'));

        return $user->fresh('roles');
    }

    private function makeLoan(User $owner, array $attrs = []): LoanDetail
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'Product-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        return LoanDetail::create(array_merge([
            'loan_number' => 'L-'.uniqid(),
            'customer_name' => 'Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 1000000,
            'status' => 'active',
            'current_stage' => 'docket',
            'bank_id' => $bank->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'created_by' => $owner->id,
            'assigned_advisor' => $owner->id,
        ], $attrs));
    }

    public function test_formatted_accessors_return_null_when_empty_and_value_when_set(): void
    {
        $loan = $this->makeLoan($this->admin());

        $this->assertNull($loan->formatted_sanctioned_amount);
        $this->assertNull($loan->formatted_disbursed_amount);

        $loan->update(['sanctioned_amount' => 2500000, 'disbursed_amount' => 2400000]);
        $loan->refresh();

        $this->assertStringContainsString('25,00,000', $loan->formatted_sanctioned_amount);
        $this->assertStringContainsString('24,00,000', $loan->formatted_disbursed_amount);
    }

    public function test_loan_listing_returns_sanctioned_and_disbursed_columns(): void
    {
        $admin = $this->admin();
        $withAmounts = $this->makeLoan($admin, ['sanctioned_amount' => 2500000, 'disbursed_amount' => 2400000]);
        $withoutAmounts = $this->makeLoan($admin);

        $rows = collect($this->actingAs($admin)
            ->getJson(route('loans.data'))
            ->assertOk()
            ->json('data'));

        $withRow = $rows->firstWhere('loan_number', $withAmounts->loan_number);
        $withoutRow = $rows->firstWhere('loan_number', $withoutAmounts->loan_number);

        $this->assertStringContainsString('25,00,000', $withRow['sanctioned_info']);
        $this->assertStringContainsString('24,00,000', $withRow['disbursed_info']);
        $this->assertSame('—', $withoutRow['sanctioned_info']);
        $this->assertSame('—', $withoutRow['disbursed_info']);
    }

    public function test_saving_docket_notes_populates_sanctioned_amount_column(): void
    {
        $admin = $this->admin();
        $loan = $this->makeLoan($admin);
        // Completed assignment skips field validation so we isolate the column sync.
        StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'docket',
            'assigned_to' => $admin->id,
            'status' => 'completed',
            'is_parallel_stage' => false,
        ]);

        $this->actingAs($admin)
            ->postJson(route('loans.stages.notes', ['loan' => $loan->id, 'stageKey' => 'docket']), [
                'notes_data' => ['sanctioned_amount' => '2700000'],
            ])
            ->assertOk();

        $this->assertSame(2700000, $loan->fresh()->sanctioned_amount);
    }

    public function test_sanction_stage_does_not_override_existing_docket_amount(): void
    {
        $admin = $this->admin();
        $loan = $this->makeLoan($admin, ['sanctioned_amount' => 3000000]);
        StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'sanction',
            'assigned_to' => $admin->id,
            'status' => 'completed',
            'is_parallel_stage' => false,
        ]);

        $this->actingAs($admin)
            ->postJson(route('loans.stages.notes', ['loan' => $loan->id, 'stageKey' => 'sanction']), [
                'notes_data' => ['sanctioned_amount' => '9999999'],
            ])
            ->assertOk();

        // docket-captured value must win; sanction only fills when empty.
        $this->assertSame(3000000, $loan->fresh()->sanctioned_amount);
    }

    public function test_processing_disbursement_populates_disbursed_amount_column(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $loan = $this->makeLoan($admin, ['current_stage' => 'disbursement']);
        StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'disbursement',
            'assigned_to' => $admin->id,
            'status' => 'in_progress',
            'is_parallel_stage' => false,
        ]);

        // Avoid real notification/FCM machinery (queue is sync in tests).
        $this->mock(NotificationService::class, function ($m) {
            $m->shouldReceive('notifyStageCompleted')->andReturnNull();
            $m->shouldReceive('notifyLoanCompleted')->andReturnNull();
        });

        app(DisbursementService::class)->processDisbursement($loan, [
            'entries' => [[
                'disbursement_date' => now()->toDateString(),
                'method' => 'fund_transfer',
                'product_id' => $loan->product_id,
                'product_name' => 'Test Product',
                'loan_account_number' => '1234567890',
                'amount' => 2400000,
            ]],
        ]);

        $this->assertSame(2400000, $loan->fresh()->disbursed_amount);
        $this->assertSame(2400000, DisbursementDetail::where('loan_id', $loan->id)->value('amount_disbursed'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
