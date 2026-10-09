<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationAuditTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $roleSlug, string $name): User
    {
        $user = User::create([
            'name' => $name,
            'email' => strtolower($name).uniqid().'@test',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
        $user->roles()->sync(Role::where('slug', $roleSlug)->pluck('id'));

        return $user->fresh('roles');
    }

    public function test_take_impersonation_logs_with_original_user_id(): void
    {
        $admin = $this->makeUser('super_admin', 'Admin');
        $target = $this->makeUser('loan_advisor', 'Target');

        $this->actingAs($admin);
        $admin->impersonate($target);

        $log = ActivityLog::where('description', 'impersonate_start')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->properties['original_user_id']);
        $this->assertSame('Admin', $log->properties['impersonator_name']);
        $this->assertSame('Target', $log->properties['impersonated_name']);
    }

    public function test_leave_impersonation_logs_end_event(): void
    {
        $admin = $this->makeUser('super_admin', 'Admin');
        $target = $this->makeUser('loan_advisor', 'Target');

        $this->actingAs($admin);
        $admin->impersonate($target);
        auth()->user()->leaveImpersonation();

        $log = ActivityLog::where('description', 'impersonate_end')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->properties['original_user_id']);
    }

    public function test_actions_during_impersonation_capture_impersonator_id(): void
    {
        $admin = $this->makeUser('super_admin', 'Admin');
        $target = $this->makeUser('loan_advisor', 'Target');

        $this->actingAs($admin);
        $admin->impersonate($target);

        ActivityLog::log('test_action');

        $log = ActivityLog::where('description', 'test_action')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($target->id, $log->user_id);
        $this->assertSame($admin->id, $log->properties['impersonator_id']);
    }

    public function test_actions_without_impersonation_have_no_impersonator_id(): void
    {
        $user = $this->makeUser('loan_advisor', 'Plain');

        $this->actingAs($user);
        ActivityLog::log('test_action');

        $log = ActivityLog::where('description', 'test_action')->latest('id')->first();
        $this->assertNotNull($log);
        $props = $log->properties?->toArray() ?? [];
        $this->assertArrayNotHasKey('impersonator_id', $props);
    }

    public function test_impersonation_picker_includes_admins_but_not_super_admins(): void
    {
        $superAdmin = $this->makeUser('super_admin', 'Super');
        $otherAdmin = $this->makeUser('admin', 'Admin Two');
        $advisor = $this->makeUser('loan_advisor', 'Advisor');

        $ids = collect($this->actingAs($superAdmin)
            ->getJson(route('impersonate.users'))
            ->assertOk()
            ->json())
            ->pluck('id');

        $this->assertTrue($ids->contains($otherAdmin->id), 'admins should be impersonation-searchable');
        $this->assertTrue($ids->contains($advisor->id));
        $this->assertFalse($ids->contains($superAdmin->id), 'super_admins must never be listed');
    }
}
