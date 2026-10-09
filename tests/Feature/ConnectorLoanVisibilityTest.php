<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A connector gets read-only visibility of the loans created from their own
 * quotations (quotation.user_id): they can view, but every mutating loan route
 * is blocked because connectors hold none of the action permissions.
 */
class ConnectorLoanVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed the full permission catalog + re-grant connector perms by slug
        // (RefreshDatabase doesn't run PermissionSeeder), then grant the new one.
        $this->seed(PermissionSeeder::class);
        $connector = Role::where('slug', 'connector')->first();
        if ($connector) {
            $connector->permissions()->syncWithoutDetaching(
                Permission::whereIn('slug', ['view_connector_loans', 'view_own_quotations', 'create_quotation'])->pluck('id')
            );
        }
        app(PermissionService::class)->clearAllCaches();
    }

    private function user(string $role): User
    {
        $u = User::create(['name' => $role.' '.uniqid(), 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->roles()->sync(Role::where('slug', $role)->pluck('id'));

        return $u->fresh('roles');
    }

    private function loanFromQuotationOf(User $quotationOwner, User $creator): LoanDetail
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'P-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        $quotation = Quotation::create([
            'user_id' => $quotationOwner->id,
            'customer_name' => 'Cust '.uniqid(),
            'customer_type' => 'salaried',
            'loan_amount' => 1000000,
        ]);

        return LoanDetail::create([
            'loan_number' => 'L-'.uniqid(), 'customer_name' => 'Cust', 'customer_type' => 'salaried',
            'loan_amount' => 1000000, 'status' => 'active', 'current_stage' => 'inquiry',
            'bank_id' => $bank->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'created_by' => $creator->id, 'quotation_id' => $quotation->id,
        ]);
    }

    public function test_connector_sees_only_loans_from_their_own_quotations(): void
    {
        $connector = $this->user('connector');
        $otherConnector = $this->user('connector');
        $staff = $this->user('loan_advisor');

        $mine = $this->loanFromQuotationOf($connector, $staff);
        $theirs = $this->loanFromQuotationOf($otherConnector, $staff);

        $visibleIds = LoanDetail::visibleTo($connector)->pluck('id');

        $this->assertTrue($visibleIds->contains($mine->id));
        $this->assertFalse($visibleIds->contains($theirs->id));
    }

    public function test_connector_can_open_their_loan_but_not_a_strangers(): void
    {
        $connector = $this->user('connector');
        $staff = $this->user('loan_advisor');
        $mine = $this->loanFromQuotationOf($connector, $staff);
        $theirs = $this->loanFromQuotationOf($this->user('connector'), $staff);

        $this->actingAs($connector)->get(route('loans.show', $mine))->assertOk();
        $this->actingAs($connector)->get(route('loans.show', $theirs))->assertForbidden();
        $this->actingAs($connector)->get(route('loans.index'))->assertOk();
    }

    public function test_connector_dashboard_loads_and_shows_the_loans_tab(): void
    {
        $connector = $this->user('connector');
        $staff = $this->user('loan_advisor');
        $this->loanFromQuotationOf($connector, $staff);

        $resp = $this->actingAs($connector)->get(route('dashboard'))->assertOk();

        $tabs = collect($resp->viewData('payload')['tabs'])->keyBy('key');
        $this->assertTrue($tabs['loans']['visible']);              // Loans tab shown
        $this->assertFalse($tabs['stage-breakdown']['visible']);   // ops funnel hidden for connector-only
        $this->assertFalse($tabs['tasks']['visible']);             // loan-stage tasks hidden (none for a connector)
        $this->assertSame(1, $tabs['loans']['count']);
    }

    public function test_connector_cannot_mutate_a_loan(): void
    {
        $connector = $this->user('connector');
        $staff = $this->user('loan_advisor');
        $mine = $this->loanFromQuotationOf($connector, $staff);

        // edit_loan / manage_loan_stages are not connector permissions → 403 at the gate.
        $this->actingAs($connector)
            ->post(route('loans.update-status', $mine), ['status' => 'on_hold'])
            ->assertForbidden();

        $this->actingAs($connector)
            ->post(route('loans.stages.status', ['loan' => $mine, 'stageKey' => 'inquiry']), [])
            ->assertForbidden();
    }
}
