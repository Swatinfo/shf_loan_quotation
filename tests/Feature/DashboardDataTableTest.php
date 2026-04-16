<?php

namespace Tests\Feature;

use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDataTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function createUser(string $role = 'staff'): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createQuotation(User $user, array $overrides = []): Quotation
    {
        return Quotation::create(array_merge([
            'user_id' => $user->id,
            'customer_name' => 'Test Customer',
            'customer_type' => 'proprietor',
            'loan_amount' => 5000000,
            'pdf_filename' => 'test.pdf',
            'pdf_path' => '/tmp/test.pdf',
            'selected_tenures' => [5, 10],
        ], $overrides));
    }

    public function test_unauthenticated_user_cannot_access_quotation_data(): void
    {
        $response = $this->getJson(route('dashboard.quotation-data'));
        $response->assertStatus(401);
    }

    public function test_staff_sees_only_own_quotations(): void
    {
        $staff = $this->createUser('staff');
        $otherStaff = $this->createUser('staff');

        $this->createQuotation($staff, ['customer_name' => 'My Customer']);
        $this->createQuotation($otherStaff, ['customer_name' => 'Other Customer']);

        $response = $this->actingAs($staff)->getJson(route('dashboard.quotation-data', [
            'draw' => 1, 'start' => 0, 'length' => 20,
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.customer_name', 'My Customer');
    }

    public function test_admin_sees_all_quotations(): void
    {
        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');

        $this->createQuotation($admin, ['customer_name' => 'Admin Q']);
        $this->createQuotation($staff, ['customer_name' => 'Staff Q']);

        $response = $this->actingAs($admin)->getJson(route('dashboard.quotation-data', [
            'draw' => 1, 'start' => 0, 'length' => 20,
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 2);
    }

    public function test_super_admin_sees_all_quotations(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $staff = $this->createUser('staff');

        $this->createQuotation($superAdmin);
        $this->createQuotation($staff);

        $response = $this->actingAs($superAdmin)->getJson(route('dashboard.quotation-data', [
            'draw' => 1, 'start' => 0, 'length' => 20,
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsTotal', 2);
    }

    public function test_customer_type_filter(): void
    {
        $admin = $this->createUser('admin');

        $this->createQuotation($admin, ['customer_type' => 'proprietor']);
        $this->createQuotation($admin, ['customer_type' => 'pvt_ltd']);

        $response = $this->actingAs($admin)->getJson(route('dashboard.quotation-data', [
            'draw' => 1, 'start' => 0, 'length' => 20,
            'customer_type' => 'pvt_ltd',
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.customer_type', 'pvt_ltd');
    }

    public function test_date_range_filter(): void
    {
        $admin = $this->createUser('admin');

        $old = $this->createQuotation($admin, ['customer_name' => 'Old']);
        $old->created_at = now()->subDays(10);
        $old->save();

        $this->createQuotation($admin, ['customer_name' => 'Recent']);

        $response = $this->actingAs($admin)->getJson(route('dashboard.quotation-data', [
            'draw' => 1, 'start' => 0, 'length' => 20,
            'date_from' => now()->subDays(2)->format('Y-m-d'),
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.customer_name', 'Recent');
    }

    public function test_created_by_filter(): void
    {
        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');

        $this->createQuotation($admin, ['customer_name' => 'Admin Q']);
        $this->createQuotation($staff, ['customer_name' => 'Staff Q']);

        $response = $this->actingAs($admin)->getJson(route('dashboard.quotation-data', [
            'draw' => 1, 'start' => 0, 'length' => 20,
            'created_by' => $staff->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.customer_name', 'Staff Q');
    }

    public function test_datatables_search(): void
    {
        $admin = $this->createUser('admin');

        $this->createQuotation($admin, ['customer_name' => 'Rajesh Patel']);
        $this->createQuotation($admin, ['customer_name' => 'Suresh Shah']);

        $response = $this->actingAs($admin)->getJson(route('dashboard.quotation-data', [
            'draw' => 1, 'start' => 0, 'length' => 20,
            'search' => ['value' => 'Rajesh'],
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.customer_name', 'Rajesh Patel');
    }

    public function test_pagination(): void
    {
        $admin = $this->createUser('admin');

        for ($i = 0; $i < 5; $i++) {
            $this->createQuotation($admin, ['customer_name' => "Customer {$i}"]);
        }

        $response = $this->actingAs($admin)->getJson(route('dashboard.quotation-data', [
            'draw' => 1, 'start' => 0, 'length' => 2,
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsTotal', 5)
            ->assertJsonPath('recordsFiltered', 5);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_response_format_matches_datatables_protocol(): void
    {
        $admin = $this->createUser('admin');
        $this->createQuotation($admin);

        $response = $this->actingAs($admin)->getJson(route('dashboard.quotation-data', [
            'draw' => 3, 'start' => 0, 'length' => 20,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data' => [
                    '*' => [
                        'id', 'customer_name', 'customer_type', 'type_label',
                        'type_badge_class', 'loan_amount', 'formatted_amount',
                        'banks', 'created_by', 'date', 'date_raw',
                        'show_url', 'download_url', 'delete_url',
                    ],
                ],
            ])
            ->assertJsonPath('draw', 3);
    }

    public function test_permission_based_action_urls(): void
    {
        $staff = $this->createUser('staff');
        $this->createQuotation($staff, ['pdf_filename' => 'test.pdf']);

        $response = $this->actingAs($staff)->getJson(route('dashboard.quotation-data', [
            'draw' => 1, 'start' => 0, 'length' => 20,
        ]));

        $response->assertOk();
        $data = $response->json('data.0');

        // Staff has download_pdf by default
        $this->assertNotNull($data['show_url']);
        $this->assertNotNull($data['download_url']);
        // Staff does NOT have delete permission by default
        $this->assertNull($data['delete_url']);
        // Staff cannot see "created_by" column
        $this->assertNull($data['created_by']);
    }

    public function test_admin_has_delete_url(): void
    {
        $admin = $this->createUser('admin');
        $this->createQuotation($admin);

        $response = $this->actingAs($admin)->getJson(route('dashboard.quotation-data', [
            'draw' => 1, 'start' => 0, 'length' => 20,
        ]));

        $data = $response->json('data.0');
        $this->assertNotNull($data['delete_url']);
        $this->assertNotNull($data['created_by']);
    }

    public function test_ajax_delete_returns_json(): void
    {
        $admin = $this->createUser('admin');
        $quotation = $this->createQuotation($admin);

        $response = $this->actingAs($admin)->deleteJson(
            route('quotations.destroy', $quotation)
        );

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => 'Quotation deleted.']);

        $this->assertDatabaseMissing('quotations', ['id' => $quotation->id]);
    }

    public function test_dashboard_index_loads_without_quotation_data(): void
    {
        $admin = $this->createUser('admin');

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()
            ->assertViewHas('stats')
            ->assertViewHas('permissions')
            ->assertViewHas('users');
    }

    public function test_ordering_by_column(): void
    {
        $admin = $this->createUser('admin');

        $this->createQuotation($admin, ['customer_name' => 'Alpha']);
        $this->createQuotation($admin, ['customer_name' => 'Zeta']);

        $response = $this->actingAs($admin)->getJson(route('dashboard.quotation-data', [
            'draw' => 1, 'start' => 0, 'length' => 20,
            'order' => [['column' => 1, 'dir' => 'asc']],
        ]));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('Alpha', $data[0]['customer_name']);
        $this->assertEquals('Zeta', $data[1]['customer_name']);
    }
}
