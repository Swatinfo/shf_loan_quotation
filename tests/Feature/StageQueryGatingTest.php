<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\LoanProgress;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stage;
use App\Models\StageAssignment;
use App\Models\StageQuery;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * An open query (pending/responded) on a stage blocks its actions until
 * resolved. Server endpoints must return a clean 422 / redirect-with-error
 * (never a raw exception page), and the stages UI hides the action controls
 * stage-wise (a query on one parallel sub-stage does not block its siblings).
 */
class StageQueryGatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        Permission::firstOrCreate(['slug' => 'manage_loan_stages'], ['name' => 'Manage Loan Stages', 'group' => 'Loans']);
        $this->seedStages();
        $this->mock(NotificationService::class)->shouldIgnoreMissing();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function seedStages(): void
    {
        $main = ['inquiry', 'document_selection', 'document_collection', 'parallel_processing', 'rate_pf', 'sanction', 'docket', 'kfs', 'esign', 'disbursement', 'otc_clearance'];
        foreach ($main as $i => $key) {
            Stage::firstOrCreate(['stage_key' => $key], [
                'stage_name_en' => ucwords(str_replace('_', ' ', $key)), 'stage_name_gu' => $key,
                'sequence_order' => $i + 1, 'is_parallel' => $key === 'parallel_processing',
                'parent_stage_key' => null, 'stage_type' => 'sequential', 'is_enabled' => true,
            ]);
        }
        foreach (['app_number', 'bsm_osv', 'legal_verification', 'technical_valuation', 'sanction_decision'] as $i => $key) {
            Stage::firstOrCreate(['stage_key' => $key], [
                'stage_name_en' => ucwords(str_replace('_', ' ', $key)), 'stage_name_gu' => $key,
                'sequence_order' => 40 + $i, 'is_parallel' => true,
                'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sub', 'is_enabled' => true,
            ]);
        }
    }

    private function admin(): User
    {
        $u = User::create(['name' => 'A-'.uniqid(), 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->roles()->sync(Role::where('slug', 'super_admin')->pluck('id'));

        return $u->fresh('roles');
    }

    private function makeLoan(User $owner, string $currentStage = 'rate_pf'): LoanDetail
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'Product-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(), 'customer_name' => 'C', 'customer_type' => 'salaried',
            'loan_amount' => 1000000, 'status' => 'active', 'current_stage' => $currentStage,
            'bank_id' => $bank->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'created_by' => $owner->id, 'assigned_advisor' => $owner->id,
        ]);
        LoanProgress::create(['loan_id' => $loan->id, 'total_stages' => 11]);

        return $loan;
    }

    private function assign(LoanDetail $loan, string $stageKey, User $owner, string $status = 'in_progress', bool $parallel = false, ?array $notes = null): StageAssignment
    {
        return StageAssignment::create([
            'loan_id' => $loan->id, 'stage_key' => $stageKey, 'assigned_to' => $owner->id,
            'status' => $status, 'is_parallel_stage' => $parallel,
            'parent_stage_key' => $parallel ? 'parallel_processing' : null,
            'notes' => $notes ? json_encode($notes) : null,
        ]);
    }

    /** Full rate_pf notes so ratePfAction's required-field check passes. */
    private function ratePfNotes(): array
    {
        return [
            'rate_pf_phase' => '1', 'interest_rate' => '8.5', 'repo_rate' => '6.5', 'bank_rate' => '2.0',
            'rate_offered_date' => '01/10/2026', 'rate_valid_until' => '30/10/2026',
            'processing_fee_type' => 'percent', 'processing_fee' => '0.5',
            'gst_percent' => '18', 'admin_charges' => '5000', 'admin_charges_gst_percent' => '18',
        ];
    }

    private function openQuery(StageAssignment $a, User $raiser, string $status = 'pending'): StageQuery
    {
        return StageQuery::create([
            'stage_assignment_id' => $a->id, 'loan_id' => $a->loan_id, 'stage_key' => $a->stage_key,
            'query_text' => 'Please clarify.', 'raised_by' => $raiser->id, 'assigned_to_user_id' => $raiser->id,
            'status' => $status,
        ]);
    }

    public function test_rate_pf_complete_blocked_by_open_query_returns_422(): void
    {
        $admin = $this->admin();
        $loan = $this->makeLoan($admin, 'rate_pf');
        $a = $this->assign($loan, 'rate_pf', $admin, 'in_progress', false, $this->ratePfNotes());
        $this->openQuery($a, $admin);

        $this->actingAs($admin)
            ->postJson(route('loans.stages.rate-pf-action', $loan), ['action' => 'complete'])
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'An open query is blocking this stage. Resolve it before completing.']);

        $this->assertSame('in_progress', $a->fresh()->status);
    }

    public function test_esign_complete_blocked_by_open_query_returns_422(): void
    {
        $admin = $this->admin();
        $loan = $this->makeLoan($admin, 'esign');
        $a = $this->assign($loan, 'esign', $admin);
        $this->openQuery($a, $admin, 'responded'); // responded still blocks

        $this->actingAs($admin)
            ->postJson(route('loans.stages.esign-action', $loan), ['action' => 'esign_complete'])
            ->assertStatus(422);

        $this->assertSame('in_progress', $a->fresh()->status);
    }

    public function test_valuation_store_blocked_by_open_query_redirects_with_error(): void
    {
        $admin = $this->admin();
        $loan = $this->makeLoan($admin, 'parallel_processing');
        $a = $this->assign($loan, 'technical_valuation', $admin, 'in_progress', true);
        $this->openQuery($a, $admin);

        $this->actingAs($admin)
            ->from(route('loans.stages', $loan))
            ->post(route('loans.valuation.store', $loan), []) // empty — block happens before validation
            ->assertRedirect(route('loans.stages', $loan))
            ->assertSessionHas('error');

        $this->assertSame('in_progress', $a->fresh()->status);
        $this->assertCount(0, $loan->valuationDetails()->get());
    }

    public function test_query_resolved_unblocks_rate_pf_completion(): void
    {
        $admin = $this->admin();
        $loan = $this->makeLoan($admin, 'rate_pf');
        $a = $this->assign($loan, 'rate_pf', $admin, 'in_progress', false, $this->ratePfNotes());
        $q = $this->openQuery($a, $admin);

        $q->update(['status' => 'resolved']);

        $this->actingAs($admin)
            ->postJson(route('loans.stages.rate-pf-action', $loan), ['action' => 'complete'])
            ->assertOk();

        $this->assertSame('completed', $a->fresh()->status);
    }
}
