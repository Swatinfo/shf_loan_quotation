<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User::selectable() backs every user dropdown: active users only, never
 * super_admin or admin, with the role available for the label.
 */
class UserSelectableScopeTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, bool $active = true): User
    {
        $u = User::create(['name' => ucfirst($role).' '.uniqid(), 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => $active]);
        $u->roles()->sync(Role::where('slug', $role)->pluck('id'));

        return $u->fresh('roles');
    }

    public function test_selectable_excludes_admins_super_admins_and_inactive(): void
    {
        $advisor = $this->user('loan_advisor');
        $bank = $this->user('bank_employee');
        $admin = $this->user('admin');
        $superAdmin = $this->user('super_admin');
        $inactiveAdvisor = $this->user('loan_advisor', active: false);

        $ids = User::selectable()->pluck('id');

        $this->assertTrue($ids->contains($advisor->id));
        $this->assertTrue($ids->contains($bank->id));
        $this->assertFalse($ids->contains($admin->id));
        $this->assertFalse($ids->contains($superAdmin->id));
        $this->assertFalse($ids->contains($inactiveAdvisor->id));
    }

    public function test_workflow_role_label_resolves_for_the_option_text(): void
    {
        $advisor = $this->user('loan_advisor');

        $this->assertSame(Role::where('slug', 'loan_advisor')->value('name'), $advisor->workflow_role_label);
    }
}
