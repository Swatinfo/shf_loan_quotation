<?php

namespace Tests\Feature;

use App\Http\Controllers\LoanController;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stage;
use App\Models\StageAssignment;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Docket-date override — permission-gated (edit_docket_date), only after the
 * Sanction stage completes, with a mandatory reason logged old → new.
 */
class LoanDocketDateUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // permission cache is keyed by user id, which repeats across tests
        foreach (['super_admin', 'admin', 'branch_manager', 'loan_advisor'] as $slug) {
            Role::firstOrCreate(['slug' => $slug], ['name' => ucwords(str_replace('_', ' ', $slug))]);
        }
        Stage::firstOrCreate(
            ['stage_key' => 'sanction'],
            ['stage_name_en' => 'Sanction Letter', 'sequence_order' => 6, 'is_parallel' => false, 'stage_type' => 'sequential', 'is_enabled' => true]
        );
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

    private function grant(User $user, string $slug): void
    {
        $permission = Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucwords(str_replace('_', ' ', $slug)), 'group' => 'Loans']
        );
        UserPermission::create([
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'type' => 'grant',
        ]);
        Cache::flush();
    }

    private function makeLoan(User $advisor, string $sanctionStatus = 'completed'): LoanDetail
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'Product-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(),
            'customer_name' => 'Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 1000000,
            'status' => 'active',
            'current_stage' => 'docket',
            'bank_id' => $bank->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'created_by' => $advisor->id,
            'assigned_advisor' => $advisor->id,
            'expected_docket_date' => '2026-08-01',
        ]);

        StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'sanction',
            'assigned_to' => $advisor->id,
            'status' => $sanctionStatus,
        ]);

        return $loan;
    }

    public function test_permitted_user_can_change_docket_date_and_change_is_logged(): void
    {
        $advisor = $this->makeUser('branch_manager');
        $this->grant($advisor, 'view_loans');
        $this->grant($advisor, 'edit_docket_date');
        $loan = $this->makeLoan($advisor);

        $future = now()->addMonth()->startOfDay();

        $this->actingAs($advisor)
            ->postJson(route('loans.docket-date.update', $loan), [
                'docket_date' => $future->format('d/m/Y'),
                'reason' => 'Bank rescheduled the docket appointment',
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'docket_date' => $future->format('d M Y')]);

        $this->assertSame($future->toDateString(), $loan->fresh()->expected_docket_date->toDateString());
        $this->assertDatabaseHas('activity_log', ['description' => 'change_docket_date']);
    }

    public function test_today_is_accepted(): void
    {
        $advisor = $this->makeUser('branch_manager');
        $this->grant($advisor, 'view_loans');
        $this->grant($advisor, 'edit_docket_date');
        $loan = $this->makeLoan($advisor);

        $this->actingAs($advisor)
            ->postJson(route('loans.docket-date.update', $loan), [
                'docket_date' => now()->format('d/m/Y'),
                'reason' => 'Docket happening today',
            ])
            ->assertOk();

        $this->assertSame(now()->toDateString(), $loan->fresh()->expected_docket_date->toDateString());
    }

    public function test_completed_loan_cannot_change_docket_date(): void
    {
        $advisor = $this->makeUser('branch_manager');
        $this->grant($advisor, 'view_loans');
        $this->grant($advisor, 'edit_docket_date');
        $loan = $this->makeLoan($advisor);
        $loan->update(['status' => 'completed']);

        $this->actingAs($advisor)
            ->postJson(route('loans.docket-date.update', $loan), [
                'docket_date' => now()->addMonth()->format('d/m/Y'),
                'reason' => 'trying to change on a completed loan',
            ])
            ->assertStatus(422);

        $this->assertSame('2026-08-01', $loan->fresh()->expected_docket_date->toDateString());
    }

    public function test_past_docket_date_is_rejected(): void
    {
        $advisor = $this->makeUser('branch_manager');
        $this->grant($advisor, 'view_loans');
        $this->grant($advisor, 'edit_docket_date');
        $loan = $this->makeLoan($advisor);

        $this->actingAs($advisor)
            ->postJson(route('loans.docket-date.update', $loan), [
                'docket_date' => now()->subDay()->format('d/m/Y'),
                'reason' => 'trying to back-date the docket',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('docket_date');

        $this->assertSame('2026-08-01', $loan->fresh()->expected_docket_date->toDateString());
    }

    public function test_reason_is_required(): void
    {
        $advisor = $this->makeUser('branch_manager');
        $this->grant($advisor, 'view_loans');
        $this->grant($advisor, 'edit_docket_date');
        $loan = $this->makeLoan($advisor);

        $this->actingAs($advisor)
            ->postJson(route('loans.docket-date.update', $loan), ['docket_date' => now()->addMonth()->format('d/m/Y')])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->assertSame('2026-08-01', $loan->fresh()->expected_docket_date->toDateString());
    }

    public function test_invalid_date_format_is_rejected(): void
    {
        $advisor = $this->makeUser('branch_manager');
        $this->grant($advisor, 'view_loans');
        $this->grant($advisor, 'edit_docket_date');
        $loan = $this->makeLoan($advisor);

        $this->actingAs($advisor)
            ->postJson(route('loans.docket-date.update', $loan), [
                'docket_date' => '2026-09-15',
                'reason' => 'valid reason text',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('docket_date');
    }

    public function test_cannot_change_before_sanction_complete(): void
    {
        // Call the controller directly so the edit_docket_date route middleware
        // isn't what stops us — we're asserting the sanction-gate (422).
        $advisor = $this->makeUser('branch_manager');
        $this->grant($advisor, 'edit_docket_date');
        $loan = $this->makeLoan($advisor, sanctionStatus: 'in_progress');

        $this->actingAs($advisor);
        $controller = app(LoanController::class);
        $request = Request::create('', 'POST', ['docket_date' => '15/09/2026', 'reason' => 'valid reason text']);

        try {
            $controller->updateDocketDate($request, $loan);
            $this->fail('Expected a 422 HttpException.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame('2026-08-01', $loan->fresh()->expected_docket_date->toDateString());
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        // loan_advisor owns the loan (passes view auth) but lacks edit_docket_date.
        $advisor = $this->makeUser('loan_advisor');
        $loan = $this->makeLoan($advisor);

        $this->actingAs($advisor);
        $controller = app(LoanController::class);
        $request = Request::create('', 'POST', ['docket_date' => '15/09/2026', 'reason' => 'valid reason text']);

        try {
            $controller->updateDocketDate($request, $loan);
            $this->fail('Expected a 403 HttpException.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertSame('2026-08-01', $loan->fresh()->expected_docket_date->toDateString());
    }
}
