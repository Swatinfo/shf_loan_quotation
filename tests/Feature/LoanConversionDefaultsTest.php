<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Permission;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The convert-to-loan form defaults the advisor and payout user to the current
 * user — except that a connector who created the quotation (the lead source)
 * keeps the payout.
 */
class LoanConversionDefaultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure the full current permission set exists (RefreshDatabase runs
        // migrations but not PermissionSeeder).
        $this->seed(PermissionSeeder::class);
    }

    private function user(string $role): User
    {
        $u = User::create(['name' => ucfirst($role).'-'.uniqid(), 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->roles()->sync(Role::where('slug', $role)->pluck('id'));
        app(PermissionService::class)->clearAllCaches();

        return $u->fresh('roles');
    }

    private function grant(User $user, string ...$slugs): void
    {
        foreach ($slugs as $slug) {
            $user->userPermissions()->create([
                'permission_id' => Permission::where('slug', $slug)->value('id'),
                'type' => 'grant',
            ]);
        }
        app(PermissionService::class)->clearAllCaches();
    }

    private function quotationBy(User $creator): Quotation
    {
        $branch = Branch::create(['name' => 'B-'.uniqid(), 'is_active' => true]);

        return Quotation::create([
            'user_id' => $creator->id,
            'customer_name' => 'Convertee',
            'customer_type' => 'salaried',
            'loan_amount' => 1500000,
            'pdf_filename' => 'c.pdf',
            'pdf_path' => 'storage/app/pdfs/c.pdf',
            'selected_tenures' => [10, 15],
            'branch_id' => $branch->id,
            'status' => Quotation::STATUS_ACTIVE,
        ]);
    }

    public function test_advisor_and_payout_default_to_the_current_user(): void
    {
        $advisor = $this->user('loan_advisor'); // advisor- AND payout-eligible
        $this->grant($advisor, 'convert_to_loan');
        $quotation = $this->quotationBy($advisor); // own quotation → visible

        $resp = $this->actingAs($advisor->fresh('roles'))->get(route('quotations.convert', $quotation))->assertOk();

        $this->assertSame($advisor->id, $resp->viewData('defaultAdvisorId'));
        $this->assertSame($advisor->id, $resp->viewData('defaultPayoutUserId'));
    }

    public function test_connector_creator_keeps_the_payout_but_advisor_is_current_user(): void
    {
        $connector = $this->user('connector');
        $advisor = $this->user('loan_advisor');
        $this->grant($advisor, 'convert_to_loan', 'view_all_quotations');

        $quotation = $this->quotationBy($connector);

        $resp = $this->actingAs($advisor->fresh('roles'))->get(route('quotations.convert', $quotation))->assertOk();

        $this->assertSame($advisor->id, $resp->viewData('defaultAdvisorId'));
        $this->assertSame($connector->id, $resp->viewData('defaultPayoutUserId')); // lead source keeps payout
    }

    public function test_super_admin_converter_gets_no_self_default(): void
    {
        // super_admin is neither advisor- nor payout-eligible → no self default.
        $sa = $this->user('super_admin');
        $quotation = $this->quotationBy($sa);

        $resp = $this->actingAs($sa)->get(route('quotations.convert', $quotation))->assertOk();

        $this->assertNull($resp->viewData('defaultAdvisorId'));
        $this->assertNull($resp->viewData('defaultPayoutUserId'));
    }
}
