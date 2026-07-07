<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStoreAssignedBanksTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin'.uniqid().'@test',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
        $user->roles()->sync(Role::where('slug', 'super_admin')->pluck('id'));

        return $user->fresh('roles');
    }

    public function test_creating_non_bank_user_with_empty_assigned_bank_succeeds(): void
    {
        $this->actingAs($this->superAdmin());

        $response = $this->post(route('users.store'), [
            'name' => 'Jane Advisor',
            'email' => 'jane.advisor@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['loan_advisor'],
            'is_active' => 1,
            'assigned_banks' => [''], // hidden bank <select> posts its empty placeholder
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'jane.advisor@test.com']);
    }

    public function test_bank_employee_with_valid_bank_is_assigned(): void
    {
        $this->actingAs($this->superAdmin());

        $bankId = \DB::table('banks')->insertGetId([
            'name' => 'Test Bank',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post(route('users.store'), [
            'name' => 'Bob Bank',
            'email' => 'bob.bank@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['bank_employee'],
            'is_active' => 1,
            'assigned_banks' => [(string) $bankId],
        ]);

        $response->assertSessionHasNoErrors();

        $user = User::where('email', 'bob.bank@test.com')->firstOrFail();
        $this->assertTrue($user->employerBanks()->where('banks.id', $bankId)->exists());
    }
}
