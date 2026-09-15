<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stage;
use App\Models\StageAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Dashboard "My Loan Tasks": a loan in parallel_processing collapses into ONE
 * entry combining all active parallel sub-stages + their owners, instead of one
 * row per sub-stage assignment.
 */
class DashboardMyLoanTasksTest extends TestCase
{
    use RefreshDatabase;

    private const SUBS = ['legal_verification', 'technical_valuation', 'sanction_decision'];

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['slug' => 'loan_advisor'], ['name' => 'Loan Advisor']);

        Stage::firstOrCreate(['stage_key' => 'kfs'], [
            'stage_name_en' => 'KFS', 'stage_name_gu' => 'KFS', 'sequence_order' => 8,
            'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'is_enabled' => true,
        ]);
        foreach (self::SUBS as $i => $key) {
            Stage::firstOrCreate(['stage_key' => $key], [
                'stage_name_en' => ucwords(str_replace('_', ' ', $key)), 'stage_name_gu' => $key,
                'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing',
                'stage_type' => 'sequential', 'is_enabled' => true,
            ]);
        }
    }

    private function user(): User
    {
        $u = User::create([
            'name' => 'U-'.uniqid(), 'email' => uniqid().'@t.local',
            'password' => bcrypt('x'), 'is_active' => true,
        ]);
        $u->roles()->sync(Role::where('slug', 'loan_advisor')->pluck('id'));

        return $u->fresh('roles');
    }

    private function loan(User $owner, string $currentStage): LoanDetail
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'Product-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        return LoanDetail::create([
            'loan_number' => 'SHF-'.uniqid(), 'customer_name' => 'Customer', 'customer_type' => 'salaried',
            'loan_amount' => 1000000, 'status' => 'active', 'current_stage' => $currentStage,
            'bank_id' => $bank->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'created_by' => $owner->id, 'assigned_advisor' => $owner->id,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function myTasks(User $user): array
    {
        $method = new ReflectionMethod(DashboardController::class, 'newthemeMyLoanTasks');
        $method->setAccessible(true);

        return $method->invoke(app(DashboardController::class), $user);
    }

    public function test_parallel_loan_collapses_into_one_entry_with_all_subs_and_owners(): void
    {
        $user = $this->user();
        $loan = $this->loan($user, 'parallel_processing');

        // The user is assigned to all three parallel sub-stages, each with a distinct owner.
        $owners = [];
        foreach (self::SUBS as $key) {
            $owner = ($key === 'legal_verification') ? $user : $this->user();
            $owners[$key] = $owner;
            StageAssignment::create([
                'loan_id' => $loan->id, 'stage_key' => $key, 'parent_stage_key' => 'parallel_processing',
                'status' => 'in_progress', 'is_parallel_stage' => true, 'assigned_to' => $owner->id,
            ]);
        }

        $tasks = $this->myTasks($user);

        // Only ONE entry for the loan (not three).
        $this->assertCount(1, $tasks);
        $entry = $tasks[0];
        $this->assertSame('parallel', $entry['type']);
        $this->assertSame($loan->loan_number, $entry['loanNumber']);
        $this->assertCount(3, $entry['subStages']);

        // Each active sub-stage + its owner is present.
        $byKey = collect($entry['subStages'])->keyBy('stageKey');
        foreach (self::SUBS as $key) {
            $this->assertTrue($byKey->has($key), "$key should be listed");
            $this->assertSame($owners[$key]->name, $byKey[$key]['owner']);
        }
    }

    public function test_only_the_users_assigned_parallel_loan_appears_but_shows_all_owners(): void
    {
        $user = $this->user();
        $loan = $this->loan($user, 'parallel_processing');
        $otherOwner = $this->user();

        // User is on legal only; the other two subs belong to someone else.
        StageAssignment::create(['loan_id' => $loan->id, 'stage_key' => 'legal_verification', 'parent_stage_key' => 'parallel_processing', 'status' => 'in_progress', 'is_parallel_stage' => true, 'assigned_to' => $user->id]);
        StageAssignment::create(['loan_id' => $loan->id, 'stage_key' => 'technical_valuation', 'parent_stage_key' => 'parallel_processing', 'status' => 'in_progress', 'is_parallel_stage' => true, 'assigned_to' => $otherOwner->id]);

        $tasks = $this->myTasks($user);

        $this->assertCount(1, $tasks);
        // The combined row shows BOTH sub-stages (all active), including the other owner.
        $this->assertCount(2, $tasks[0]['subStages']);
        $owners = collect($tasks[0]['subStages'])->pluck('owner')->all();
        $this->assertContains($otherOwner->name, $owners);
    }

    public function test_non_parallel_assignment_is_a_single_entry_with_owner_and_app_number(): void
    {
        $user = $this->user();
        $loan = $this->loan($user, 'kfs');
        $loan->update(['application_number' => 'APP-55555']);
        StageAssignment::create(['loan_id' => $loan->id, 'stage_key' => 'kfs', 'status' => 'in_progress', 'assigned_to' => $user->id]);

        $tasks = $this->myTasks($user);

        $this->assertCount(1, $tasks);
        $this->assertSame('single', $tasks[0]['type']);
        $this->assertSame('kfs', $tasks[0]['stageKey']);
        // The stage owner is shown for single (non-parallel) stages too.
        $this->assertSame($user->name, $tasks[0]['owner']);
        // Application number is surfaced.
        $this->assertSame('APP-55555', $tasks[0]['applicationNumber']);
    }

    public function test_two_parallel_loans_produce_two_entries(): void
    {
        $user = $this->user();
        foreach ([1, 2] as $n) {
            $loan = $this->loan($user, 'parallel_processing');
            StageAssignment::create(['loan_id' => $loan->id, 'stage_key' => 'legal_verification', 'parent_stage_key' => 'parallel_processing', 'status' => 'in_progress', 'is_parallel_stage' => true, 'assigned_to' => $user->id]);
        }

        $tasks = $this->myTasks($user);
        $this->assertCount(2, $tasks);
    }
}
