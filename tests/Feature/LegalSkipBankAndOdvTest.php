<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stage;
use App\Models\StageAssignment;
use App\Models\User;
use App\Services\LoanStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Two interlocking workflow rules under test:
 *   1. Legal Verification can be completed without sending to bank by the
 *      task owner / branch_manager / bdh of the loan's branch (super_admin
 *      bypasses all gates).
 *   2. The new `original_document_verification` sub-stage opens after legal
 *      completes — and is auto-completed when legal was completed via the
 *      skip-bank path.
 */
class LegalSkipBankAndOdvTest extends TestCase
{
    use RefreshDatabase;

    private LoanStageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LoanStageService::class);
        $this->seedRolesAndPermissions();
        $this->seedStages();
    }

    private function seedRolesAndPermissions(): void
    {
        foreach (['super_admin', 'admin', 'branch_manager', 'bdh', 'loan_advisor', 'bank_employee', 'office_employee'] as $slug) {
            Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => ucwords(str_replace('_', ' ', $slug))],
            );
        }

        // The legal-action route is gated by `permission:manage_loan_stages`.
        // Seed the permission and grant it to the roles whose users will
        // exercise the action in tests.
        $perm = Permission::firstOrCreate(
            ['slug' => 'manage_loan_stages'],
            ['name' => 'Manage Loan Stages', 'group' => 'Loans'],
        );
        foreach (['loan_advisor', 'branch_manager', 'bdh', 'bank_employee', 'admin'] as $slug) {
            $roleId = Role::where('slug', $slug)->value('id');
            DB::table('role_permission')->insertOrIgnore([
                ['role_id' => $roleId, 'permission_id' => $perm->id],
            ]);
        }
    }

    private function seedStages(): void
    {
        $main = [
            'inquiry', 'document_selection', 'document_collection',
            'parallel_processing', 'rate_pf', 'sanction',
            'docket', 'kfs', 'esign', 'disbursement', 'otc_clearance',
        ];
        foreach ($main as $i => $key) {
            Stage::firstOrCreate(
                ['stage_key' => $key],
                [
                    'stage_name_en' => ucwords(str_replace('_', ' ', $key)),
                    'sequence_order' => $i + 1,
                    'is_parallel' => $key === 'parallel_processing',
                    'parent_stage_key' => null,
                    'stage_type' => $key === 'parallel_processing' ? 'parallel' : 'sequential',
                    'is_enabled' => true,
                ]
            );
        }

        foreach (['app_number', 'bsm_osv', 'legal_verification', 'sanction_decision', 'original_document_verification'] as $i => $key) {
            Stage::firstOrCreate(
                ['stage_key' => $key],
                [
                    'stage_name_en' => ucwords(str_replace('_', ' ', $key)),
                    'sequence_order' => 100 + $i,
                    'is_parallel' => true,
                    'parent_stage_key' => 'parallel_processing',
                    'stage_type' => 'sequential',
                    'is_enabled' => true,
                ]
            );
        }
    }

    private function makeUser(string $roleSlug, ?Branch $branch = null): User
    {
        $user = User::create([
            'name' => 'U'.uniqid(),
            'email' => uniqid().'@test',
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);
        $user->roles()->sync(Role::where('slug', $roleSlug)->pluck('id'));
        if ($branch) {
            $user->branches()->sync([$branch->id]);
        }

        return $user->fresh(['roles']);
    }

    private function makeLoan(?User $advisor = null, ?Branch $branch = null): LoanDetail
    {
        $advisor ??= $this->makeUser('loan_advisor');
        $branch ??= Branch::create(['name' => 'B-'.uniqid(), 'is_active' => true]);
        $bank = Bank::create(['name' => 'TestBank-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'P-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(),
            'customer_name' => 'C',
            'customer_type' => 'salaried',
            'loan_amount' => 1000000,
            'status' => 'active',
            'current_stage' => 'parallel_processing',
            'bank_id' => $bank->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'created_by' => $advisor->id,
            'assigned_advisor' => $advisor->id,
        ]);

        $this->service->initializeStages($loan);

        return $loan->fresh();
    }

    private function bringLegalInProgress(LoanDetail $loan, User $actor): StageAssignment
    {
        // Assignments exist after initializeStages. Mark legal in_progress and
        // assign to the actor so the controller's permission flow can find it.
        $assignment = $loan->stageAssignments()->where('stage_key', 'legal_verification')->first();
        $assignment->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'assigned_to' => $actor->id,
        ]);

        return $assignment->fresh();
    }

    /* ── Legal skip-bank action ── */

    public function test_creator_can_complete_legal_without_sending_to_bank(): void
    {
        $advisor = $this->makeUser('loan_advisor');
        $loan = $this->makeLoan($advisor);
        $this->bringLegalInProgress($loan, $advisor);

        $this->actingAs($advisor);
        $resp = $this->postJson(route('loans.stages.legal-action', $loan), [
            'action' => 'complete_skip_bank',
        ]);

        $resp->assertOk();
        $legal = $loan->stageAssignments()->where('stage_key', 'legal_verification')->first();
        $this->assertSame('completed', $legal->status);
        $this->assertTrue($legal->getNotesData()['legal_skipped_bank'] ?? false);
        $this->assertSame('completed_skip_bank', $legal->getNotesData()['legal_phase'] ?? null);
    }

    public function test_branch_manager_in_loan_branch_can_skip_bank(): void
    {
        $branch = Branch::create(['name' => 'B-shared-'.uniqid(), 'is_active' => true]);
        $advisor = $this->makeUser('loan_advisor');
        $bm = $this->makeUser('branch_manager', $branch);
        $loan = $this->makeLoan($advisor, $branch);
        $this->bringLegalInProgress($loan, $advisor);

        $this->actingAs($bm);
        $resp = $this->postJson(route('loans.stages.legal-action', $loan), [
            'action' => 'complete_skip_bank',
        ]);

        $resp->assertOk();
        $this->assertSame('completed', $loan->stageAssignments()->where('stage_key', 'legal_verification')->first()->status);
    }

    public function test_branch_manager_in_other_branch_cannot_skip_bank(): void
    {
        $loanBranch = Branch::create(['name' => 'B-loan-'.uniqid(), 'is_active' => true]);
        $otherBranch = Branch::create(['name' => 'B-other-'.uniqid(), 'is_active' => true]);
        $advisor = $this->makeUser('loan_advisor');
        $outsider = $this->makeUser('branch_manager', $otherBranch);
        $loan = $this->makeLoan($advisor, $loanBranch);
        $this->bringLegalInProgress($loan, $advisor);

        $this->actingAs($outsider);
        $resp = $this->postJson(route('loans.stages.legal-action', $loan), [
            'action' => 'complete_skip_bank',
        ]);

        $resp->assertForbidden();
        $this->assertSame('in_progress', $loan->stageAssignments()->where('stage_key', 'legal_verification')->first()->status);
    }

    public function test_bank_employee_cannot_skip_bank(): void
    {
        $advisor = $this->makeUser('loan_advisor');
        $banker = $this->makeUser('bank_employee');
        $loan = $this->makeLoan($advisor);
        $this->bringLegalInProgress($loan, $advisor);

        $this->actingAs($banker);
        $resp = $this->postJson(route('loans.stages.legal-action', $loan), [
            'action' => 'complete_skip_bank',
        ]);

        $resp->assertForbidden();
    }

    /* ── ODV auto-completion + manual flow ── */

    public function test_skip_bank_auto_completes_original_document_verification(): void
    {
        $advisor = $this->makeUser('loan_advisor');
        $loan = $this->makeLoan($advisor);
        $this->bringLegalInProgress($loan, $advisor);

        $this->actingAs($advisor);
        $this->postJson(route('loans.stages.legal-action', $loan), [
            'action' => 'complete_skip_bank',
        ])->assertOk();

        $odv = $loan->stageAssignments()->where('stage_key', 'original_document_verification')->first();
        $this->assertSame('completed', $odv->status, 'ODV must auto-complete when legal skipped bank');
        $this->assertSame('legal_skipped_bank', $odv->getNotesData()['auto_completed_reason'] ?? null);
    }

    public function test_normal_legal_completion_starts_odv_in_progress_not_completed(): void
    {
        $advisor = $this->makeUser('loan_advisor');
        $loan = $this->makeLoan($advisor);
        $this->bringLegalInProgress($loan, $advisor);

        // Simulate normal completion path (legal_phase=3, no skip flag).
        $legal = $loan->stageAssignments()->where('stage_key', 'legal_verification')->first();
        $legal->mergeNotesData(['legal_phase' => '3']);
        $this->actingAs($advisor);
        $this->service->updateStageStatus($loan, 'legal_verification', 'completed', $advisor->id);

        $odv = $loan->stageAssignments()->where('stage_key', 'original_document_verification')->first();
        $this->assertSame('in_progress', $odv->status, 'ODV must open for manual completion when legal completes normally');
        $this->assertNull($odv->getNotesData()['auto_completed_reason'] ?? null);
    }

    /* ── canStartStage gate ── */

    public function test_odv_cannot_start_until_legal_completes(): void
    {
        $loan = $this->makeLoan();

        // Legal is still pending → ODV must not be startable.
        $this->assertFalse($this->service->canStartStage($loan, 'original_document_verification'));

        $loan->stageAssignments()->where('stage_key', 'legal_verification')->update(['status' => 'completed', 'completed_at' => now()]);

        // After legal completes, ODV is startable (parent must also be in_progress).
        $loan->stageAssignments()->where('stage_key', 'parallel_processing')->update(['status' => 'in_progress']);
        $this->assertTrue($this->service->canStartStage($loan->fresh(), 'original_document_verification'));
    }
}
