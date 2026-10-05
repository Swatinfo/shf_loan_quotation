<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The custom branded 403 page renders (standalone shared error layout — no app
 * header, so it is robust in all states) and shows a clear "no permission"
 * message with a Go to Dashboard action.
 */
class Error403PageTest extends TestCase
{
    use RefreshDatabase;

    public function test_403_renders_themed_page_with_message(): void
    {
        Role::firstOrCreate(['slug' => 'loan_advisor'], ['name' => 'Loan Advisor']);
        Permission::firstOrCreate(['slug' => 'manage_workflow_config'], ['name' => 'Manage Workflow Config', 'group' => 'Loans']);

        // Define an inline route gated by a permission the user does NOT have.
        Route::middleware(['web', 'auth', 'permission:manage_workflow_config'])
            ->get('/__test_forbidden', fn () => 'ok');

        $user = User::create(['name' => 'U', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $user->roles()->sync(Role::where('slug', 'loan_advisor')->pluck('id'));

        $response = $this->actingAs($user->fresh('roles'))->get('/__test_forbidden');

        $response->assertForbidden();
        $response->assertSee('Access Denied');
        $response->assertSee("don't have permission", false);
        // Standalone branded page with a Go to Dashboard action.
        $response->assertSee('Go to Dashboard');
        $response->assertSee('href="/dashboard"', false);
    }
}
