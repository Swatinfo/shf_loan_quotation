<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutDeviceTokenCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Device User',
            'email' => 'device'.uniqid().'@test.com',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
    }

    public function test_logout_drops_only_the_posted_device_token(): void
    {
        $user = $this->makeUser();
        $thisDevice = DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'phone-token',
            'platform' => 'android',
        ]);
        $otherDevice = DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'tablet-token',
            'platform' => 'android',
        ]);

        $this->actingAs($user)
            ->post(route('logout'), ['device_token' => 'phone-token'])
            ->assertRedirect('/');

        $this->assertDatabaseMissing('device_tokens', ['id' => $thisDevice->id]);
        $this->assertDatabaseHas('device_tokens', ['id' => $otherDevice->id]);
    }

    public function test_logout_without_device_token_keeps_tokens(): void
    {
        $user = $this->makeUser();
        $device = DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'phone-token',
            'platform' => 'android',
        ]);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->assertDatabaseHas('device_tokens', ['id' => $device->id]);
    }

    public function test_unregister_endpoint_deletes_only_current_users_token(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        DeviceToken::create(['user_id' => $user->id, 'token' => 'mine', 'platform' => 'android']);
        DeviceToken::create(['user_id' => $other->id, 'token' => 'theirs', 'platform' => 'android']);

        $this->actingAs($user)
            ->postJson(route('device.unregister'), ['token' => 'mine'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('device_tokens', ['token' => 'mine']);
        // A user cannot delete another user's token even if they know it.
        $this->actingAs($user)
            ->postJson(route('device.unregister'), ['token' => 'theirs'])
            ->assertOk();
        $this->assertDatabaseHas('device_tokens', ['token' => 'theirs']);
    }
}
