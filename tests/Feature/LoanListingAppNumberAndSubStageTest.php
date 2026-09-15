<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stage;
use App\Models\StageAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Loan-list (`loans.data`) additions:
 *  - application_number is returned + searchable.
 *  - Sub-stage filter matches loans CURRENTLY AT the sub-stage (in_progress under
 *    parallel_processing); top-level stages keep "completed" semantics.
 */
class LoanListingAppNumberAndSubStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);

        // Seed the stages the sub-stage branch inspects.
        Stage::firstOrCreate(['stage_key' => 'legal_verification'], [
            'stage_name_en' => 'Legal Verification', 'stage_name_gu' => 'Legal',
            'sequence_order' => 4, 'is_parallel' => false,
            'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'is_enabled' => true,
        ]);
        Stage::firstOrCreate(['stage_key' => 'otc_clearance'], [
            'stage_name_en' => 'OTC Clearance', 'stage_name_gu' => 'OTC',
            'sequence_order' => 11, 'is_parallel' => false,
            'parent_stage_key' => null, 'stage_type' => 'sequential', 'is_enabled' => true,
        ]);
    }

    private function admin(): User
    {
        $u = User::create([
            'name' => 'Admin '.uniqid(), 'email' => uniqid().'@test',
            'password' => bcrypt('x'), 'is_active' => true,
        ]);
        $u->roles()->sync(Role::where('slug', 'super_admin')->pluck('id'));

        return $u->fresh('roles');
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

    private function loanNumbers($response): string
    {
        return collect($response->json('data'))->pluck('loan_number')->implode(' ');
    }

    public function test_application_number_is_returned_in_listing(): void
    {
        $admin = $this->admin();
        $this->makeLoan($admin, ['application_number' => 'APP-12345']);

        $response = $this->actingAs($admin)->getJson(route('loans.data'))->assertOk();

        $this->assertSame('APP-12345', collect($response->json('data'))->first()['application_number']);
    }

    public function test_search_matches_application_number(): void
    {
        $admin = $this->admin();
        $match = $this->makeLoan($admin, ['application_number' => 'APP-77777']);
        $other = $this->makeLoan($admin, ['application_number' => 'APP-00000']);

        $response = $this->actingAs($admin)
            ->getJson(route('loans.data', ['search' => ['value' => 'APP-77777']]))
            ->assertOk();

        $names = $this->loanNumbers($response);
        $this->assertStringContainsString($match->loan_number, $names);
        $this->assertStringNotContainsString($other->loan_number, $names);
    }

    public function test_sub_stage_filter_matches_currently_at_not_completed(): void
    {
        $admin = $this->admin();

        // Loan A currently AT legal_verification (in_progress under parallel block).
        $atLegal = $this->makeLoan($admin, ['current_stage' => 'parallel_processing']);
        StageAssignment::create([
            'loan_id' => $atLegal->id, 'stage_key' => 'legal_verification',
            'parent_stage_key' => 'parallel_processing', 'status' => 'in_progress',
            'started_at' => now(),
        ]);

        // Loan B has COMPLETED legal_verification — must NOT match the "currently at" filter.
        $pastLegal = $this->makeLoan($admin);
        StageAssignment::create([
            'loan_id' => $pastLegal->id, 'stage_key' => 'legal_verification',
            'parent_stage_key' => 'parallel_processing', 'status' => 'completed',
            'started_at' => now()->subDay(), 'completed_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('loans.data', ['stage' => 'legal_verification']))
            ->assertOk();

        $response->assertJsonPath('recordsFiltered', 1);
        $names = $this->loanNumbers($response);
        $this->assertStringContainsString($atLegal->loan_number, $names);
        $this->assertStringNotContainsString($pastLegal->loan_number, $names);
    }

    /** Exact reported bug: filtering KFS must not return loans that moved past it. */
    public function test_kfs_filter_excludes_loans_at_disbursement_and_otc(): void
    {
        $admin = $this->admin();

        $atKfs = $this->makeLoan($admin, ['current_stage' => 'kfs']);
        StageAssignment::create([
            'loan_id' => $atKfs->id, 'stage_key' => 'kfs',
            'status' => 'in_progress', 'started_at' => now(),
        ]);

        // Loan now at disbursement — KFS long completed.
        $atDisb = $this->makeLoan($admin, ['current_stage' => 'disbursement']);
        StageAssignment::create([
            'loan_id' => $atDisb->id, 'stage_key' => 'kfs',
            'status' => 'completed', 'started_at' => now()->subDays(2), 'completed_at' => now()->subDay(),
        ]);
        StageAssignment::create([
            'loan_id' => $atDisb->id, 'stage_key' => 'disbursement',
            'status' => 'in_progress', 'started_at' => now(),
        ]);

        // Loan at OTC — KFS completed too.
        $atOtc = $this->makeLoan($admin, ['current_stage' => 'otc_clearance']);
        StageAssignment::create([
            'loan_id' => $atOtc->id, 'stage_key' => 'kfs',
            'status' => 'completed', 'started_at' => now()->subDays(3), 'completed_at' => now()->subDays(2),
        ]);
        StageAssignment::create([
            'loan_id' => $atOtc->id, 'stage_key' => 'otc_clearance',
            'status' => 'in_progress', 'started_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('loans.data', ['stage' => 'kfs']))
            ->assertOk();

        $response->assertJsonPath('recordsFiltered', 1);
        $names = $this->loanNumbers($response);
        $this->assertStringContainsString($atKfs->loan_number, $names);
        $this->assertStringNotContainsString($atDisb->loan_number, $names);
        $this->assertStringNotContainsString($atOtc->loan_number, $names);
    }

    public function test_top_level_stage_filter_matches_currently_at_not_past(): void
    {
        $admin = $this->admin();

        // Currently AT otc_clearance → matches.
        $atOtc = $this->makeLoan($admin);
        StageAssignment::create([
            'loan_id' => $atOtc->id, 'stage_key' => 'otc_clearance',
            'status' => 'in_progress', 'started_at' => now(),
        ]);

        // Past otc_clearance (completed) → must NOT match: this is the KFS-shows-
        // disbursement/OTC bug fix — completing a stage no longer surfaces the loan.
        $pastOtc = $this->makeLoan($admin);
        StageAssignment::create([
            'loan_id' => $pastOtc->id, 'stage_key' => 'otc_clearance',
            'status' => 'completed', 'started_at' => now()->subDay(), 'completed_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('loans.data', ['stage' => 'otc_clearance']))
            ->assertOk();

        $response->assertJsonPath('recordsFiltered', 1);
        $names = $this->loanNumbers($response);
        $this->assertStringContainsString($atOtc->loan_number, $names);
        $this->assertStringNotContainsString($pastOtc->loan_number, $names);
    }
}
