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
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Email-gated loan stage reset — web action (loans.stages.reset) + the
 * shared LoanStageService::resetToStage() engine used by the CLI too.
 */
class LoanStageResetTest extends TestCase
{
    use RefreshDatabase;

    /** Main-stage flow used to seed a fully-completed loan. */
    private const FLOW = [
        'inquiry', 'document_selection', 'document_collection',
        'rate_pf', 'sanction', 'docket', 'kfs', 'esign', 'disbursement', 'otc_clearance',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        // Restrict the reset to a single known account for the tests.
        config(['app.stage_reset_emails' => ['superadmin@shfworld.com']]);
        $this->seedStages();
    }

    private function seedStages(): void
    {
        foreach (self::FLOW as $i => $key) {
            Stage::firstOrCreate(['stage_key' => $key], [
                'stage_name_en' => ucwords(str_replace('_', ' ', $key)),
                'stage_name_gu' => $key,
                'sequence_order' => $i + 1,
                'is_parallel' => false,
                'parent_stage_key' => null,
                'stage_type' => 'sequential',
                'is_enabled' => true,
            ]);
        }
    }

    private function user(string $email, bool $superAdmin = false): User
    {
        $u = User::create([
            'name' => 'U '.uniqid(),
            'email' => $email,
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);
        if ($superAdmin) {
            $u->roles()->sync(Role::where('slug', 'super_admin')->pluck('id'));
        }

        return $u->fresh('roles');
    }

    private function makeCompletedLoan(User $owner): LoanDetail
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'Product-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(),
            'customer_name' => 'Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 1000000,
            'status' => 'completed',
            'current_stage' => 'otc_clearance',
            'application_number' => 'APP-123',
            'bank_id' => $bank->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'created_by' => $owner->id,
            'assigned_advisor' => $owner->id,
        ]);

        foreach (self::FLOW as $key) {
            StageAssignment::create([
                'loan_id' => $loan->id,
                'stage_key' => $key,
                'status' => 'completed',
                'assigned_to' => $owner->id,
                'is_parallel_stage' => false,
                'parent_stage_key' => null,
                'started_at' => now()->subDays(2),
                'completed_at' => now()->subDay(),
            ]);
        }

        return $loan->fresh('stageAssignments');
    }

    public function test_allowed_email_resets_loan_to_earlier_stage(): void
    {
        $allowed = $this->user('superadmin@shfworld.com');
        $owner = $this->user('owner-'.uniqid().'@test');
        $loan = $this->makeCompletedLoan($owner);

        $this->actingAs($allowed)
            ->postJson(route('loans.stages.reset', $loan), ['stage_key' => 'sanction'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $loan->refresh();
        $this->assertSame('sanction', $loan->current_stage);
        $this->assertSame('active', $loan->status);

        $status = fn (string $key) => $loan->stageAssignments->firstWhere('stage_key', $key)?->status;

        // Target is in progress again.
        $this->assertSame('in_progress', $status('sanction'));
        // Earlier stages untouched.
        $this->assertSame('completed', $status('rate_pf'));
        // Everything after the target reset to pending.
        foreach (['docket', 'kfs', 'esign', 'disbursement', 'otc_clearance'] as $later) {
            $this->assertSame('pending', $status($later), "{$later} should be pending");
        }
    }

    public function test_reset_before_app_number_clears_application_number(): void
    {
        $allowed = $this->user('superadmin@shfworld.com');
        $owner = $this->user('owner-'.uniqid().'@test');
        $loan = $this->makeCompletedLoan($owner);

        $this->actingAs($allowed)
            ->postJson(route('loans.stages.reset', $loan), ['stage_key' => 'document_collection'])
            ->assertOk();

        $this->assertNull($loan->fresh()->application_number);
    }

    public function test_non_allowed_user_is_forbidden_even_as_super_admin(): void
    {
        // super_admin ROLE, but email not in the allowlist → still denied.
        $notAllowed = $this->user('someone-else@shfworld.com', superAdmin: true);
        $owner = $this->user('owner-'.uniqid().'@test');
        $loan = $this->makeCompletedLoan($owner);

        $this->actingAs($notAllowed)
            ->postJson(route('loans.stages.reset', $loan), ['stage_key' => 'sanction'])
            ->assertForbidden();

        // Nothing changed.
        $this->assertSame('otc_clearance', $loan->fresh()->current_stage);
    }

    public function test_unknown_stage_key_is_rejected(): void
    {
        $allowed = $this->user('superadmin@shfworld.com');
        $owner = $this->user('owner-'.uniqid().'@test');
        $loan = $this->makeCompletedLoan($owner);

        $this->actingAs($allowed)
            ->postJson(route('loans.stages.reset', $loan), ['stage_key' => 'not_a_stage'])
            ->assertStatus(422);
    }

    public function test_console_command_non_interactive_reset(): void
    {
        $owner = $this->user('owner-'.uniqid().'@test');
        $loan = $this->makeCompletedLoan($owner);

        $exit = Artisan::call('loan:set-stage', [
            'loan' => $loan->id,
            'stage' => 'docket',
            '--force' => true,
        ]);

        $this->assertSame(0, $exit);
        $loan->refresh();
        $this->assertSame('docket', $loan->current_stage);
        $this->assertSame('in_progress', $loan->stageAssignments->firstWhere('stage_key', 'docket')?->status);
        $this->assertSame('pending', $loan->stageAssignments->firstWhere('stage_key', 'esign')?->status);
    }
}
