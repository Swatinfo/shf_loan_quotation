<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Role;
use App\Models\StageAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Loan Report (report_plan.md Part 1):
 *   - Role-gated (no permission slug): super_admin/admin/bdh/branch_manager;
 *     everyone else 403 on BOTH endpoints.
 *   - BDH sees ALL branches (unlike turnaround); BM stays branch-scoped even
 *     when a foreign branch_id is forged into the request.
 *   - Status select: sanctioned -> sanctioned_amount NOT NULL;
 *     disbursed -> disbursed_amount NOT NULL. Totals sum the filtered set.
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

    public function test_access_matrix_on_both_endpoints(): void
    {
        foreach (['super_admin', 'admin', 'bdh', 'branch_manager'] as $slug) {
            $user = $this->makeUser($slug);
            $this->actingAs($user)->get(route('reports.loans'))->assertOk();
            $this->actingAs($user)->getJson(route('reports.loans.data'))->assertOk();
        }

        foreach (['loan_advisor', 'bank_employee', 'office_employee'] as $slug) {
            $user = $this->makeUser($slug);
            $this->actingAs($user)->get(route('reports.loans'))->assertForbidden();
            $this->actingAs($user)->getJson(route('reports.loans.data'))->assertForbidden();
        }
    }

    public function test_status_filter_splits_sanctioned_and_disbursed(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $sanctioned = $this->makeLoan($advisor, ['sanctioned_amount' => 1500000]);
        $disbursed = $this->makeLoan($advisor, ['disbursed_amount' => 900000]);

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

    public function test_default_status_is_sanctioned_and_totals_sum_the_filtered_set(): void
    {
        $admin = $this->makeUser('admin');
        $advisor = $this->makeUser('loan_advisor');
        $this->makeLoan($advisor, ['sanctioned_amount' => 1000000]);
        $this->makeLoan($advisor, ['sanctioned_amount' => 2500000, 'disbursed_amount' => 2000000]);
        $this->makeLoan($advisor, ['disbursed_amount' => 700000]); // excluded by default status

        $response = $this->actingAs($admin)->getJson(route('reports.loans.data'))->assertOk();

        $this->assertSame(2, $response->json('totals.count'));
        $this->assertStringContainsString('35,00,000', $response->json('totals.sanctioned'));
        $this->assertStringContainsString('20,00,000', $response->json('totals.disbursed'));
    }

    public function test_bdh_sees_all_branches(): void
    {
        $bdh = $this->makeUser('bdh');
        $advisor = $this->makeUser('loan_advisor');
        $a = $this->makeLoan($advisor, ['sanctioned_amount' => 100000]);
        $b = $this->makeLoan($advisor, ['sanctioned_amount' => 200000]);
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
}
