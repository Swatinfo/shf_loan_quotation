<?php

namespace Tests\Feature;

use App\Models\LoanDetail;
use App\Models\User;
use App\Services\LoanStageService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(StageSeeder::class);
    }

    private function createUser(string $role = 'staff', array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_active' => true,
            'task_role' => 'loan_advisor',
        ], $overrides));
    }

    private function createLoan(User $user): LoanDetail
    {
        $loan = LoanDetail::create([
            'loan_number' => LoanDetail::generateLoanNumber(),
            'customer_name' => 'Test Customer',
            'customer_type' => 'proprietor',
            'loan_amount' => 5000000,
            'status' => LoanDetail::STATUS_ACTIVE,
            'current_stage' => 'inquiry',
            'created_by' => $user->id,
        ]);

        app(LoanStageService::class)->initializeStages($loan);

        return $loan;
    }

    // ── Unauthenticated Access ──

    public function test_unauthenticated_cannot_access_loans(): void
    {
        $response = $this->get(route('loans.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_cannot_create_loan(): void
    {
        $response = $this->get(route('loans.create'));
        $response->assertRedirect(route('login'));
    }

    // ── Staff Permissions ──

    public function test_staff_can_view_loans_index(): void
    {
        $staff = $this->createUser('staff');
        $response = $this->actingAs($staff)->get(route('loans.index'));
        $response->assertOk();
    }

    public function test_staff_can_create_loan(): void
    {
        $staff = $this->createUser('staff');
        $response = $this->actingAs($staff)->get(route('loans.create'));
        $response->assertOk();
    }

    public function test_staff_can_view_own_loan(): void
    {
        $staff = $this->createUser('staff');
        $loan = $this->createLoan($staff);

        $response = $this->actingAs($staff)->get(route('loans.show', $loan));
        $response->assertOk();
    }

    public function test_staff_cannot_edit_loan(): void
    {
        $staff = $this->createUser('staff');
        $loan = $this->createLoan($staff);

        $response = $this->actingAs($staff)->get(route('loans.edit', $loan));
        $response->assertForbidden();
    }

    public function test_staff_cannot_delete_loan(): void
    {
        $staff = $this->createUser('staff');
        $loan = $this->createLoan($staff);

        $response = $this->actingAs($staff)->deleteJson(route('loans.destroy', $loan));
        $response->assertForbidden();
    }

    public function test_staff_cannot_skip_stages(): void
    {
        $staff = $this->createUser('staff');
        $loan = $this->createLoan($staff);

        $response = $this->actingAs($staff)->postJson(
            route('loans.stages.skip', [$loan, 'inquiry'])
        );

        $response->assertForbidden();
    }

    public function test_staff_can_manage_documents(): void
    {
        $staff = $this->createUser('staff');
        $loan = $this->createLoan($staff);

        $response = $this->actingAs($staff)->get(
            route('loans.documents', $loan)
        );

        $response->assertOk();
    }

    public function test_staff_can_manage_stages(): void
    {
        $staff = $this->createUser('staff');
        $loan = $this->createLoan($staff);

        $response = $this->actingAs($staff)->postJson(
            route('loans.stages.status', [$loan, 'inquiry']),
            ['status' => 'in_progress']
        );

        $response->assertOk()->assertJson(['success' => true]);
    }

    // ── Admin Permissions ──

    public function test_admin_can_edit_loan(): void
    {
        $admin = $this->createUser('admin');
        $loan = $this->createLoan($admin);

        $response = $this->actingAs($admin)->get(route('loans.edit', $loan));
        $response->assertOk();
    }

    public function test_admin_can_delete_loan(): void
    {
        $admin = $this->createUser('admin');
        $loan = $this->createLoan($admin);

        $response = $this->actingAs($admin)->deleteJson(route('loans.destroy', $loan));
        $response->assertOk()->assertJson(['success' => true]);
        $this->assertSoftDeleted('loan_details', ['id' => $loan->id]);
    }

    public function test_admin_can_skip_stages(): void
    {
        $admin = $this->createUser('admin');
        $loan = $this->createLoan($admin);

        $response = $this->actingAs($admin)->postJson(
            route('loans.stages.skip', [$loan, 'inquiry'])
        );

        $response->assertOk()->assertJson(['success' => true]);
    }

    public function test_admin_sees_all_loans(): void
    {
        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');
        $this->createLoan($admin);
        $this->createLoan($staff);

        $response = $this->actingAs($admin)->get(route('loans.index'));
        $response->assertOk();
    }

    // ── Super Admin ──

    public function test_super_admin_can_access_everything(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $loan = $this->createLoan($superAdmin);

        $this->actingAs($superAdmin)->get(route('loans.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('loans.create'))->assertOk();
        $this->actingAs($superAdmin)->get(route('loans.show', $loan))->assertOk();
        $this->actingAs($superAdmin)->get(route('loans.edit', $loan))->assertOk();
        $this->actingAs($superAdmin)->get(route('loans.stages', $loan))->assertOk();
        $this->actingAs($superAdmin)->get(route('loans.documents', $loan))->assertOk();
    }

    // ── Visibility Scope ──

    public function test_staff_cannot_see_others_loans_in_show(): void
    {
        $staff1 = $this->createUser('staff');
        $staff2 = $this->createUser('staff');
        $loan = $this->createLoan($staff2);

        $response = $this->actingAs($staff1)->get(route('loans.show', $loan));

        // Should be forbidden or redirect — staff1 doesn't own this loan
        $this->assertTrue(in_array($response->status(), [403, 302]));
    }

    // ── Inactive User ──

    public function test_inactive_user_cannot_access_loans(): void
    {
        $inactive = User::factory()->create([
            'role' => 'admin',
            'is_active' => false,
        ]);

        $response = $this->actingAs($inactive)->get(route('loans.index'));
        // EnsureUserIsActive middleware should block
        $this->assertTrue(in_array($response->status(), [302, 403]));
    }

    // ── Workflow Config ──

    public function test_staff_can_view_loan_settings_but_not_modify(): void
    {
        $staff = $this->createUser('staff');

        // Staff can view loan-settings index (uses view_loans permission)
        $response = $this->actingAs($staff)->get(route('loan-settings.index'));
        $response->assertOk();

        // But staff cannot modify (manage_workflow_config required)
        $response = $this->actingAs($staff)->post(route('loan-settings.branches.store'), [
            'name' => 'Test Branch',
            'code' => 'TB01',
        ]);
        $response->assertForbidden();
    }

    public function test_admin_can_access_loan_settings(): void
    {
        $admin = $this->createUser('admin');

        $response = $this->actingAs($admin)->get(route('loan-settings.index'));
        $response->assertOk();
    }
}
