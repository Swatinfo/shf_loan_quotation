<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Logout server-side backstop: a posted push_endpoint drops that Web Push
 * subscription so the next user on the device gets only their own pushes.
 */
class LogoutPushCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'U-'.uniqid(), 'email' => uniqid().'@t.local',
            'password' => bcrypt('x'), 'is_active' => true,
        ]);
    }

    public function test_logout_deletes_the_posted_push_subscription(): void
    {
        $user = $this->user();
        $endpoint = 'https://push.example.com/'.uniqid();
        $user->updatePushSubscription($endpoint, 'test-public-key', 'test-auth-token');

        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => $endpoint]);

        $this->actingAs($user)
            ->post(route('logout'), ['push_endpoint' => $endpoint])
            ->assertRedirect('/');

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => $endpoint]);
    }

    public function test_logout_without_endpoint_leaves_other_subscriptions(): void
    {
        $user = $this->user();
        $endpoint = 'https://push.example.com/'.uniqid();
        $user->updatePushSubscription($endpoint, 'k', 't');

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect('/');

        // No endpoint posted → the subscription is untouched by the backstop.
        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => $endpoint]);
    }
}
