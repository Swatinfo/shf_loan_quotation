<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Deleting a user hard-cascades their quotations/loans (SoftDeletes is bypassed
 * by the DB cascade). The destroy guard must block deletion when the user has
 * created either, so off-boarding never silently destroys business records.
 */
class UserDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'loan_advisor'] as $slug) {
            Role::firstOrCreate(['slug' => $slug], ['name' => ucwords(str_replace('_', ' ', $slug))]);
        }
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

    public function test_cannot_delete_a_user_who_created_quotations(): void
    {
        $admin = $this->makeUser('super_admin');
        $target = $this->makeUser('loan_advisor');

        DB::table('quotations')->insert([
            'customer_name' => 'C', 'customer_type' => 'salaried', 'loan_amount' => 500000,
            'user_id' => $target->id, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('users.destroy', $target))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot delete this user because they have created quotations. Deactivate the user instead.']);

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_can_delete_a_user_with_no_records(): void
    {
        $admin = $this->makeUser('super_admin');
        $target = $this->makeUser('loan_advisor');

        $this->actingAs($admin)
            ->deleteJson(route('users.destroy', $target))
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }
}
