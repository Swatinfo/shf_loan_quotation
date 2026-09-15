<?php

namespace Tests\Feature;

use App\Models\LoanDetail;
use App\Models\LoanProgress;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Stage;
use App\Models\StageAssignment;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Permission gates (additive) for the Legal Verification "waive" and Original
 * Document Verification "seen original" actions. Permission-holders can act
 * regardless of assignee and at any in_progress phase; the existing
 * owner/BM/BDH authority is unchanged.
 */
class LegalOdvPermissionTest extends TestCase
{
    use RefreshDatabase;

    /** Parallel sub-stages to seed so the completion cascade runs cleanly. */
    private const SUBS = [
        'app_number', 'bsm_osv', 'legal_verification',
        'original_document_verification', 'technical_valuation', 'sanction_decision',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStages();

        $roles = [];
        foreach (['super_admin', 'admin', 'branch_manager', 'bdh', 'loan_advisor', 'office_employee'] as $s) {
            $roles[$s] = Role::firstOrCreate(['slug' => $s], ['name' => ucwords(str_replace('_', ' ', $s))]);
        }

        // The stage-action routes are gated by manage_loan_stages; the new perms are
        // the fine-grained authority on top. Seed explicitly (RefreshDatabase runs
        // migrations only, base role_permission seeding isn't guaranteed in tests).
        $mls = Permission::firstOrCreate(['slug' => 'manage_loan_stages'], ['name' => 'Manage Loan Stages', 'group' => 'Loans']);
        $waive = Permission::firstOrCreate(['slug' => 'waive_legal_verification'], ['name' => 'Waive Legal Verification', 'group' => 'Loans']);
        $verify = Permission::firstOrCreate(['slug' => 'verify_original_documents'], ['name' => 'Verify Original Documents', 'group' => 'Loans']);

        // loan_advisor: can reach stage actions AND holds both new permissions.
        $roles['loan_advisor']->permissions()->syncWithoutDetaching([$mls->id, $waive->id, $verify->id]);
        // office_employee: can reach stage actions but has NEITHER new permission
        // (proves the 403 comes from the waive authority check, not the middleware).
        $roles['office_employee']->permissions()->syncWithoutDetaching([$mls->id]);

        app(PermissionService::class)->clearAllCaches();
    }

    private function seedStages(): void
    {
        Stage::firstOrCreate(['stage_key' => 'parallel_processing'], [
            'stage_name_en' => 'Parallel Processing', 'stage_name_gu' => 'PP',
            'sequence_order' => 4, 'is_parallel' => true, 'parent_stage_key' => null,
            'stage_type' => 'parallel', 'assigned_role' => 'task_owner', 'is_enabled' => true,
        ]);
        foreach (self::SUBS as $i => $key) {
            Stage::firstOrCreate(['stage_key' => $key], [
                'stage_name_en' => ucwords(str_replace('_', ' ', $key)), 'stage_name_gu' => $key,
                'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing',
                'stage_type' => 'sequential', 'assigned_role' => 'task_owner', 'is_enabled' => true,
            ]);
        }
    }

    private function user(string $role): User
    {
        $u = User::create([
            'name' => 'U-'.uniqid(), 'email' => uniqid().'@t.local',
            'password' => bcrypt('x'), 'is_active' => true,
        ]);
        $u->roles()->sync(Role::where('slug', $role)->pluck('id'));

        return $u->fresh('roles');
    }

