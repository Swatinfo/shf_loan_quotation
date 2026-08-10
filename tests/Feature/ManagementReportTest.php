<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Role;
use App\Models\Stage;
use App\Models\StageAssignment;
use App\Models\StageQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Management Summary report (report_plan.md Phase 2B):
 * funnel math, monthly bucketing, scoreboard aggregation, exception
 * thresholds, and branch-manager scoping.
 */
class ManagementReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'admin', 'branch_manager', 'bdh', 'loan_advisor', 'bank_employee', 'office_employee'] as $slug) {
            Role::firstOrCreate(['slug' => $slug], ['name' => ucwords(str_replace('_', ' ', $slug))]);
        }
        // stale_stages inner-joins the stages table — seed the keys used here.
        foreach (['legal_verification', 'sanction', 'disbursement'] as $i => $key) {
            Stage::firstOrCreate(['stage_key' => $key], [
                'stage_name_en' => ucwords(str_replace('_', ' ', $key)),
                'sequence_order' => $i + 1,
                'is_parallel' => false,
                'parent_stage_key' => null,
                'stage_type' => 'sequential',
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

    private function makeBranch(): Branch
    {
        return Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
    }

    private function makeLoan(User $advisor, Branch $branch, array $attrs = []): LoanDetail
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);

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

    private function completeStage(LoanDetail $loan, string $stageKey, string $startedAt, string $completedAt): StageAssignment
    {
        return StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => $stageKey,
            'assigned_to' => $loan->assigned_advisor,
            'status' => 'completed',
            'is_parallel_stage' => false,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ]);
    }

    /** One disbursement tranche — "disbursed" is defined by these rows. */
    private function addDisbursementEntry(LoanDetail $loan, string $date, int $amount): void
    {
        $detailId = DB::table('disbursement_details')->where('loan_id', $loan->id)->value('id')
            ?? DB::table('disbursement_details')->insertGetId([
                'loan_id' => $loan->id, 'disbursement_type' => 'fund_transfer',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        DB::table('disbursement_entries')->insert([
            'loan_id' => $loan->id, 'disbursement_detail_id' => $detailId,
            'disbursement_date' => $date, 'method' => 'fund_transfer', 'amount' => $amount,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_management_denied_for_branch_manager_and_below(): void
    {
        // Management Summary is restricted to super_admin / admin / bdh.
        foreach (['branch_manager', 'loan_advisor', 'bank_employee', 'office_employee'] as $slug) {
            $user = $this->makeUser($slug);
            $this->actingAs($user)->get(route('reports.management'))->assertForbidden();
            $this->actingAs($user)->getJson(route('reports.management.data'))->assertForbidden();
        }
    }

    public function test_funnel_counts_amounts_percentages_and_avg_days(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $branch = $this->makeBranch();

        // Midnight-aligned backdates: disbursement dates are DATE-only (per
        // tranche), so exact avg-day assertions need whole-day timestamps.
        $day = fn (int $daysAgo) => now()->startOfDay()->subDays($daysAgo);

        $quoteId = DB::table('quotations')->insertGetId([
            'customer_name' => 'C', 'customer_type' => 'salaried', 'loan_amount' => 1200000,
            'branch_id' => $branch->id, 'user_id' => $advisor->id, 'status' => 'active',
            'created_at' => $day(20), 'updated_at' => $day(20),
        ]);
        $loan = $this->makeLoan($advisor, $branch, [
            'quotation_id' => $quoteId,
            'sanctioned_amount' => 900000,
            'disbursed_amount' => 850000,
        ]);
        DB::table('loan_details')->where('id', $loan->id)->update([
            'created_at' => $day(18), 'updated_at' => $day(18),
        ]);
        $this->completeStage($loan, 'sanction', $day(17)->toDateTimeString(), $day(13)->toDateTimeString());
        // Partial disbursement: tranches only, disbursement stage NOT completed.
        $this->addDisbursementEntry($loan, $day(10)->toDateString(), 600000);
        $this->addDisbursementEntry($loan, $day(8)->toDateString(), 250000);

        $funnel = $this->actingAs($admin)
            ->getJson(route('reports.management.data'))
            ->assertOk()->json('funnel');

        $this->assertSame(1, $funnel['quotations']['count']);
        $this->assertSame(1, $funnel['converted']['count']);
        $this->assertEquals(100, $funnel['converted']['pct']);
        $this->assertEquals(2, $funnel['converted']['avg_days']);   // quote -> loan
        $this->assertSame(1, $funnel['sanctioned']['count']);
        $this->assertEquals(5, $funnel['sanctioned']['avg_days']);  // loan -> sanction done
        $this->assertSame(1, $funnel['disbursed']['count']);        // partial counts, once
        $this->assertEquals(3, $funnel['disbursed']['avg_days']);   // sanction -> first tranche
        $this->assertStringContainsString('9,00,000', $funnel['sanctioned']['amount']);
        $this->assertStringContainsString('8,50,000', $funnel['disbursed']['amount']); // tranche sum
    }

    public function test_trend_buckets_current_month(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $loan = $this->makeLoan($advisor, $this->makeBranch());
        // Two tranches this month (stage not completed) — one disbursed loan,
        // amounts summed per tranche.
        $this->addDisbursementEntry($loan, now()->toDateString(), 300000);
        $this->addDisbursementEntry($loan, now()->toDateString(), 100000);

        $trend = $this->actingAs($admin)
            ->getJson(route('reports.management.data'))
            ->assertOk()->json('trend');

        $this->assertCount(12, $trend);
        $current = end($trend);
        $this->assertSame(now()->format('M Y'), $current['month']);
        $this->assertSame(1, $current['created']['count']);
        $this->assertSame(1, $current['disbursed']['count']);
        $this->assertSame(400000, $current['disbursed']['amount_raw']);
    }

    public function test_scoreboard_groups_by_branch_with_rejection_pct_and_advisors(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $branch = $this->makeBranch();
        $completed = $this->makeLoan($advisor, $branch, ['status' => 'completed', 'disbursed_amount' => 500000]);
        $this->makeLoan($advisor, $branch, ['status' => 'rejected']);
        $this->completeStage($completed, 'disbursement', now()->subDays(9)->toDateTimeString(), now()->subDays(2)->toDateTimeString());
        DB::table('loan_details')->where('id', $completed->id)->update(['created_at' => now()->subDays(12)]);

        $score = collect($this->actingAs($admin)
            ->getJson(route('reports.management.data'))
            ->assertOk()->json('scoreboard'));

        $row = $score->firstWhere('branch_name', $branch->name);
        $this->assertSame(2, $row['created']);
        $this->assertSame(1, $row['completed']);
        $this->assertEquals(50, $row['rejection_pct']);
        $this->assertEquals(10, $row['avg_tat_days']); // created -12d, done -2d
        $this->assertSame($advisor->name, $row['advisors'][0]['advisor_name']);
        $this->assertSame(2, $row['advisors'][0]['created']);
    }

    public function test_exceptions_lists_respect_thresholds(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $branch = $this->makeBranch();

        $stuckLoan = $this->makeLoan($advisor, $branch);
        StageAssignment::create([
            'loan_id' => $stuckLoan->id, 'stage_key' => 'legal_verification',
            'assigned_to' => $advisor->id, 'status' => 'in_progress',
            'is_parallel_stage' => true, 'started_at' => now()->subDays(20),
        ]);
        $freshLoan = $this->makeLoan($advisor, $branch);
        $freshAssignment = StageAssignment::create([
            'loan_id' => $freshLoan->id, 'stage_key' => 'legal_verification',
            'assigned_to' => $advisor->id, 'status' => 'in_progress',
            'is_parallel_stage' => true, 'started_at' => now()->subDays(2),
        ]);
        $staleQuery = StageQuery::create([
            'stage_assignment_id' => $freshAssignment->id, 'loan_id' => $freshLoan->id,
            'stage_key' => 'legal_verification', 'query_text' => 'Old query',
            'raised_by' => $advisor->id, 'status' => 'pending',
        ]);
        DB::table('stage_queries')->where('id', $staleQuery->id)->update(['created_at' => now()->subDays(10)]);
        $heldLoan = $this->makeLoan($advisor, $branch, [
            'status' => 'on_hold', 'status_reason' => 'Docs pending',
            'status_changed_at' => now()->subDays(40),
        ]);

        $ex = $this->actingAs($admin)
            ->getJson(route('reports.management.data'))
            ->assertOk()->json('exceptions');

        $this->assertSame($stuckLoan->loan_number, $ex['stale_stages'][0]['loan_number']);
        $this->assertCount(1, $ex['stale_stages']); // the 2-day one is below threshold
        $this->assertSame($freshLoan->loan_number, $ex['stale_queries'][0]['loan_number']);
        $this->assertSame($heldLoan->loan_number, $ex['stale_holds'][0]['loan_number']);
        $this->assertSame('Docs pending', $ex['stale_holds'][0]['reason']);
    }

    public function test_admin_sees_all_branches_and_can_filter_to_one(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch();
        $this->makeLoan($advisor, $branchA);
        $this->makeLoan($advisor, $branchB);

        // Full scope by default — both branches counted.
        $resp = $this->actingAs($admin)->getJson(route('reports.management.data'))->assertOk();
        $this->assertSame(2, $resp->json('funnel.converted.count'));
        $this->assertCount(2, $resp->json('scoreboard'));

        // The branch filter narrows to a single branch.
        $resp = $this->actingAs($admin)
            ->getJson(route('reports.management.data', ['branch_id' => $branchA->id]))
            ->assertOk();
        $this->assertSame(1, $resp->json('funnel.converted.count'));
        $this->assertCount(1, $resp->json('scoreboard'));
    }
}
