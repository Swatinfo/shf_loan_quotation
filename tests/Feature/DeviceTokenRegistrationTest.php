<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The native WebView shell registers its FCM device token against the
 * logged-in user via POST /api/device/register. The upsert is keyed on the
 * token so the same device reassigns to whoever is currently authenticated.
 */
class DeviceTokenRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U'.uniqid(),
            'email' => uniqid().'@test',
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);
    }

    public function test_authenticated_user_can_register_a_device_token(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/device/register', [
                'token' => 'fcm-token-abc',
                'platform' => 'android',
                'sound' => 'smooth',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-token-abc',
            'platform' => 'android',
            'sound' => 'smooth',
        ]);
    }

    public function test_registering_the_same_token_upserts_and_reassigns_the_user(): void
    {
        $first = $this->makeUser();
        $second = $this->makeUser();

        $this->actingAs($first)->postJson('/api/device/register', [
            'token' => 'shared-device',
            'platform' => 'android',
            'sound' => 'smooth',
        ])->assertOk();

        $this->actingAs($second)->postJson('/api/device/register', [
            'token' => 'shared-device',
            'platform' => 'android',
            'sound' => 'mario',
        ])->assertOk();

        $this->assertSame(1, DeviceToken::where('token', 'shared-device')->count());
        $this->assertDatabaseHas('device_tokens', [
            'token' => 'shared-device',
            'user_id' => $second->id,
            'sound' => 'mario',
        ]);
    }

    public function test_invalid_platform_is_rejected(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/device/register', [
                'token' => 'fcm-token-xyz',
                'platform' => 'windows-phone',
            ])
            ->assertStatus(422);
    }

    public function test_guests_cannot_register(): void
    {
        $this->postJson('/api/device/register', [
            'token' => 'fcm-token-guest',
            'platform' => 'android',
        ])->assertStatus(401);
    }
}