    /**
     * Loan sitting in parallel processing with legal in_progress. $legalPhase
     * lets us place legal past phase 1.
     */
    private function makeLoan(User $creator, string $legalPhase = '1'): LoanDetail
    {
        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(), 'customer_name' => 'C', 'customer_type' => 'salaried',
            'loan_amount' => 500000, 'status' => 'active', 'current_stage' => 'parallel_processing',
            'created_by' => $creator->id, 'assigned_advisor' => $creator->id,
        ]);

        $sa = function (string $key, string $status, ?array $notes = null) use ($loan) {
            StageAssignment::create([
                'loan_id' => $loan->id, 'stage_key' => $key, 'parent_stage_key' => 'parallel_processing',
                'status' => $status, 'is_parallel_stage' => true,
                'notes' => $notes ? json_encode($notes) : null,
            ]);
        };

        LoanProgress::create(['loan_id' => $loan->id, 'total_stages' => 10, 'completed_stages' => 0]);

        StageAssignment::create([
            'loan_id' => $loan->id, 'stage_key' => 'parallel_processing', 'parent_stage_key' => null,
            'status' => 'in_progress', 'is_parallel_stage' => false,
        ]);
        $sa('app_number', 'completed');
        $sa('bsm_osv', 'completed');
        $sa('legal_verification', 'in_progress', ['legal_phase' => $legalPhase]);
        $sa('original_document_verification', 'pending');
        // Keep two subs open so the parallel block never completes (no advance to rate_pf).
        $sa('technical_valuation', 'in_progress');
        $sa('sanction_decision', 'in_progress');

        return $loan->fresh('stageAssignments');
    }

    private function legalStatus(LoanDetail $loan): string
    {
        return $loan->fresh()->stageAssignments->firstWhere('stage_key', 'legal_verification')->status;
    }

    // ── Legal waive ──

    public function test_permission_holder_can_waive_legal_when_not_owner(): void
    {
        $owner = $this->user('loan_advisor');
        $loan = $this->makeLoan($owner);
        // A different loan_advisor: has waive_legal_verification (via migration) but is
        // not this loan's creator/advisor.
        $actor = $this->user('loan_advisor');

        $this->actingAs($actor)
            ->postJson(route('loans.stages.legal-action', $loan), ['action' => 'complete_skip_bank'])
            ->assertOk();

        $this->assertSame('completed', $this->legalStatus($loan));
    }

    public function test_user_without_waive_permission_is_forbidden(): void
    {
        $owner = $this->user('loan_advisor');
        $loan = $this->makeLoan($owner);
        // office_employee has manage_loan_stages (passes the route) but NOT
        // waive_legal_verification, and isn't owner/BM/BDH → 403.
        $actor = $this->user('office_employee');

        $this->actingAs($actor)
            ->postJson(route('loans.stages.legal-action', $loan), ['action' => 'complete_skip_bank'])
            ->assertForbidden();

        $this->assertSame('in_progress', $this->legalStatus($loan));
    }

    public function test_waive_works_past_phase_one(): void
    {
        $owner = $this->user('loan_advisor');
        $loan = $this->makeLoan($owner, legalPhase: '2'); // already sent to bank
        $actor = $this->user('loan_advisor'); // permission-holder

        $this->actingAs($actor)
            ->postJson(route('loans.stages.legal-action', $loan), ['action' => 'complete_skip_bank'])
            ->assertOk();

        $this->assertSame('completed', $this->legalStatus($loan));
    }

    public function test_loan_owner_can_still_waive(): void
    {
        $owner = $this->user('loan_advisor');
        $loan = $this->makeLoan($owner);

        $this->actingAs($owner)
            ->postJson(route('loans.stages.legal-action', $loan), ['action' => 'complete_skip_bank'])
            ->assertOk();

        $this->assertSame('completed', $this->legalStatus($loan));
    }

    // ── Original Document Verification ──

    public function test_permission_holder_can_verify_originals_when_not_assignee(): void
    {
        $owner = $this->user('loan_advisor');
        $loan = $this->makeLoan($owner);
        // Move ODV to in_progress, assigned to someone else.
        $assignee = $this->user('loan_advisor');
        $odv = $loan->stageAssignments->firstWhere('stage_key', 'original_document_verification');
        $odv->update(['status' => 'in_progress', 'assigned_to' => $assignee->id]);

        // A different loan_advisor holds verify_original_documents (via migration).
        $actor = $this->user('loan_advisor');

        $this->actingAs($actor)
            ->postJson(route('loans.stages.notes', [$loan, 'original_document_verification']), [
                'notes_data' => ['verification_date' => '31/08/2026'],
            ])
            ->assertOk();

        $this->assertSame(
            'completed',
            $loan->fresh()->stageAssignments->firstWhere('stage_key', 'original_document_verification')->status
        );
    }
}
