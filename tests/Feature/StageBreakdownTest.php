<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\DisbursementDetail;
use App\Models\DisbursementEntry;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\StageAssignment;
use App\Models\StageQuery;
use App\Models\User;
use App\Services\LoanPipelineBreakdownService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard "Stage status breakdown" service classifies each cohort loan into
 * exactly one bucket per section (precedence), sums the right amount per bucket, and
 * enforces scope / selected-user authorisation server-side.
 */
class StageBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private LoanPipelineBreakdownService $service;

    private Bank $bank;

    private Branch $branch;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['loan_advisor', 'branch_manager', 'bdh', 'super_admin'] as $slug) {
            Role::firstOrCreate(['slug' => $slug], ['name' => ucwords(str_replace('_', ' ', $slug))]);
        }
        $this->service = app(LoanPipelineBreakdownService::class);
        $this->bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $this->branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $this->product = Product::create(['name' => 'Product-'.uniqid(), 'bank_id' => $this->bank->id, 'is_active' => true]);
    }

    private function makeUser(string $slug = 'loan_advisor'): User
    {
        $user = User::create([
            'name' => 'U'.uniqid(),
            'email' => uniqid().'@test',
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);
        $user->roles()->sync(Role::where('slug', $slug)->pluck('id'));

        return $user->fresh('roles');
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function makeLoan(User $owner, array $overrides = []): LoanDetail
    {
        return LoanDetail::create(array_merge([
            'loan_number' => 'L-'.uniqid(),
            'customer_name' => 'Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 100000,
            'status' => 'active',
            'current_stage' => 'inquiry',
            'bank_id' => $this->bank->id,
            'branch_id' => $this->branch->id,
            'product_id' => $this->product->id,
            'created_by' => $owner->id,
            'assigned_advisor' => $owner->id,
        ], $overrides));
    }

    private function assign(LoanDetail $loan, string $stageKey, string $status, ?string $started = null, ?string $completed = null): void
    {
        $attrs = [
            'loan_id' => $loan->id,
            'stage_key' => $stageKey,
            'assigned_to' => $loan->created_by,
            'status' => $status,
            'is_parallel_stage' => false,
        ];
        // Mirror real data: reached stages carry started_at; completed/rejected carry completed_at.
        if (in_array($status, ['in_progress', 'completed', 'rejected'], true)) {
            $attrs['started_at'] = $started ?? now();
        }
        if (in_array($status, ['completed', 'rejected'], true)) {
            $attrs['completed_at'] = $completed ?? now();
        }
        StageAssignment::create($attrs);
    }

    private function query(LoanDetail $loan, string $stageKey, string $status): void
    {
        $assignment = StageAssignment::where('loan_id', $loan->id)->where('stage_key', $stageKey)->first()
            ?? StageAssignment::create([
                'loan_id' => $loan->id,
                'stage_key' => $stageKey,
                'assigned_to' => $loan->created_by,
                'status' => 'in_progress',
                'is_parallel_stage' => false,
            ]);

        StageQuery::create([
            'stage_assignment_id' => $assignment->id,
            'loan_id' => $loan->id,
            'stage_key' => $stageKey,
            'query_text' => 'Q',
            'raised_by' => $loan->created_by,
            'status' => $status,
        ]);
    }

    private function entry(LoanDetail $loan, int $amount, ?string $date = null): void
    {
        $date = $date ?? now()->toDateString();
        // One disbursement_details row per loan (unique loan_id); many tranche entries.
        $detail = DisbursementDetail::firstOrCreate(
            ['loan_id' => $loan->id],
            ['disbursement_type' => 'fund_transfer', 'disbursement_date' => $date, 'amount_disbursed' => 0],
        );
        DisbursementEntry::create([
            'loan_id' => $loan->id,
            'disbursement_detail_id' => $detail->id,
            'amount' => $amount,
            'disbursement_date' => $date,
            'is_active' => true,
        ]);
    }

    /** @return array{count:int,amount:int} */
    private function tile(array $build, string $section, string $bucket): array
    {
        $block = $build['blocks'][0];
        foreach ($block['sections'] as $s) {
            if ($s['key'] !== $section) {
                continue;
            }
            foreach ($s['buckets'] as $b) {
                if ($b['key'] === $bucket) {
                    return ['count' => $b['count'], 'amount' => $b['amount']];
                }
            }
        }
        $this->fail("bucket $section/$bucket not found");
    }

    public function test_technical_buckets_are_mutually_exclusive_with_query_rule(): void
    {
        $owner = $this->makeUser();

        // Not Initiated only counts while the loan is actually in the parallel phase.
        $this->assign($this->makeLoan($owner, ['loan_amount' => 400000, 'current_stage' => 'parallel_processing']), 'technical_valuation', 'pending');
        $this->assign($this->makeLoan($owner, ['loan_amount' => 100000]), 'technical_valuation', 'in_progress'); // under_process

        $l3 = $this->makeLoan($owner, ['loan_amount' => 300000]);
        $this->assign($l3, 'technical_valuation', 'completed');
        $this->query($l3, 'technical_valuation', StageQuery::STATUS_RESOLVED); // completed beats a (resolved) query

        $l4 = $this->makeLoan($owner, ['loan_amount' => 200000]);
        $this->assign($l4, 'technical_valuation', 'in_progress');
        $this->query($l4, 'technical_valuation', StageQuery::STATUS_PENDING); // unresolved query → query bucket

        $this->assign($this->makeLoan($owner, ['loan_amount' => 500000]), 'technical_valuation', 'rejected'); // rejected

        $b = $this->service->build($owner);

        $this->assertSame(['count' => 1, 'amount' => 400000], $this->tile($b, 'technical', 'not_initiated'));
        $this->assertSame(['count' => 1, 'amount' => 100000], $this->tile($b, 'technical', 'under_process'));
        $this->assertSame(['count' => 1, 'amount' => 300000], $this->tile($b, 'technical', 'completed'));
        $this->assertSame(['count' => 1, 'amount' => 200000], $this->tile($b, 'technical', 'query'));
        $this->assertSame(['count' => 1, 'amount' => 500000], $this->tile($b, 'technical', 'rejected'));
    }

    public function test_disbursement_bucket_amounts_use_sanctioned_then_disbursed(): void
    {
        $owner = $this->makeUser();

        // Spill: loan sitting AT docket (in_progress) → sanctioned_amount
        $this->assign($this->makeLoan($owner, ['sanctioned_amount' => 1000000, 'current_stage' => 'docket']), 'docket', 'in_progress');

        // Logged in: docket completed, no entries → sanctioned_amount
        $this->assign($this->makeLoan($owner, ['sanctioned_amount' => 2000000]), 'docket', 'completed');

        // Cheque/transfer entry: has an entry (docket completed) → disbursed (entry sum)
        $l3 = $this->makeLoan($owner, ['sanctioned_amount' => 9999999]);
        $this->assign($l3, 'docket', 'completed');
        $this->entry($l3, 750000);

        // OTC: otc_clearance completed → disbursed (entry sum), beats entry bucket
        $l4 = $this->makeLoan($owner, ['sanctioned_amount' => 9999999]);
        $this->assign($l4, 'docket', 'completed');
        $this->assign($l4, 'otc_clearance', 'completed');
        $this->entry($l4, 500000);

        $b = $this->service->build($owner);

        $this->assertSame(['count' => 1, 'amount' => 1000000], $this->tile($b, 'disbursement', 'spill'));
        $this->assertSame(['count' => 1, 'amount' => 2000000], $this->tile($b, 'disbursement', 'logged_in'));
        $this->assertSame(['count' => 1, 'amount' => 750000], $this->tile($b, 'disbursement', 'entry'));
        $this->assertSame(['count' => 1, 'amount' => 500000], $this->tile($b, 'disbursement', 'otc'));
    }

    public function test_sanction_section_covers_loan_level_and_stage_states(): void
    {
        $owner = $this->makeUser();

        $this->assign($this->makeLoan($owner), 'sanction_decision', 'in_progress'); // sip
        $this->makeLoan($owner, ['status' => 'on_hold']);                            // hold
        $this->makeLoan($owner, ['status' => 'cancelled']);                          // withdrawn
        $this->assign($this->makeLoan($owner), 'sanction_decision', 'completed');    // sanctioned (not disbursed)

        $l5 = $this->makeLoan($owner);
        $this->assign($l5, 'sanction_decision', 'in_progress');
        $this->query($l5, 'sanction_decision', StageQuery::STATUS_PENDING);          // query beats sip

        $b = $this->service->build($owner);

        $this->assertSame(1, $this->tile($b, 'sanction', 'sip')['count']);
        $this->assertSame(1, $this->tile($b, 'sanction', 'hold')['count']);
        $this->assertSame(1, $this->tile($b, 'sanction', 'withdrawn')['count']);
        $this->assertSame(1, $this->tile($b, 'sanction', 'sanctioned')['count']);
        $this->assertSame(1, $this->tile($b, 'sanction', 'query')['count']);
    }

    public function test_parallel_processing_loan_is_not_counted_in_disbursement(): void
    {
        $owner = $this->makeUser();
        // Real shape: in parallel_processing, sanction decided, tech+legal ongoing,
        // and pre-created placeholder rows (pending) for docket/disbursement/otc.
        $loan = $this->makeLoan($owner, ['current_stage' => 'parallel_processing', 'sanctioned_amount' => 500000]);
        $this->assign($loan, 'sanction_decision', 'completed');
        $this->assign($loan, 'technical_valuation', 'in_progress');
        $this->assign($loan, 'legal_verification', 'in_progress');
        $this->assign($loan, 'docket', 'pending');
        $this->assign($loan, 'disbursement', 'pending');
        $this->assign($loan, 'otc_clearance', 'pending');

        $b = $this->service->build($owner);

        // Appears in the concurrent parallel sub-stages…
        $this->assertSame(1, $this->tile($b, 'sanction', 'sanctioned')['count']);
        $this->assertSame(1, $this->tile($b, 'technical', 'under_process')['count']);
        $this->assertSame(1, $this->tile($b, 'legal', 'under_process')['count']);
        // …but NOT in Disbursement (docket placeholder is not "reached").
        $this->assertSame(0, $this->tile($b, 'disbursement', 'spill')['count']);
        $this->assertSame(0, $this->tile($b, 'disbursement', 'logged_in')['count']);
    }

    public function test_pre_parallel_loan_is_not_counted_in_technical_or_legal(): void
    {
        $owner = $this->makeUser();
        // A loan still in document_collection has placeholder pending tech/legal rows.
        $loan = $this->makeLoan($owner, ['current_stage' => 'document_collection']);
        $this->assign($loan, 'technical_valuation', 'pending');
        $this->assign($loan, 'legal_verification', 'pending');

        $b = $this->service->build($owner);

        $this->assertSame(0, $this->tile($b, 'technical', 'not_initiated')['count']);
        $this->assertSame(0, $this->tile($b, 'legal', 'not_initiated')['count']);
    }

    public function test_disbursement_total_tile_equals_entry_plus_otc(): void
    {
        $owner = $this->makeUser();
        // Entry loan (disbursed, not OTC-cleared)
        $e = $this->makeLoan($owner, ['current_stage' => 'disbursement']);
        $this->assign($e, 'docket', 'completed');
        $this->entry($e, 400000);
        // OTC loan (OTC cleared + disbursed)
        $o = $this->makeLoan($owner, ['status' => 'active']);
        $this->assign($o, 'otc_clearance', 'completed');
        $this->entry($o, 600000);

        $b = $this->service->build($owner);
        $entry = $this->tile($b, 'disbursement', 'entry');
        $otc = $this->tile($b, 'disbursement', 'otc');
        $total = $this->tile($b, 'disbursement', 'total');

        $this->assertSame($entry['count'] + $otc['count'], $total['count']);
        $this->assertSame($entry['amount'] + $otc['amount'], $total['amount']);
        $this->assertSame(['count' => 2, 'amount' => 1000000], $total);

        // Derived total must NOT double-count into the section subtotal.
        $section = collect($b['blocks'][0]['sections'])->firstWhere('key', 'disbursement');
        $this->assertSame(2, $section['subtotalCount']);
        $this->assertSame(1000000, $section['subtotalAmount']);
    }

    public function test_spill_amount_falls_back_to_loan_amount_without_sanctioned(): void
    {
        $owner = $this->makeUser();
        // At docket (in_progress), no sanctioned_amount yet → amount falls back to loan_amount.
        $this->assign($this->makeLoan($owner, ['loan_amount' => 650000, 'sanctioned_amount' => null, 'current_stage' => 'docket']), 'docket', 'in_progress');

        $b = $this->service->build($owner);
        $this->assertSame(['count' => 1, 'amount' => 650000], $this->tile($b, 'disbursement', 'spill'));
    }

    public function test_otc_bucket_is_disbursed_money_dated_by_tranche(): void
    {
        $owner = $this->makeUser();

        // Completed (OTC skipped) loan with a tranche in the window → OTC bucket.
        $done = $this->makeLoan($owner, ['status' => 'completed']);
        $this->assign($done, 'otc_clearance', 'skipped');
        $this->entry($done, 800000);

        // OTC-cleared loan with a tranche in the window → OTC bucket.
        $cheque = $this->makeLoan($owner, ['status' => 'active']);
        $this->assign($cheque, 'otc_clearance', 'completed');
        $this->entry($cheque, 200000);

        // A completed loan with NO tranches has no disbursed money → NOT in OTC.
        $this->makeLoan($owner, ['status' => 'completed']);

        $b = $this->service->build($owner);
        $otc = $this->tile($b, 'disbursement', 'otc');
        $this->assertSame(2, $otc['count']);                 // only the two with tranches
        $this->assertSame(1000000, $otc['amount']);          // Σ in-window tranches (800k + 200k)
    }

    public function test_completed_bucket_dates_by_completed_at_not_created_at(): void
    {
        $owner = $this->makeUser();

        // Completed 10 days ago (created long before) → inside a 20-day custom window.
        $inside = $this->makeLoan($owner);
        $this->assign($inside, 'technical_valuation', 'completed', now()->subDays(90)->toDateTimeString(), now()->subDays(10)->toDateTimeString());
        LoanDetail::where('id', $inside->id)->update(['created_at' => now()->subDays(90)]);

        // Completed 40 days ago → outside the window (even though nothing else changed).
        $outside = $this->makeLoan($owner);
        $this->assign($outside, 'technical_valuation', 'completed', now()->subDays(90)->toDateTimeString(), now()->subDays(40)->toDateTimeString());

        $b = $this->service->build($owner, 'custom', null, now()->subDays(20)->toDateString(), now()->toDateString());
        $this->assertSame(1, $this->tile($b, 'technical', 'completed')['count']);
        $this->assertStringContainsString('–', $b['range']['label']); // "d M Y – d M Y"
    }

    /** A 20-day custom window ending today (robust regardless of calendar month). */
    private function window20(User $owner): array
    {
        return $this->service->build($owner, 'custom', null, now()->subDays(20)->toDateString(), now()->toDateString());
    }

    public function test_in_progress_bucket_dates_by_started_at(): void
    {
        $owner = $this->makeUser();
        // started 5 days ago → inside the 20-day window
        $recent = $this->makeLoan($owner);
        $this->assign($recent, 'technical_valuation', 'in_progress', now()->subDays(5)->toDateTimeString());

        // started 40 days ago (created date is irrelevant now) → outside it
        $old = $this->makeLoan($owner);
        $this->assign($old, 'technical_valuation', 'in_progress', now()->subDays(40)->toDateTimeString());

        $this->assertSame(1, $this->tile($this->window20($owner), 'technical', 'under_process')['count']);
        $this->assertSame(2, $this->tile($this->service->build($owner, 'all'), 'technical', 'under_process')['count']);
    }

    public function test_pending_bucket_falls_back_to_loan_created_at(): void
    {
        $owner = $this->makeUser();
        // In the parallel phase, technical pending (no started_at) → dated by created_at.
        $recent = $this->makeLoan($owner, ['current_stage' => 'parallel_processing']);
        $this->assign($recent, 'technical_valuation', 'pending');
        LoanDetail::where('id', $recent->id)->update(['created_at' => now()->subDays(5)]);

        $old = $this->makeLoan($owner, ['current_stage' => 'parallel_processing']);
        $this->assign($old, 'technical_valuation', 'pending');
        LoanDetail::where('id', $old->id)->update(['created_at' => now()->subDays(40)]);

        $this->assertSame(1, $this->tile($this->window20($owner), 'technical', 'not_initiated')['count']);
        $this->assertSame(2, $this->tile($this->service->build($owner, 'all'), 'technical', 'not_initiated')['count']);
    }

    public function test_entry_amount_sums_only_in_window_tranches(): void
    {
        $owner = $this->makeUser();
        $loan = $this->makeLoan($owner, ['current_stage' => 'disbursement']);
        $this->assign($loan, 'docket', 'completed');
        $this->entry($loan, 500000, now()->subDays(5)->toDateString());   // inside the 20-day window
        $this->entry($loan, 300000, now()->subDays(40)->toDateString());  // outside it

        $t = $this->tile($this->window20($owner), 'disbursement', 'entry');
        $this->assertSame(1, $t['count']);
        $this->assertSame(500000, $t['amount']); // only the in-window tranche

        $this->assertSame(800000, $this->tile($this->service->build($owner, 'all'), 'disbursement', 'entry')['amount']);
    }

    public function test_advisor_scope_is_own_only_and_user_filter_ignored(): void
    {
        $advisor = $this->makeUser('loan_advisor');
        $other = $this->makeUser('loan_advisor');

        // A loan owned by someone else, in the advisor's branch — advisor must NOT see it.
        $foreign = $this->makeLoan($other);
        $this->assign($foreign, 'technical_valuation', 'in_progress');

        // Advisor gets a single 'own' block; passing another user id is ignored.
        $build = $this->service->build($advisor, 'month', $other->id);
        $this->assertCount(1, $build['blocks']);
        $this->assertSame('own', $build['blocks'][0]['scope']);
        $this->assertSame(0, $this->tile($build, 'technical', 'under_process')['count']);

        // Forged 'all' scope in a click-through is downgraded to the advisor's own scope.
        $ids = $this->service->loanIdsFor($advisor, 'all', null, 'month', 'technical', 'under_process');
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_loan_list_deeplink_filters_to_the_exact_bucket(): void
    {
        $admin = $this->makeUser('super_admin'); // bypasses permission + sees all

        $a = $this->makeLoan($admin);
        $this->assign($a, 'technical_valuation', 'in_progress');
        $b = $this->makeLoan($admin);
        $this->assign($b, 'technical_valuation', 'in_progress');
        $c = $this->makeLoan($admin);
        $this->assign($c, 'technical_valuation', 'completed'); // different bucket

        $resp = $this->actingAs($admin)->getJson(route('loans.data', [
            'brk_section' => 'technical',
            'brk_bucket' => 'under_process',
            'brk_scope' => 'all',
            'brk_period' => 'all',
        ]));

        $resp->assertOk();
        $this->assertSame(2, (int) $resp->json('recordsFiltered'));
        $numbers = collect($resp->json('data'))->pluck('loan_number_raw')->all();
        $this->assertContains($a->loan_number, $numbers);
        $this->assertNotContains($c->loan_number, $numbers);
    }

    public function test_branch_manager_sees_own_plus_branch_and_branch_user_filter(): void
    {
        $bm = $this->makeUser('branch_manager');
        $bm->branches()->sync([$this->branch->id]);
        $member = $this->makeUser('loan_advisor');
        $member->branches()->sync([$this->branch->id]);
        $outsider = $this->makeUser('loan_advisor');

        $loan = $this->makeLoan($member);
        $this->assign($loan, 'technical_valuation', 'in_progress');

        // No user filter → two blocks (own + branch); branch block sees the member's loan.
        $build = $this->service->build($bm->fresh('roles'));
        $this->assertCount(2, $build['blocks']);
        $this->assertSame('own', $build['blocks'][0]['scope']);
        $this->assertSame('branch', $build['blocks'][1]['scope']);

        // Picking an outsider (not in branch) is rejected → falls back to the two blocks.
        $rejected = $this->service->build($bm->fresh('roles'), 'month', $outsider->id);
        $this->assertCount(2, $rejected['blocks']);

        // Picking a branch member → single "Selected" block of that user's own data.
        $selected = $this->service->build($bm->fresh('roles'), 'month', $member->id);
        $this->assertCount(1, $selected['blocks']);
        $this->assertStringContainsString('Selected', $selected['blocks'][0]['label']);
    }
}
