<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\StageAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Loan-list (`loans.data`) date-range + stage filter semantics.
 *
 * The date range filters on stage COMPLETION activity, not loan_details.created_at:
 *  - Stage selected  -> loans that COMPLETED that stage (date bounds apply to its completed_at).
 *  - Date range only -> the loan's LATEST stage completion falls in the range.
 */
class LoanStageDateFilterTest extends TestCase
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
            'current_stage' => 'inquiry',
            'bank_id' => $bank->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'created_by' => $owner->id,
            'assigned_advisor' => $owner->id,
        ], $attrs));
    }

    private function completeStage(LoanDetail $loan, string $stageKey, string $completedAt): void
    {
        StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => $stageKey,
            'status' => 'completed',
            'started_at' => $completedAt,
            'completed_at' => $completedAt,
        ]);
    }

    private function loanNumbers($response): string
    {
        return collect($response->json('data'))->pluck('loan_number')->implode(' ');
    }

    /** Loan created in June but its OTC stage completed in July shows for Stage=OTC + July range. */
    public function test_stage_plus_date_matches_stage_completion_date_not_created_at(): void
    {
        $admin = $this->admin();
        $loan = $this->makeLoan($admin, ['created_at' => '2026-06-17 20:00:00']);
        $this->completeStage($loan, 'otc_clearance', '2026-07-23 15:00:00');

        $this->actingAs($admin)
            ->getJson(route('loans.data', [
                'stage' => 'otc_clearance',
                'date_from' => '2026-07-01',
                'date_to' => '2026-07-31',
            ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1);
    }

    /** Same loan is excluded from a June-only range (OTC completed in July). */
    public function test_stage_plus_date_excludes_completion_outside_range(): void
    {
        $admin = $this->admin();
        $loan = $this->makeLoan($admin, ['created_at' => '2026-06-17 20:00:00']);
        $this->completeStage($loan, 'otc_clearance', '2026-07-23 15:00:00');

        $this->actingAs($admin)
            ->getJson(route('loans.data', [
                'stage' => 'otc_clearance',
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
            ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 0);
    }

    /** Stage selected without a date: loans that completed that stage show regardless of when. */
    public function test_stage_only_matches_completed_stage_any_date(): void
    {
        $admin = $this->admin();
        $done = $this->makeLoan($admin);
        $this->completeStage($done, 'otc_clearance', '2026-05-10 10:00:00');
        // A loan currently IN PROGRESS at OTC must NOT match "stage completed".
        $inProgress = $this->makeLoan($admin);
        StageAssignment::create([
            'loan_id' => $inProgress->id,
            'stage_key' => 'otc_clearance',
            'status' => 'in_progress',
            'started_at' => '2026-05-10 10:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('loans.data', ['stage' => 'otc_clearance']))
            ->assertOk();

        $response->assertJsonPath('recordsFiltered', 1);
        $this->assertStringContainsString($done->loan_number, $this->loanNumbers($response));
        $this->assertStringNotContainsString($inProgress->loan_number, $this->loanNumbers($response));
    }

    /** Date range only → matches on the loan's LATEST stage completion. */
    public function test_date_only_matches_latest_stage_completion(): void
    {
        $admin = $this->admin();

        // Latest completion (docket) is in July → matches July window.
        $july = $this->makeLoan($admin, ['created_at' => '2026-06-01 09:00:00']);
        $this->completeStage($july, 'inquiry', '2026-06-05 09:00:00');
        $this->completeStage($july, 'docket', '2026-07-10 09:00:00');

        // Latest completion (inquiry) is in June → excluded from July window,
        // even though it exists — proves we key on MAX(completed_at), not any row.
        $june = $this->makeLoan($admin, ['created_at' => '2026-06-01 09:00:00']);
        $this->completeStage($june, 'inquiry', '2026-06-20 09:00:00');

        $response = $this->actingAs($admin)
            ->getJson(route('loans.data', [
                'date_from' => '2026-07-01',
                'date_to' => '2026-07-31',
            ]))
            ->assertOk();

        $response->assertJsonPath('recordsFiltered', 1);
        $this->assertStringContainsString($july->loan_number, $this->loanNumbers($response));
        $this->assertStringNotContainsString($june->loan_number, $this->loanNumbers($response));
    }
}
