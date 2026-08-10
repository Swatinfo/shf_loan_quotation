<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Role;
use App\Models\Stage;
use App\Models\StageAssignment;
use App\Models\StageQuery;
use App\Models\StageTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Loan Pipeline report (report_plan.md Phase 2A):
 *   - Open to every role via view_reports; data narrowed by reportScope
 *     (all / branch / own — advisors & stage-workers see only touched loans).
 *   - Active rows carry stage lines: in-progress (owner, days in stage, days
 *     with owner from transfer history, open-query count) plus pending
 *     sub-stages inside an active parallel block (queued days from the parent).
 *   - Pending future MAIN stages never appear.
 *   - Completed rows carry stage-based TAT (never ld.updated_at — re-homed
 *     from the removed TurnaroundReportTest).
 *   - Summary chips, workload tab, BM forged-branch scoping.
 */
class PipelineReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'admin', 'branch_manager', 'bdh', 'loan_advisor', 'bank_employee', 'office_employee'] as $slug) {
            Role::firstOrCreate(['slug' => $slug], ['name' => ucwords(str_replace('_', ' ', $slug))]);
        }
        $stages = [
            ['stage_key' => 'parallel_processing', 'sequence_order' => 4, 'parent' => null, 'type' => 'parallel'],
            ['stage_key' => 'legal_verification', 'sequence_order' => 5, 'parent' => 'parallel_processing', 'type' => 'sequential'],
            ['stage_key' => 'original_document_verification', 'sequence_order' => 6, 'parent' => 'parallel_processing', 'type' => 'sequential'],
            ['stage_key' => 'sanction', 'sequence_order' => 7, 'parent' => null, 'type' => 'sequential'],
        ];
        foreach ($stages as $s) {
            Stage::firstOrCreate(['stage_key' => $s['stage_key']], [
                'stage_name_en' => ucwords(str_replace('_', ' ', $s['stage_key'])),
                'sequence_order' => $s['sequence_order'],
                'is_parallel' => $s['parent'] !== null,
                'parent_stage_key' => $s['parent'],
                'stage_type' => $s['type'],
                'is_enabled' => true,
            ]);
        }
    }

    private function makeUser(string $roleSlug): User
    {
        $user = User::create([
            'name' => 'U'.uniqid(),
            'email' => uniqid().'@test',
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);
        $user->roles()->sync(Role::where('slug', $roleSlug)->pluck('id'));

        return $user->fresh('roles');
    }

    private function makeLoan(User $advisor, array $attrs = []): LoanDetail
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);

        return LoanDetail::create(array_merge([
            'loan_number' => 'L-'.uniqid(),
            'customer_name' => 'Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 1000000,
            'status' => 'active',
            'current_stage' => 'parallel_processing',
            'bank_id' => $bank->id,
            'bank_name' => $bank->name,
            'branch_id' => $branch->id,
            'created_by' => $advisor->id,
            'assigned_advisor' => $advisor->id,
        ], $attrs));
    }

    private function assign(LoanDetail $loan, string $stageKey, string $status, ?int $userId, ?string $startedAt = null, ?string $completedAt = null): StageAssignment
    {
        return StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => $stageKey,
            'assigned_to' => $userId,
            'status' => $status,
            'is_parallel_stage' => $stageKey !== 'sanction',
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ]);
    }

    private function rowFor($response, LoanDetail $loan): ?array
    {
        return collect($response->json('data'))->firstWhere('loan_number', $loan->loan_number);
    }

    public function test_pipeline_is_available_to_every_role(): void
    {
        // Reports (Pipeline + Loan Report) are open to all via view_reports.
        foreach (['super_admin', 'admin', 'bdh', 'branch_manager', 'loan_advisor', 'bank_employee', 'office_employee'] as $slug) {
            $user = $this->makeUser($slug);
            $this->actingAs($user)->get(route('reports.pipeline'))->assertOk();
            $this->actingAs($user)->getJson(route('reports.pipeline.data'))->assertOk();
        }
    }

    public function test_loan_advisor_sees_only_own_loans(): void
    {
        $a = $this->makeUser('loan_advisor');
        $b = $this->makeUser('loan_advisor');
        $mine = $this->makeLoan($a);
        $theirs = $this->makeLoan($b);

        $resp = $this->actingAs($a)->getJson(route('reports.pipeline.data'))->assertOk();

        $this->assertNotNull($this->rowFor($resp, $mine));
        $this->assertNull($this->rowFor($resp, $theirs));
        $this->assertSame(1, $resp->json('summary.all.count'));
    }

    public function test_stage_worker_sees_only_loans_they_touched(): void
    {
        $office = $this->makeUser('office_employee');
        $advisor = $this->makeUser('loan_advisor');
        $touched = $this->makeLoan($advisor);
        $untouched = $this->makeLoan($advisor);
        $this->assign($touched, 'legal_verification', 'in_progress', $office->id, now()->subDays(2));

        $resp = $this->actingAs($office)->getJson(route('reports.pipeline.data'))->assertOk();

        $this->assertNotNull($this->rowFor($resp, $touched));
        $this->assertNull($this->rowFor($resp, $untouched));
        $this->assertSame(1, $resp->json('summary.all.count'));
    }

    public function test_own_scope_cannot_be_widened_by_a_forged_user_id(): void
    {
        $a = $this->makeUser('loan_advisor');
        $b = $this->makeUser('loan_advisor');
        $mine = $this->makeLoan($a);
        $theirs = $this->makeLoan($b);

        // A forges B's id — the scope constraint keeps it within A's own set,
        // so the filter narrows to nothing rather than exposing B's loan.
        $resp = $this->actingAs($a)
            ->getJson(route('reports.pipeline.data', ['user_id' => $b->id]))
            ->assertOk();

        $this->assertNull($this->rowFor($resp, $theirs));
        $this->assertNull($this->rowFor($resp, $mine));
        $this->assertSame(0, $resp->json('summary.all.count'));
    }

    public function test_active_row_shows_in_progress_stage_with_owner_and_days(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $owner = $this->makeUser('office_employee');
        $loan = $this->makeLoan($advisor);
        $this->assign($loan, 'legal_verification', 'in_progress', $owner->id, now()->subDays(5));

        $row = $this->rowFor($this->actingAs($admin)->getJson(route('reports.pipeline.data'))->assertOk(), $loan);

        $this->assertNotNull($row);
        $line = collect($row['stage_lines'])->firstWhere('stage_key', 'legal_verification');
        $this->assertSame('in_progress', $line['kind']);
        $this->assertSame($owner->name, $line['owner']);
        $this->assertSame(5, $line['days_in_stage']);
        $this->assertSame(5, $line['days_with_owner']);
    }

    public function test_days_with_owner_resets_on_transfer(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $first = $this->makeUser('office_employee');
        $second = $this->makeUser('bdh');
        $loan = $this->makeLoan($advisor);
        $assignment = $this->assign($loan, 'legal_verification', 'in_progress', $second->id, now()->subDays(10));
        $transfer = StageTransfer::create([
            'stage_assignment_id' => $assignment->id,
            'loan_id' => $loan->id,
            'stage_key' => 'legal_verification',
            'transferred_from' => $first->id,
            'transferred_to' => $second->id,
            'transfer_type' => 'manual',
        ]);
        // created_at is not fillable — backdate via the query builder.
        \DB::table('stage_transfers')->where('id', $transfer->id)->update(['created_at' => now()->subDays(3)]);

        $row = $this->rowFor($this->actingAs($admin)->getJson(route('reports.pipeline.data'))->assertOk(), $loan);

        $line = collect($row['stage_lines'])->firstWhere('stage_key', 'legal_verification');
        $this->assertSame(10, $line['days_in_stage']);
        $this->assertSame(3, $line['days_with_owner']);
    }

    public function test_pending_sub_stage_in_active_parallel_block_appears_with_queued_days(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $loan = $this->makeLoan($advisor);
        $this->assign($loan, 'parallel_processing', 'in_progress', $advisor->id, now()->subDays(6));
        $this->assign($loan, 'original_document_verification', 'pending', null);
        // Pending MAIN stage must NOT appear.
        $this->assign($loan, 'sanction', 'pending', null);

        $row = $this->rowFor($this->actingAs($admin)->getJson(route('reports.pipeline.data'))->assertOk(), $loan);

        $pending = collect($row['stage_lines'])->firstWhere('stage_key', 'original_document_verification');
        $this->assertNotNull($pending);
        $this->assertSame('pending', $pending['kind']);
        $this->assertSame(6, $pending['queued_days']);
        $this->assertNull(collect($row['stage_lines'])->firstWhere('stage_key', 'sanction'));
        // The parallel container itself must not render as a work line.
        $this->assertNull(collect($row['stage_lines'])->firstWhere('stage_key', 'parallel_processing'));
    }

    public function test_open_query_flag_on_stage_line(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $loan = $this->makeLoan($advisor);
        $assignment = $this->assign($loan, 'legal_verification', 'in_progress', $advisor->id, now()->subDays(2));
        StageQuery::create([
            'stage_assignment_id' => $assignment->id,
            'loan_id' => $loan->id,
            'stage_key' => 'legal_verification',
            'query_text' => 'Blocked',
            'raised_by' => $advisor->id,
            'status' => 'pending',
        ]);

        $row = $this->rowFor($this->actingAs($admin)->getJson(route('reports.pipeline.data'))->assertOk(), $loan);

        $this->assertSame(1, collect($row['stage_lines'])->firstWhere('stage_key', 'legal_verification')['open_queries']);
    }

    public function test_summary_chips_count_all_statuses(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $this->makeLoan($advisor);
        $this->makeLoan($advisor, ['status' => 'completed']);
        $this->makeLoan($advisor, ['status' => 'rejected']);

        $response = $this->actingAs($admin)->getJson(route('reports.pipeline.data'))->assertOk();

        $this->assertSame(1, $response->json('summary.active.count'));
        $this->assertSame(1, $response->json('summary.completed.count'));
        $this->assertSame(1, $response->json('summary.rejected.count'));
        $this->assertSame(3, $response->json('summary.all.count'));
    }

    public function test_completed_view_uses_stage_based_tat_not_updated_at(): void
    {
        // Re-homed from TurnaroundReportTest: updated_at bumps must not
        // inflate the TAT.
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $loan = $this->makeLoan($advisor, ['status' => 'completed']);
        $this->assign($loan, 'legal_verification', 'completed', $advisor->id, now()->subDays(30), now()->subDays(22));
        \DB::table('loan_details')->where('id', $loan->id)->update([
            'created_at' => now()->subDays(30),
            'updated_at' => now(), // touched today — must not become the TAT
        ]);

        $row = $this->rowFor(
            $this->actingAs($admin)->getJson(route('reports.pipeline.data', ['status' => 'completed']))->assertOk(),
            $loan->fresh(),
        );

        $this->assertSame(8, $row['tat_days']);
    }

    public function test_rejected_view_carries_rejection_details(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $loan = $this->makeLoan($advisor, [
            'status' => 'rejected',
            'rejected_stage' => 'legal_verification',
            'rejection_reason' => 'Title issue',
            'rejected_at' => '2026-06-01 10:00:00',
            'rejected_by' => $advisor->id,
        ]);

        $row = $this->rowFor(
            $this->actingAs($admin)->getJson(route('reports.pipeline.data', ['status' => 'rejected']))->assertOk(),
            $loan,
        );

        $this->assertSame('legal_verification', $row['rejected_stage']);
        $this->assertSame('Title issue', $row['rejection_reason']);
        $this->assertSame($advisor->name, $row['rejected_by']);
        $this->assertSame('01/06/2026', $row['rejected_at']);
    }

    public function test_branch_manager_scope_survives_forged_branch_id(): void
    {
        $bm = $this->makeUser('branch_manager');
        $advisor = $this->makeUser('loan_advisor');
        $own = $this->makeLoan($advisor);
        $foreign = $this->makeLoan($advisor);
        $bm->branches()->attach($own->branch_id);

        // No filter: only the own-branch loan is visible.
        $response = $this->actingAs($bm)->getJson(route('reports.pipeline.data'))->assertOk();
        $this->assertNotNull($this->rowFor($response, $own));
        $this->assertNull($this->rowFor($response, $foreign));
        $this->assertSame(1, $response->json('summary.all.count'));

        // Forged foreign branch_id intersects with the scope to nothing.
        $response = $this->actingAs($bm)
            ->getJson(route('reports.pipeline.data', ['branch_id' => $foreign->branch_id]))
            ->assertOk();
        $this->assertNull($this->rowFor($response, $foreign));
        $this->assertSame(0, $response->json('summary.all.count'));
    }

    public function test_workload_tab_groups_in_progress_stages_by_holder(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $holder = $this->makeUser('office_employee');
        $loanA = $this->makeLoan($advisor);
        $loanB = $this->makeLoan($advisor);
        $this->assign($loanA, 'legal_verification', 'in_progress', $holder->id, now()->subDays(9));
        $this->assign($loanB, 'legal_verification', 'in_progress', $holder->id, now()->subDays(3));

        $rows = collect($this->actingAs($admin)
            ->getJson(route('reports.pipeline.data', ['tab' => 'workload']))
            ->assertOk()->json('data'));

        $row = $rows->firstWhere('user_name', $holder->name);
        $this->assertSame(2, $row['held']);
        $this->assertSame(9, $row['oldest_days']);
        $this->assertSame(1, $row['stuck']); // only the 9-day one is > 7d
    }

    public function test_stuck_days_filter_narrows_active_rows(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $stuck = $this->makeLoan($advisor);
        $fresh = $this->makeLoan($advisor);
        $this->assign($stuck, 'legal_verification', 'in_progress', $advisor->id, now()->subDays(20));
        $this->assign($fresh, 'legal_verification', 'in_progress', $advisor->id, now()->subDays(1));

        $response = $this->actingAs($admin)
            ->getJson(route('reports.pipeline.data', ['stuck_days' => 10]))
            ->assertOk();

        $this->assertNotNull($this->rowFor($response, $stuck));
        $this->assertNull($this->rowFor($response, $fresh));
    }
}
