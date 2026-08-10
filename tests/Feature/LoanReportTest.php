<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Role;
use App\Models\StageAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Loan Report (report_plan.md Part 1):
 *   - Open to every role via view_reports; data narrowed by reportScope.
 *   - all-scope (super_admin/admin/bdh) sees every branch; BM stays
 *     branch-scoped; advisors/stage-workers see only loans they touched.
 *     A forged branch/user id cannot widen the scope.
 *   - Status select: sanctioned -> completed `sanction` stage; disbursed ->
 *     completed `disbursement` stage (management-funnel semantics — the
 *     amount columns may be NULL until docket phase 2 records them).
 *   - date_from/date_to run on the milestone completion date (sanction or
 *     disbursement stage completed_at per the status toggle) — the same
 *     semantics as the management funnel — NOT on loan created_at.
 *   - Totals are PERIOD totals for BOTH milestones (count + ₹, milestone-
 *     dated, filter/scope aware), independent of the status toggle.
 */
class LoanReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'admin', 'branch_manager', 'bdh', 'loan_advisor', 'bank_employee', 'office_employee'] as $slug) {
            Role::firstOrCreate(['slug' => $slug], ['name' => ucwords(str_replace('_', ' ', $slug))]);
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
            'loan_amount' => 2000000,
            'status' => 'active',
            'current_stage' => 'disbursement',
            'bank_id' => $bank->id,
            'bank_name' => $bank->name,
            'branch_id' => $branch->id,
            'created_by' => $advisor->id,
            'assigned_advisor' => $advisor->id,
        ], $attrs));
    }

    private function completeStage(LoanDetail $loan, string $stageKey, string $completedAt = '2026-03-10 10:00:00'): StageAssignment
    {
        return StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => $stageKey,
            'assigned_to' => $loan->assigned_advisor,
            'status' => 'completed',
            'is_parallel_stage' => false,
            'started_at' => '2026-03-01 10:00:00',
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

    public function test_loan_report_is_available_to_every_role(): void
    {
        // Open to all via view_reports; data is narrowed by reportScope.
        foreach (['super_admin', 'admin', 'bdh', 'branch_manager', 'loan_advisor', 'bank_employee', 'office_employee'] as $slug) {
            $user = $this->makeUser($slug);
            $this->actingAs($user)->get(route('reports.loans'))->assertOk();
            $this->actingAs($user)->getJson(route('reports.loans.data'))->assertOk();
        }
    }

    public function test_advisor_sees_only_own_loans_in_the_report(): void
    {
        $a = $this->makeUser('loan_advisor');
        $b = $this->makeUser('loan_advisor');
        $mine = $this->makeLoan($a, ['sanctioned_amount' => 900000]);
        $theirs = $this->makeLoan($b, ['sanctioned_amount' => 800000]);
        $this->completeStage($mine, 'sanction');
        $this->completeStage($theirs, 'sanction');

        $rows = collect($this->actingAs($a)
            ->getJson(route('reports.loans.data'))
            ->assertOk()->json('data'));

        $this->assertNotNull($rows->firstWhere('loan_number', $mine->loan_number));
        $this->assertNull($rows->firstWhere('loan_number', $theirs->loan_number));
    }

    public function test_stage_worker_sees_only_loans_they_touched_in_the_report(): void
    {
        $office = $this->makeUser('office_employee');
        $advisor = $this->makeUser('loan_advisor');
        $touched = $this->makeLoan($advisor, ['sanctioned_amount' => 500000]);
        $untouched = $this->makeLoan($advisor, ['sanctioned_amount' => 600000]);
        $this->completeStage($untouched, 'sanction');
        StageAssignment::create([
            'loan_id' => $touched->id,
            'stage_key' => 'sanction',
            'assigned_to' => $office->id,
            'status' => 'completed',
            'is_parallel_stage' => false,
            'started_at' => now()->subDays(2),
            'completed_at' => now()->subDay(),
        ]);

        $rows = collect($this->actingAs($office)
            ->getJson(route('reports.loans.data'))
            ->assertOk()->json('data'));

        $this->assertNotNull($rows->firstWhere('loan_number', $touched->loan_number));
        $this->assertNull($rows->firstWhere('loan_number', $untouched->loan_number));
    }

    public function test_forged_branch_id_cannot_widen_own_scope(): void
    {
        $a = $this->makeUser('loan_advisor');
        $b = $this->makeUser('loan_advisor');
        $own = $this->makeLoan($a, ['sanctioned_amount' => 100000]);
        $theirs = $this->makeLoan($b, ['sanctioned_amount' => 200000]);
        $this->completeStage($own, 'sanction');
        $this->completeStage($theirs, 'sanction');

        $resp = $this->actingAs($a)
            ->getJson(route('reports.loans.data', ['branch_id' => $theirs->branch_id]))
            ->assertOk();

        $this->assertNull(collect($resp->json('data'))->firstWhere('loan_number', $theirs->loan_number));
        $this->assertSame(0, $resp->json('totals.count'));
    }

    public function test_status_filter_splits_sanctioned_and_disbursed(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $sanctioned = $this->makeLoan($advisor, ['sanctioned_amount' => 1500000]);
        $disbursed = $this->makeLoan($advisor, ['disbursed_amount' => 900000]);
        $this->completeStage($sanctioned, 'sanction');
        $this->addDisbursementEntry($disbursed, '2026-03-10', 900000);

        $rows = collect($this->actingAs($admin)
            ->getJson(route('reports.loans.data', ['status' => 'sanctioned']))
            ->assertOk()->json('data'));
        $this->assertNotNull($rows->firstWhere('loan_number', $sanctioned->loan_number));
        $this->assertNull($rows->firstWhere('loan_number', $disbursed->loan_number));

        $rows = collect($this->actingAs($admin)
            ->getJson(route('reports.loans.data', ['status' => 'disbursed']))
            ->assertOk()->json('data'));
        $this->assertNotNull($rows->firstWhere('loan_number', $disbursed->loan_number));
        $this->assertNull($rows->firstWhere('loan_number', $sanctioned->loan_number));
    }

    public function test_default_status_is_sanctioned_and_totals_cover_both_milestones(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $a = $this->makeLoan($advisor, ['sanctioned_amount' => 1000000]);
        $b = $this->makeLoan($advisor, ['sanctioned_amount' => 2500000, 'disbursed_amount' => 2000000]);
        $c = $this->makeLoan($advisor, ['disbursed_amount' => 700000]); // not sanctioned — no table row
        $this->completeStage($a, 'sanction');
        $this->completeStage($b, 'sanction');
        $this->addDisbursementEntry($b, '2026-03-12', 2000000);
        $this->addDisbursementEntry($c, '2026-03-15', 700000);

        $response = $this->actingAs($admin)->getJson(route('reports.loans.data'))->assertOk();

        // Default view lists sanctioned loans only…
        $this->assertSame(2, $response->json('totals.count'));
        $this->assertSame(2, $response->json('totals.sanctioned_count'));
        $this->assertStringContainsString('35,00,000', $response->json('totals.sanctioned'));
        // …but the disbursed period total still covers ALL disbursements,
        // including loan C which has no row in the sanctioned view.
        $this->assertSame(2, $response->json('totals.disbursed_count'));
        $this->assertStringContainsString('27,00,000', $response->json('totals.disbursed'));
    }

    public function test_sanctioned_loan_without_amount_is_listed_and_counted(): void
    {
        // Between "sanction letter completed" and "docket phase 2 financials
        // submitted" the amount column is still NULL — the loan must appear
        // (amount "—") and count toward the sanctioned total, matching the
        // management funnel.
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $noAmount = $this->makeLoan($advisor); // sanctioned_amount NULL
        $withAmount = $this->makeLoan($advisor, ['sanctioned_amount' => 800000]);
        $this->completeStage($noAmount, 'sanction');
        $this->completeStage($withAmount, 'sanction');

        $response = $this->actingAs($admin)->getJson(route('reports.loans.data'))->assertOk();
        $rows = collect($response->json('data'));

        $this->assertSame('—', $rows->firstWhere('loan_number', $noAmount->loan_number)['sanctioned_amount']);
        $this->assertSame(2, $response->json('totals.count'));
        $this->assertSame(2, $response->json('totals.sanctioned_count'));
        $this->assertStringContainsString('8,00,000', $response->json('totals.sanctioned'));
    }

    public function test_bdh_sees_all_branches(): void
    {
        $bdh = $this->makeUser('bdh');
        $advisor = $this->makeUser('loan_advisor');
        $a = $this->makeLoan($advisor, ['sanctioned_amount' => 100000]);
        $b = $this->makeLoan($advisor, ['sanctioned_amount' => 200000]);
        $this->completeStage($a, 'sanction');
        $this->completeStage($b, 'sanction');
        $bdh->branches()->attach($a->branch_id); // attached to ONE branch only

        $rows = collect($this->actingAs($bdh)
            ->getJson(route('reports.loans.data'))
            ->assertOk()->json('data'));

        $this->assertNotNull($rows->firstWhere('loan_number', $a->loan_number));
        $this->assertNotNull($rows->firstWhere('loan_number', $b->loan_number));
    }

    public function test_branch_manager_scope_survives_forged_branch_id(): void
    {
        $bm = $this->makeUser('branch_manager');
        $advisor = $this->makeUser('loan_advisor');
        $own = $this->makeLoan($advisor, ['sanctioned_amount' => 100000]);
        $foreign = $this->makeLoan($advisor, ['sanctioned_amount' => 200000]);
        $this->completeStage($own, 'sanction');
        $this->completeStage($foreign, 'sanction');
        $bm->branches()->attach($own->branch_id);

        // No filter: only own-branch loans.
        $rows = collect($this->actingAs($bm)
            ->getJson(route('reports.loans.data'))
            ->assertOk()->json('data'));
        $this->assertNotNull($rows->firstWhere('loan_number', $own->loan_number));
        $this->assertNull($rows->firstWhere('loan_number', $foreign->loan_number));

        // Forged foreign branch_id: still nothing leaks.
        $rows = collect($this->actingAs($bm)
            ->getJson(route('reports.loans.data', ['branch_id' => $foreign->branch_id]))
            ->assertOk()->json('data'));
        $this->assertTrue($rows->isEmpty());
    }

    public function test_user_bank_and_date_filters_narrow_rows(): void
    {
        $admin = $this->makeUser('admin');
        $adv1 = $this->makeUser('loan_advisor');
        $adv2 = $this->makeUser('loan_advisor');
        $mine = $this->makeLoan($adv1, ['sanctioned_amount' => 100000]);
        $theirs = $this->makeLoan($adv2, ['sanctioned_amount' => 200000]);
        $this->completeStage($mine, 'sanction');
        $this->completeStage($theirs, 'sanction');

        $rows = collect($this->actingAs($admin)
            ->getJson(route('reports.loans.data', ['user_id' => $adv1->id]))
            ->assertOk()->json('data'));
        $this->assertNotNull($rows->firstWhere('loan_number', $mine->loan_number));
        $this->assertNull($rows->firstWhere('loan_number', $theirs->loan_number));

        $rows = collect($this->actingAs($admin)
            ->getJson(route('reports.loans.data', ['bank_id' => $theirs->bank_id]))
            ->assertOk()->json('data'));
        $this->assertSame(1, $rows->count());

        $rows = collect($this->actingAs($admin)
            ->getJson(route('reports.loans.data', ['date_from' => '2030-01-01']))
            ->assertOk()->json('data'));
        $this->assertTrue($rows->isEmpty());
    }

    public function test_date_filter_runs_on_sanction_date_not_loan_creation(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');

        // Created in May, sanctioned in July — a July window must include it.
        $sanctionedInJuly = $this->makeLoan($advisor, ['sanctioned_amount' => 100000]);
        DB::table('loan_details')->where('id', $sanctionedInJuly->id)
            ->update(['created_at' => '2026-05-01 10:00:00']);
        StageAssignment::create([
            'loan_id' => $sanctionedInJuly->id,
            'stage_key' => 'sanction',
            'assigned_to' => $advisor->id,
            'status' => 'completed',
            'is_parallel_stage' => false,
            'started_at' => '2026-07-01 10:00:00',
            'completed_at' => '2026-07-05 10:00:00',
        ]);

        // Created in July, sanctioned in June — a July window must exclude it.
        $sanctionedInJune = $this->makeLoan($advisor, ['sanctioned_amount' => 200000]);
        DB::table('loan_details')->where('id', $sanctionedInJune->id)
            ->update(['created_at' => '2026-07-02 10:00:00']);
        StageAssignment::create([
            'loan_id' => $sanctionedInJune->id,
            'stage_key' => 'sanction',
            'assigned_to' => $advisor->id,
            'status' => 'completed',
            'is_parallel_stage' => false,
            'started_at' => '2026-06-10 10:00:00',
            'completed_at' => '2026-06-20 10:00:00',
        ]);

        $rows = collect($this->actingAs($admin)
            ->getJson(route('reports.loans.data', ['date_from' => '2026-07-01', 'date_to' => '2026-07-31']))
            ->assertOk()->json('data'));

        $this->assertNotNull($rows->firstWhere('loan_number', $sanctionedInJuly->loan_number));
        $this->assertNull($rows->firstWhere('loan_number', $sanctionedInJune->loan_number));
    }

    public function test_disbursed_status_date_filter_uses_disbursement_date(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $loan = $this->makeLoan($advisor, ['sanctioned_amount' => 500000, 'disbursed_amount' => 450000]);
        $this->completeStage($loan, 'sanction', '2026-06-15 10:00:00');
        $this->addDisbursementEntry($loan, '2026-07-10', 450000);

        // Disbursed view, July window: matches the July tranche date.
        $rows = collect($this->actingAs($admin)
            ->getJson(route('reports.loans.data', ['status' => 'disbursed', 'date_from' => '2026-07-01', 'date_to' => '2026-07-31']))
            ->assertOk()->json('data'));
        $this->assertNotNull($rows->firstWhere('loan_number', $loan->loan_number));

        // Sanctioned view, July window: sanction happened in June — excluded.
        $rows = collect($this->actingAs($admin)
            ->getJson(route('reports.loans.data', ['status' => 'sanctioned', 'date_from' => '2026-07-01', 'date_to' => '2026-07-31']))
            ->assertOk()->json('data'));
        $this->assertNull($rows->firstWhere('loan_number', $loan->loan_number));
    }

    public function test_partial_disbursement_counts_and_tranches_split_across_periods(): void
    {
        // A loan with saved tranches but an incomplete disbursement stage IS
        // disbursed; each tranche lands in its own period, and only in-window
        // tranche amounts feed the period total.
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $partial = $this->makeLoan($advisor, ['disbursed_amount' => 500000]);
        $this->addDisbursementEntry($partial, '2026-06-20', 300000); // tranche 1 (June)
        $this->addDisbursementEntry($partial, '2026-07-05', 200000); // tranche 2 (July)

        $response = $this->actingAs($admin)
            ->getJson(route('reports.loans.data', ['status' => 'disbursed', 'date_from' => '2026-07-01', 'date_to' => '2026-07-31']))
            ->assertOk();

        $rows = collect($response->json('data'));
        $row = $rows->firstWhere('loan_number', $partial->loan_number);
        $this->assertNotNull($row);
        $this->assertSame('05/07/2026', $row['disbursed_on']); // latest in-window tranche
        $this->assertSame(1, $response->json('totals.disbursed_count'));
        $this->assertStringContainsString('2,00,000', $response->json('totals.disbursed')); // July tranche only

        // June window: same loan, tranche-1 amount only.
        $response = $this->actingAs($admin)
            ->getJson(route('reports.loans.data', ['status' => 'disbursed', 'date_from' => '2026-06-01', 'date_to' => '2026-06-30']))
            ->assertOk();
        $this->assertSame(1, $response->json('totals.disbursed_count'));
        $this->assertStringContainsString('3,00,000', $response->json('totals.disbursed'));
    }

    public function test_sanctioned_on_comes_from_completed_sanction_stage(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $loan = $this->makeLoan($advisor, ['sanctioned_amount' => 100000]);
        StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'sanction',
            'assigned_to' => $advisor->id,
            'status' => 'completed',
            'is_parallel_stage' => false,
            'started_at' => '2026-02-01 10:00:00',
            'completed_at' => '2026-02-10 10:00:00',
        ]);

        $rows = collect($this->actingAs($admin)
            ->getJson(route('reports.loans.data'))
            ->assertOk()->json('data'));

        $this->assertSame('10/02/2026', $rows->firstWhere('loan_number', $loan->loan_number)['sanctioned_on']);
    }

    public function test_rows_carry_a_link_to_the_loan_stages_page(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $loan = $this->makeLoan($advisor, ['sanctioned_amount' => 100000]);
        $this->completeStage($loan, 'sanction');

        $rows = collect($this->actingAs($admin)
            ->getJson(route('reports.loans.data'))
            ->assertOk()->json('data'));

        $this->assertSame(
            route('loans.stages', $loan->id),
            $rows->firstWhere('loan_number', $loan->loan_number)['stages_url']
        );
    }
}
