<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\DisbursementDetail;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\ProductStage;
use App\Models\Role;
use App\Models\ShfNotification;
use App\Models\Stage;
use App\Models\StageAssignment;
use App\Models\StageTransfer;
use App\Models\User;
use App\Services\LoanStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Auto-propagation of stage/task-owner config changes onto eligible in-flight
 * loans (not completed/rejected/cancelled, not disbursed). Manual transfers are
 * always preserved. Also covers the "Sync Settings" bulk route.
 */
class StageConfigPropagationTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    private Branch $branch;

    private Product $product;

    private Stage $stage;

    private LoanStageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        Role::firstOrCreate(['slug' => 'bank_employee'], ['name' => 'Bank Employee']);

        $this->service = app(LoanStageService::class);

        $this->bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $this->branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $this->product = Product::create(['name' => 'Product-'.uniqid(), 'bank_id' => $this->bank->id, 'is_active' => true]);

        // A single-phase, bank_employee-owned stage so the assignee comes from config.
        $this->stage = Stage::firstOrCreate(['stage_key' => 'kfs'], [
            'stage_name_en' => 'KFS',
            'stage_name_gu' => 'KFS',
            'sequence_order' => 8,
            'is_parallel' => false,
            'parent_stage_key' => null,
            'stage_type' => 'sequential',
            'assigned_role' => 'bank_employee',
            'is_enabled' => true,
        ]);
    }

    private function user(string $role = 'bank_employee'): User
    {
        $u = User::create([
            'name' => 'U-'.uniqid(),
            'email' => uniqid().'@test.local',
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);
        $u->roles()->sync(Role::where('slug', $role)->pluck('id'));

        return $u->fresh('roles');
    }

    /**
     * Build a loan whose frozen snapshot points the kfs stage at $oldOwner, with
     * a single kfs assignment in_progress assigned to $assignee.
     */
    private function makeLoan(User $oldOwner, User $assignee, string $status = 'active'): LoanDetail
    {
        // Config that produced the snapshot: kfs default user = oldOwner.
        ProductStage::updateOrCreate(
            ['product_id' => $this->product->id, 'stage_id' => $this->stage->id],
            ['is_enabled' => true, 'default_user_id' => $oldOwner->id],
        );

        $creator = $this->user();
        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(),
            'customer_name' => 'Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 1000000,
            'status' => $status,
            'current_stage' => 'kfs',
            'bank_id' => $this->bank->id,
            'branch_id' => $this->branch->id,
            'product_id' => $this->product->id,
            'created_by' => $creator->id,
            'assigned_advisor' => $creator->id,
        ]);

        $loan->update([
            'workflow_config' => $this->service->buildWorkflowSnapshot(
                $loan->bank_id, $loan->product_id, $loan->branch_id, $loan->location_id
            ),
        ]);

        StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'kfs',
            'status' => 'in_progress',
            'assigned_to' => $assignee->id,
            'is_parallel_stage' => false,
            'parent_stage_key' => null,
            'started_at' => now(),
        ]);

        return $loan->fresh('stageAssignments');
    }

    public function test_config_change_repoints_owner_and_notifies(): void
    {
        $oldBE = $this->user();
        $newBE = $this->user();
        $loan = $this->makeLoan($oldBE, $oldBE);

        // Snapshot really points at the old owner.
        $this->assertSame($oldBE->id, $loan->workflow_config['kfs']['default_user_id']);

        // Admin changes the task owner.
        ProductStage::where('product_id', $this->product->id)
            ->where('stage_id', $this->stage->id)
            ->update(['default_user_id' => $newBE->id]);

        $result = $this->service->propagateConfigToEligibleLoans($this->product->id);

        $this->assertSame(1, $result['loans_processed']);
        $this->assertSame(1, $result['stages_reassigned']);

        $loan->refresh();
        $this->assertSame($newBE->id, $loan->workflow_config['kfs']['default_user_id']);
        $this->assertSame($newBE->id, $loan->stageAssignments->firstWhere('stage_key', 'kfs')->assigned_to);

        $this->assertTrue(
            ShfNotification::where('user_id', $newBE->id)->where('type', 'assignment')->exists(),
            'new owner should be notified'
        );
        $this->assertTrue(
            StageTransfer::where('loan_id', $loan->id)->where('transferred_to', $newBE->id)->exists(),
            'a transfer ledger row should be written'
        );
    }

    public function test_manual_transfer_is_preserved(): void
    {
        $oldBE = $this->user();
        $newBE = $this->user();
        $manualBE = $this->user();

        // Loan's kfs was hand-transferred to $manualBE (not the snapshot default).
        $loan = $this->makeLoan($oldBE, $manualBE);

        ProductStage::where('product_id', $this->product->id)
            ->where('stage_id', $this->stage->id)
            ->update(['default_user_id' => $newBE->id]);

        $result = $this->service->propagateConfigToEligibleLoans($this->product->id);

        $this->assertSame(0, $result['stages_reassigned']);
        $loan->refresh();
        $this->assertSame($manualBE->id, $loan->stageAssignments->firstWhere('stage_key', 'kfs')->assigned_to);
    }

    public function test_disbursed_but_open_loan_is_updated(): void
    {
        $oldBE = $this->user();
        $newBE = $this->user();
        $loan = $this->makeLoan($oldBE, $oldBE);

        // Disbursement started but the loan is not completed → eligible now.
        DisbursementDetail::create(['loan_id' => $loan->id, 'disbursement_type' => 'fund_transfer']);

        ProductStage::where('product_id', $this->product->id)
            ->where('stage_id', $this->stage->id)
            ->update(['default_user_id' => $newBE->id]);

        $result = $this->service->propagateConfigToEligibleLoans($this->product->id);

        $this->assertSame(1, $result['stages_reassigned']);
        $this->assertSame($newBE->id, $loan->fresh()->stageAssignments->firstWhere('stage_key', 'kfs')->assigned_to);
    }

    public function test_only_completed_loans_are_excluded(): void
    {
        $oldBE = $this->user();
        $newBE = $this->user();

        // Completed → excluded (only exclusion).
        $completed = $this->makeLoan($oldBE, $oldBE, status: 'completed');
        // Rejected → NOT completed, so eligible: its in_progress stage is re-pointed.
        $rejected = $this->makeLoan($oldBE, $oldBE, status: 'rejected');

        ProductStage::where('product_id', $this->product->id)
            ->where('stage_id', $this->stage->id)
            ->update(['default_user_id' => $newBE->id]);

        $this->service->propagateConfigToEligibleLoans($this->product->id);

        $this->assertSame($oldBE->id, $completed->fresh()->stageAssignments->firstWhere('stage_key', 'kfs')->assigned_to);
        $this->assertSame($newBE->id, $rejected->fresh()->stageAssignments->firstWhere('stage_key', 'kfs')->assigned_to);
    }

    public function test_on_hold_loan_is_still_propagated(): void
    {
        $oldBE = $this->user();
        $newBE = $this->user();
        $loan = $this->makeLoan($oldBE, $oldBE, status: 'on_hold');

        ProductStage::where('product_id', $this->product->id)
            ->where('stage_id', $this->stage->id)
            ->update(['default_user_id' => $newBE->id]);

        $result = $this->service->propagateConfigToEligibleLoans($this->product->id);

        $this->assertSame(1, $result['stages_reassigned']);
        $this->assertSame($newBE->id, $loan->fresh()->stageAssignments->firstWhere('stage_key', 'kfs')->assigned_to);
    }

    public function test_sync_settings_route_reapplies_across_products(): void
    {
        $oldBE = $this->user();
        $newBE = $this->user();
        $loan = $this->makeLoan($oldBE, $oldBE);

        ProductStage::where('product_id', $this->product->id)
            ->where('stage_id', $this->stage->id)
            ->update(['default_user_id' => $newBE->id]);

        $admin = $this->user('super_admin');

        $this->actingAs($admin)
            ->post(route('loan-settings.sync-stage-config'))
            ->assertRedirect();

        $this->assertSame($newBE->id, $loan->fresh()->stageAssignments->firstWhere('stage_key', 'kfs')->assigned_to);
    }

    /** Stage Master save calls propagateConfigToEligibleLoans(null, null) — all eligible loans. */
    public function test_master_scope_null_reapplies_to_all_eligible_loans(): void
    {
        $oldBE = $this->user();
        $newBE = $this->user();
        $loan = $this->makeLoan($oldBE, $oldBE);

        ProductStage::where('product_id', $this->product->id)
            ->where('stage_id', $this->stage->id)
            ->update(['default_user_id' => $newBE->id]);

        $result = $this->service->propagateConfigToEligibleLoans(null, null);

        $this->assertGreaterThanOrEqual(1, $result['stages_reassigned']);
        $this->assertSame($newBE->id, $loan->fresh()->stageAssignments->firstWhere('stage_key', 'kfs')->assigned_to);
    }
}
