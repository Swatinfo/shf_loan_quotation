<?php

namespace Tests\Feature;

use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanStageController;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stage;
use App\Models\StageAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * DME (assigned during Application Number, locked on completion, changeable
 * afterwards only by super_admin/admin/bdh via the dedicated endpoint).
 */
class LoanDmeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'admin', 'bdh', 'loan_advisor'] as $slug) {
            Role::firstOrCreate(['slug' => $slug], ['name' => ucwords(str_replace('_', ' ', $slug))]);
        }
        Stage::firstOrCreate(
            ['stage_key' => 'app_number'],
            ['stage_name_en' => 'Application Number', 'sequence_order' => 1, 'is_parallel' => true, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'is_enabled' => true]
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

    private function makeLoan(User $advisor, string $appNumberStatus = 'completed'): LoanDetail
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
            'current_stage' => 'parallel_processing',
            'bank_id' => $bank->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'created_by' => $advisor->id,
            'assigned_advisor' => $advisor->id,
        ]);

        StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'app_number',
            'parent_stage_key' => 'parallel_processing',
            'assigned_to' => $advisor->id,
            'status' => $appNumberStatus,
            'is_parallel_stage' => true,
        ]);

        return $loan;
    }

    public function test_admin_can_set_dme_from_null(): void
    {
        $advisor = $this->makeUser('loan_advisor');
        $admin = $this->makeUser('super_admin');
        $loan = $this->makeLoan($advisor);
        $dme = $this->makeUser('loan_advisor');

        $this->assertNull($loan->dme_user_id);

        $this->actingAs($admin)
            ->postJson(route('loans.dme.update', $loan), ['dme_user_id' => $dme->id])
            ->assertOk()
            ->assertJson(['success' => true, 'dme_name' => $dme->name]);

        $this->assertSame($dme->id, $loan->fresh()->dme_user_id);
        // ActivityLog writes to Spatie's `activity_log` table; action → description.
        $this->assertDatabaseHas('activity_log', ['description' => 'change_dme']);
    }

    public function test_bdh_can_change_existing_dme(): void
    {
        // bdh is the loan's advisor here so the view-auth passes; the role check
        // (super_admin/admin/bdh) is what we're verifying. Called directly to
        // avoid seeding the view_loans permission for the route middleware.
        $bdh = $this->makeUser('bdh');
        $loan = $this->makeLoan($bdh);
        $loan->update(['dme_user_id' => $bdh->id]);
        $newDme = $this->makeUser('loan_advisor');

        $this->actingAs($bdh);
        $controller = app(LoanController::class);
        $request = Request::create('', 'POST', ['dme_user_id' => $newDme->id]);
        $response = $controller->updateDme($request, $loan);

        $this->assertTrue($response->getData()->success);
        $this->assertSame($newDme->id, $loan->fresh()->dme_user_id);
    }

    public function test_non_privileged_user_cannot_change_dme(): void
    {
        $advisor = $this->makeUser('loan_advisor');
        $loan = $this->makeLoan($advisor);
        $dme = $this->makeUser('loan_advisor');

        // Advisor owns the loan (passes view auth) but lacks the role to change DME.
        $this->actingAs($advisor);
        $controller = app(LoanController::class);
        $request = Request::create('', 'POST', ['dme_user_id' => $dme->id]);

        try {
            $controller->updateDme($request, $loan);
            $this->fail('Expected a 403 HttpException.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertNull($loan->fresh()->dme_user_id);
    }

    public function test_cannot_set_dme_before_app_number_complete(): void
    {
        $advisor = $this->makeUser('loan_advisor');
        $admin = $this->makeUser('super_admin');
        $loan = $this->makeLoan($advisor, appNumberStatus: 'in_progress');
        $dme = $this->makeUser('loan_advisor');

        $this->actingAs($admin)
            ->postJson(route('loans.dme.update', $loan), ['dme_user_id' => $dme->id])
            ->assertStatus(422);

        $this->assertNull($loan->fresh()->dme_user_id);
    }

    public function test_cannot_assign_admin_as_dme(): void
    {
        $advisor = $this->makeUser('loan_advisor');
        $admin = $this->makeUser('super_admin');
        $loan = $this->makeLoan($advisor);
        $adminTarget = $this->makeUser('admin');

        $this->actingAs($admin)
            ->postJson(route('loans.dme.update', $loan), ['dme_user_id' => $adminTarget->id])
            ->assertStatus(422);

        $this->assertNull($loan->fresh()->dme_user_id);
    }

    public function test_app_number_completion_requires_dme(): void
    {
        $advisor = $this->makeUser('loan_advisor');
        $loan = $this->makeLoan($advisor, appNumberStatus: 'in_progress');
        $assignment = $loan->stageAssignments()->where('stage_key', 'app_number')->first();

        $controller = app(LoanStageController::class);
        $method = (new \ReflectionClass($controller))->getMethod('isStageDataComplete');
        $method->setAccessible(true);

        // application_number + docket present, but no DME → not complete.
        $assignment->update(['notes' => json_encode(['application_number' => 'HL1', 'docket_days_offset' => '1'])]);
        $this->assertFalse($method->invoke($controller, 'app_number', $assignment->fresh()));

        // Add DME → complete.
        $assignment->update(['notes' => json_encode(['application_number' => 'HL1', 'docket_days_offset' => '1', 'dme_user_id' => $advisor->id])]);
        $this->assertTrue($method->invoke($controller, 'app_number', $assignment->fresh()));
    }
}
