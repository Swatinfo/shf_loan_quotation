<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Role;
use App\Models\Stage;
use App\Models\StageAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Turnaround report fixes (report_plan.md Part 0):
 *   1. Overall TAT derives from the last completed stage's completed_at —
 *      NOT ld.updated_at, which bumps on any later touch.
 *   2. Advisor attribution falls back to created_by when advisor is null.
 *   3. Stage filter dropdown includes sub-stages; stage-tab user filter
 *      matches the stage assignee, not the loan advisor.
 *   4. Branch-manager scope unchanged (branch-limited).
 */
class TurnaroundReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'admin', 'branch_manager', 'bdh', 'loan_advisor'] as $slug) {
            Role::firstOrCreate(['slug' => $slug], ['name' => ucwords(str_replace('_', ' ', $slug))]);
        }
        Stage::firstOrCreate(['stage_key' => 'parallel_processing'], [
            'stage_name_en' => 'Parallel Processing', 'sequence_order' => 4,
            'is_parallel' => true, 'parent_stage_key' => null, 'stage_type' => 'parallel', 'is_enabled' => true,
        ]);
        Stage::firstOrCreate(['stage_key' => 'legal_verification'], [
            'stage_name_en' => 'Legal Verification', 'sequence_order' => 5,
            'is_parallel' => true, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'is_enabled' => true,
        ]);
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

    private function makeCompletedLoan(User $advisor, string $createdAt, string $completedAt, array $attrs = []): LoanDetail
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);

        $loan = LoanDetail::create(array_merge([
            'loan_number' => 'L-'.uniqid(),
            'customer_name' => 'Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 1000000,
            'status' => 'completed',
            'current_stage' => 'disbursement',
            'bank_id' => $bank->id,
            'bank_name' => $bank->name,
            'branch_id' => $branch->id,
            'created_by' => $advisor->id,
            'assigned_advisor' => $advisor->id,
        ], $attrs));

        StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'legal_verification',
            'assigned_to' => $advisor->id,
            'status' => 'completed',
            'is_parallel_stage' => true,
            'started_at' => $createdAt,
            'completed_at' => $completedAt,
        ]);

        // Set timestamps via the query builder so no Eloquent hook rewrites them.
        DB::table('loan_details')->where('id', $loan->id)
            ->update(['created_at' => $createdAt, 'updated_at' => $completedAt]);

        return $loan->fresh();
    }

    public function test_overall_tat_uses_stage_completion_not_updated_at(): void
    {
        $admin = $this->makeUser('super_admin');
        $advisor = $this->makeUser('loan_advisor');
        $loan = $this->makeCompletedLoan($advisor, '2026-01-01 10:00:00', '2026-01-09 10:00:00');

        // Simulate a much later touch (edit, backfill, transfer) bumping updated_at.
        DB::table('loan_details')->where('id', $loan->id)->update(['updated_at' => '2026-03-01 10:00:00']);

        $response = $this->actingAs($admin)
            ->getJson(route('reports.turnaround.data', ['tab' => 'overall']))
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('user_name', $advisor->name);
        $this->assertNotNull($row);
        $this->assertSame(8, $row['min_days_raw']);
        $this->assertSame(8, $row['max_days_raw']);
    }

    public function test_loan_without_advisor_is_attributed_to_creator(): void
    {
        $admin = $this->makeUser('super_admin');
        $creator = $this->makeUser('loan_advisor');
        $this->makeCompletedLoan($creator, '2026-01-01 10:00:00', '2026-01-05 10:00:00', ['assigned_advisor' => null]);

        $response = $this->actingAs($admin)
            ->getJson(route('reports.turnaround.data', ['tab' => 'overall']))
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('user_name', $creator->name);
        $this->assertNotNull($row, 'Loan with NULL advisor must appear, attributed to its creator.');
        $this->assertSame(1, $row['total_loans']);
    }

    public function test_stage_dropdown_offers_sub_stages_and_filter_matches_them(): void
    {
        $admin = $this->makeUser('super_admin');
        $advisor = $this->makeUser('loan_advisor');
        $this->makeCompletedLoan($advisor, '2026-01-01 10:00:00', '2026-01-03 10:00:00');

        // Page: sub-stage appears (indented) in the stage filter.
        $this->actingAs($admin)->get(route('reports.turnaround'))
            ->assertOk()
            ->assertSee('— Legal Verification');

        // Data: filtering by the sub-stage key returns its rows.
        $response = $this->actingAs($admin)
            ->getJson(route('reports.turnaround.data', ['tab' => 'stagewise', 'stage_key' => 'legal_verification']))
            ->assertOk();

        $rows = collect($response->json('data'));
        $this->assertTrue($rows->isNotEmpty());
        $this->assertSame('legal_verification', $rows->first()['stage_key']);
    }

    public function test_stage_tab_user_filter_matches_stage_assignee(): void
    {
        $admin = $this->makeUser('super_admin');
        $advisor = $this->makeUser('loan_advisor');
        $other = $this->makeUser('loan_advisor');
        // Loan advised by $advisor but the stage was handled by $other.
        $loan = $this->makeCompletedLoan($advisor, '2026-01-01 10:00:00', '2026-01-03 10:00:00');
        StageAssignment::where('loan_id', $loan->id)->update(['assigned_to' => $other->id]);

        $rows = collect($this->actingAs($admin)
            ->getJson(route('reports.turnaround.data', ['tab' => 'stagewise', 'user_id' => $other->id]))
            ->assertOk()->json('data'));
        $this->assertNotNull($rows->firstWhere('user_name', $other->name));

        // Filtering by the advisor (who handled no stage) returns nothing.
        $rows = collect($this->actingAs($admin)
            ->getJson(route('reports.turnaround.data', ['tab' => 'stagewise', 'user_id' => $advisor->id]))
            ->assertOk()->json('data'));
        $this->assertTrue($rows->isEmpty());
    }

    public function test_branch_manager_stays_branch_scoped(): void
    {
        $bm = $this->makeUser('branch_manager');
        $advisor = $this->makeUser('loan_advisor');
        $inLoan = $this->makeCompletedLoan($advisor, '2026-01-01 10:00:00', '2026-01-04 10:00:00');
        $this->makeCompletedLoan($advisor, '2026-01-01 10:00:00', '2026-01-06 10:00:00'); // other branch
        $bm->branches()->attach($inLoan->branch_id);

        $response = $this->actingAs($bm)
            ->getJson(route('reports.turnaround.data', ['tab' => 'overall']))
            ->assertOk();

        $rows = collect($response->json('data'));
        $this->assertSame(1, (int) $rows->sum('total_loans'));
        $this->assertSame(3, $rows->first()['min_days_raw']);
    }
}
