<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The `connector` role: can create quotations and download them PLAIN only
 * (no branded), and cannot convert quotations to loans.
 */
class ConnectorRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The permission catalog is seeded by PermissionSeeder, which does not run
        // under RefreshDatabase — so the add_connector_role migration (which runs
        // before any seeder) finds no catalog to grant from. Seed the catalog and
        // re-apply the connector grants here (mirrors what the migration does in
        // production, where the catalog already exists).
        $this->seed(PermissionSeeder::class);

        $connector = Role::where('slug', 'connector')->first();
        $slugs = [
            'create_quotation', 'edit_quotation', 'generate_pdf', 'view_own_quotations',
            'download_pdf', 'download_pdf_plain', 'change_own_password', 'view_dashboard', 'manage_notifications',
        ];
        $connector->permissions()->syncWithoutDetaching(Permission::whereIn('slug', $slugs)->pluck('id'));

        app(PermissionService::class)->clearAllCaches();
    }

    private function connector(): User
    {
        $u = User::create([
            'name' => 'Conn-'.uniqid(), 'email' => uniqid().'@t',
            'password' => bcrypt('x'), 'is_active' => true,
        ]);
        $u->roles()->sync(Role::where('slug', 'connector')->pluck('id'));

        return $u->fresh('roles');
    }

    public function test_connector_role_exists_and_is_seeded(): void
    {
        $role = Role::where('slug', 'connector')->first();
        $this->assertNotNull($role, 'connector role should be seeded by migration');
        $this->assertFalse((bool) $role->can_be_advisor);
    }

    public function test_connector_can_quote_and_download_plain_only(): void
    {
        $u = $this->connector();

        $this->assertTrue($u->hasPermission('create_quotation'));
        $this->assertTrue($u->hasPermission('generate_pdf'));
        $this->assertTrue($u->hasPermission('view_own_quotations'));
        $this->assertTrue($u->hasPermission('download_pdf_plain'));

        // Must NOT have branded download, convert, or any loan access.
        $this->assertFalse($u->hasPermission('download_pdf_branded'));
        $this->assertFalse($u->hasPermission('convert_to_loan'));
        $this->assertFalse($u->hasPermission('view_loans'));
        $this->assertFalse($u->hasPermission('manage_loan_stages'));
    }

    public function test_connector_cannot_access_convert_route(): void
    {
        $u = $this->connector();

        $quotation = Quotation::create([
            'user_id' => $u->id,
            'customer_name' => 'Test Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 1000000,
            'status' => 'active',
        ]);

        $this->actingAs($u)
            ->get(route('quotations.convert', $quotation))
            ->assertForbidden();
    }

    public function test_connector_gujarati_label_present(): void
    {
        $this->assertSame('કનેક્ટર', Role::gujaratiLabels()['connector'] ?? null);
    }
}
