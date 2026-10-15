<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\DisbursementDetail;
use App\Models\DisbursementEntry;
use App\Models\LoanDetail;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stage;
use App\Models\StageAssignment;
use App\Models\StageQuery;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Permission-gated loan stage reset — web action (loans.stages.reset), gated by
 * the `reset_loan_stages` permission, plus the shared
 * LoanStageService::resetToStage() engine used by the CLI too.
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
        // A resetter role that holds the reset permission; a plain role that does not.
        $resetter = Role::firstOrCreate(['slug' => 'branch_manager'], ['name' => 'Branch Manager']);
        Role::firstOrCreate(['slug' => 'loan_advisor'], ['name' => 'Loan Advisor']);

        // RefreshDatabase runs migrations only; base role_permission seeding isn't
        // guaranteed in tests, so grant the reset permission explicitly.
        $perm = Permission::firstOrCreate(['slug' => 'reset_loan_stages'], ['name' => 'Reset Loan Stages', 'group' => 'Loans']);
        $resetter->permissions()->syncWithoutDetaching([$perm->id]);
        app(PermissionService::class)->clearAllCaches();

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

    private function user(string $email, ?string $role = null): User
    {
        $u = User::create([
            'name' => 'U '.uniqid(),
            'email' => $email,
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);
        if ($role) {
            $u->roles()->sync(Role::where('slug', $role)->pluck('id'));
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

    public function test_permission_holder_resets_loan_to_earlier_stage(): void
    {
        $allowed = $this->user('bm-'.uniqid().'@test', 'branch_manager');
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
        $allowed = $this->user('bm-'.uniqid().'@test', 'branch_manager');
        $owner = $this->user('owner-'.uniqid().'@test');
        $loan = $this->makeCompletedLoan($owner);

        $this->actingAs($allowed)
            ->postJson(route('loans.stages.reset', $loan), ['stage_key' => 'document_collection'])
            ->assertOk();

        $this->assertNull($loan->fresh()->application_number);
    }

    public function test_user_without_reset_permission_is_forbidden(): void
    {
        // loan_advisor role does not hold reset_loan_stages → 403 at the route.
        $notAllowed = $this->user('adv-'.uniqid().'@test', 'loan_advisor');
        $owner = $this->user('owner-'.uniqid().'@test');
        $loan = $this->makeCompletedLoan($owner);

        $this->actingAs($notAllowed)
            ->postJson(route('loans.stages.reset', $loan), ['stage_key' => 'sanction'])
            ->assertForbidden();

        // Nothing changed.
        $this->assertSame('otc_clearance', $loan->fresh()->current_stage);
    }

    public function test_super_admin_bypasses_permission_and_can_reset(): void
    {
        $superAdmin = $this->user('sa-'.uniqid().'@test', 'super_admin');
        $owner = $this->user('owner-'.uniqid().'@test');
        $loan = $this->makeCompletedLoan($owner);

        $this->actingAs($superAdmin)
            ->postJson(route('loans.stages.reset', $loan), ['stage_key' => 'sanction'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('sanction', $loan->fresh()->current_stage);
    }

    public function test_unknown_stage_key_is_rejected(): void
    {
        $allowed = $this->user('bm-'.uniqid().'@test', 'branch_manager');
        $owner = $this->user('owner-'.uniqid().'@test');
        $loan = $this->makeCompletedLoan($owner);

        $this->actingAs($allowed)
            ->postJson(route('loans.stages.reset', $loan), ['stage_key' => 'not_a_stage'])
            ->assertStatus(422);
    }

    private function attachDisbursement(LoanDetail $loan, array $entryOverrides = []): DisbursementEntry
    {
        $detail = DisbursementDetail::create([
            'loan_id' => $loan->id,
            'disbursement_type' => 'cheque',
            'amount_disbursed' => 1000000,
            'completion_intent' => 'full',
        ]);

        return DisbursementEntry::create(array_merge([
            'loan_id' => $loan->id,
            'disbursement_detail_id' => $detail->id,
            'method' => 'cheque',
            'amount' => 1000000,
            'cheque_number' => '12345',
            'otc_status' => 'cleared',
            'is_active' => true,
        ], $entryOverrides));
    }

    public function test_reset_to_rate_pf_removes_disbursement_entries(): void
    {
        $allowed = $this->user('bm-'.uniqid().'@test', 'branch_manager');
        $owner = $this->user('owner-'.uniqid().'@test');
        $loan = $this->makeCompletedLoan($owner);
        $this->attachDisbursement($loan);

        $this->assertSame(1, $loan->disbursementEntries()->count());

        $this->actingAs($allowed)
            ->postJson(route('loans.stages.reset', $loan), ['stage_key' => 'rate_pf'])
            ->assertOk();

        $this->assertSame(0, $loan->disbursementEntries()->count(), 'disbursement entries should be deleted');
        $this->assertSame(0, DisbursementEntry::withTrashed()->where('loan_id', $loan->id)->count(), 'entries should be force-deleted, not soft-deleted');
        $this->assertNull($loan->fresh()->disbursement);
    }

    public function test_reset_is_blocked_when_a_tranche_has_a_finalized_payout(): void
    {
        $allowed = $this->user('bm-'.uniqid().'@test', 'branch_manager');
        $owner = $this->user('owner-'.uniqid().'@test');
        $loan = $this->makeCompletedLoan($owner);
        $this->attachDisbursement($loan, ['payout_run_id' => 1]);

        $this->actingAs($allowed)
            ->postJson(route('loans.stages.reset', $loan), ['stage_key' => 'rate_pf'])
            ->assertStatus(422)
            ->assertJsonPath('error', fn ($m) => str_contains($m, 'finalized payout'));

        // Nothing changed — still completed, entry intact.
        $this->assertSame('completed', $loan->fresh()->status);
        $this->assertSame(1, $loan->disbursementEntries()->count());
    }

    public function test_reset_to_rate_pf_clears_sanctioned_amount(): void
    {
        $allowed = $this->user('bm-'.uniqid().'@test', 'branch_manager');
        $owner = $this->user('owner-'.uniqid().'@test');
        $loan = $this->makeCompletedLoan($owner);
        $loan->update(['sanctioned_amount' => 950000]);

        $this->actingAs($allowed)
            ->postJson(route('loans.stages.reset', $loan), ['stage_key' => 'rate_pf'])
            ->assertOk();

        $this->assertNull($loan->fresh()->sanctioned_amount);
    }

    public function test_reset_closes_open_queries_on_forward_stages(): void
    {
        $allowed = $this->user('bm-'.uniqid().'@test', 'branch_manager');
        $owner = $this->user('owner-'.uniqid().'@test');
        $loan = $this->makeCompletedLoan($owner);

        $query = StageQuery::create([
            'stage_assignment_id' => $loan->stageAssignments->firstWhere('stage_key', 'disbursement')->id,
            'loan_id' => $loan->id,
            'stage_key' => 'disbursement',
            'query_text' => 'please clarify',
            'raised_by' => $owner->id,
            'status' => 'pending',
        ]);

        $this->actingAs($allowed)
            ->postJson(route('loans.stages.reset', $loan), ['stage_key' => 'rate_pf'])
            ->assertOk();

        $this->assertSame('resolved', $query->fresh()->status);
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
