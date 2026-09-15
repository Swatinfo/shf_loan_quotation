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
 * Dashboard "Active Loans" tab: a loan in parallel_processing lists its active
 * sub-stages + owners combined (type 'parallel'); application_number is surfaced.
 */
class DashboardLoansTabTest extends TestCase
{
    use RefreshDatabase;

    private const SUBS = ['legal_verification', 'technical_valuation'];

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['slug' => 'loan_advisor'], ['name' => 'Loan Advisor']);
        Stage::firstOrCreate(['stage_key' => 'kfs'], [
            'stage_name_en' => 'KFS', 'stage_name_gu' => 'KFS', 'sequence_order' => 8,
            'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'is_enabled' => true,
        ]);
        foreach (self::SUBS as $key) {
            Stage::firstOrCreate(['stage_key' => $key], [
                'stage_name_en' => ucwords(str_replace('_', ' ', $key)), 'stage_name_gu' => $key,
                'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing',
                'stage_type' => 'sequential', 'is_enabled' => true,
            ]);
        }
    }

    private function user(): User
    {
        $u = User::create(['name' => 'U-'.uniqid(), 'email' => uniqid().'@t.local', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->roles()->sync(Role::where('slug', 'loan_advisor')->pluck('id'));

        return $u->fresh('roles');
    }

    private function loan(User $owner, string $currentStage, ?string $appNo = null): LoanDetail
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'Product-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        return LoanDetail::create([
            'loan_number' => 'SHF-'.uniqid(), 'application_number' => $appNo,
            'customer_name' => 'Customer', 'customer_type' => 'salaried', 'loan_amount' => 1000000,
            'status' => 'active', 'current_stage' => $currentStage,
            'bank_id' => $bank->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'created_by' => $owner->id, 'assigned_advisor' => $owner->id,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function loansTab(User $user): array
    {
        $method = new ReflectionMethod(DashboardController::class, 'newthemeLoans');
        $method->setAccessible(true);

        return $method->invoke(app(DashboardController::class), $user);
    }

    public function test_parallel_loan_shows_combined_substages_and_owners(): void
    {
        $user = $this->user();
        $loan = $this->loan($user, 'parallel_processing', 'APP-900');
        $legalOwner = $this->user();
        $tvOwner = $this->user();
        StageAssignment::create(['loan_id' => $loan->id, 'stage_key' => 'legal_verification', 'parent_stage_key' => 'parallel_processing', 'status' => 'in_progress', 'is_parallel_stage' => true, 'assigned_to' => $legalOwner->id]);
        StageAssignment::create(['loan_id' => $loan->id, 'stage_key' => 'technical_valuation', 'parent_stage_key' => 'parallel_processing', 'status' => 'in_progress', 'is_parallel_stage' => true, 'assigned_to' => $tvOwner->id]);

        $rows = $this->loansTab($user);
        $row = collect($rows)->firstWhere('loanNumber', $loan->loan_number);

        $this->assertNotNull($row);
        $this->assertSame('parallel', $row['type']);
        $this->assertCount(2, $row['subStages']);
        $owners = collect($row['subStages'])->pluck('owner')->all();
        $this->assertContains($legalOwner->name, $owners);
        $this->assertContains($tvOwner->name, $owners);
        $this->assertSame('APP-900', $row['applicationNumber']);
    }

    public function test_non_parallel_loan_is_single_with_app_number(): void
    {
        $user = $this->user();
        $loan = $this->loan($user, 'kfs', 'APP-123');
        StageAssignment::create(['loan_id' => $loan->id, 'stage_key' => 'kfs', 'status' => 'in_progress', 'assigned_to' => $user->id]);

        $rows = $this->loansTab($user);
        $row = collect($rows)->firstWhere('loanNumber', $loan->loan_number);

        $this->assertNotNull($row);
        $this->assertSame('single', $row['type']);
        $this->assertSame('APP-123', $row['applicationNumber']);
    }
}
