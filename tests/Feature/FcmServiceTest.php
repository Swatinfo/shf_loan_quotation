<?php

namespace Tests\Feature;

use App\Jobs\SendFcmPush;
use App\Models\DeviceToken;
use App\Models\ShfNotification;
use App\Models\User;
use App\Notifications\ShfPushNotification;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FcmServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $credentialsPath;

    /**
     * A throwaway 2048-bit RSA key, embedded so JWT signing is real without
     * runtime keygen (openssl_pkey_new needs an openssl.cnf that isn't present
     * on every dev/CI box; openssl_sign with a provided key does not).
     */
    private const TEST_PRIVATE_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDOyrqa1a9tFUxJ
rA+4rzBVUF3EMabPXGS1mewK/NzPf7kAV8luQM0V5eclU4YeY//75FpC7S3pfhGa
7QulMZcngPECISc/vnHZLw6yLCGdzNGymMp/MWxmCwyo/L6dFWz5HfuCmRzcVKoz
6uV3ZxBjRsxRybwMlRzdqVdUHnTlIUWXn410eq3J4unu3Mx9eId7bQb1rU3CQa5a
xRj/bxa4J6wyqONOhK4B73lev3DBj6DEwAdXixbWpjCUT9z2uoS+QZWreQeqy8hf
X9PlURt27i864nxbbRjcF8Q6sMd26snlosyuNorSWai0C4jppdx8jSg3XHySybSb
Sza9/xjPAgMBAAECggEAEWfkznnHbJxn0b9FVxhYrnDd/tCzetQkkYwvEmFK6fF7
h9XCMy3W9Vpm59az1pgFNaJFaxYd3X+9vJ23DR5AKLrEm0phTHWO89oI1+9saItg
Af/nv4GdLBB1bgq3T29WJYVw15yXd//T9oKPJcs+u/rhrmd9roA+/NtiaeBJNF+M
D1UpHWOZy22l3g/HU5owSba7MMjlJ9zknBEXq4pXEliX3bhaSJpW+GKcZE/alE9l
eHRUHxUjxnniIWB++W3tnJN+loOlJGS4HQYAlRviFaKgUBren0JTReJ8RY/Kpc8L
wLe3z9vPwNgHdFQa0D9ZAaNMuIQfv1qTl/rpZQ/MYQKBgQD1CiiVBQv9rwa0Kg/o
GLhnifKSQxM21wtX5KjijrSthbeDVC7flh5r+b1tgKO3x/4AluhZVHeDmOvKvDK4
SZD0Exk8AiseijEjAYfD11uzCErvMTS7R05cBUo3i6eftDT7QcqPew9KDM2Y5rQT
Wz3vBEQbSvxbxXgEjCrhP9P0LwKBgQDYCpyst+zrD2jNksB+FWM50fnLWYu4QgQZ
CaehLoIbuDqo99xingZVgh1XGAJjjbHcjazFqAVzVu8mP6YQFFO5jJMTgY8e1V1e
Xqj46j6j0v1bsNrTByDX1FcxXCvmhmO0PrDRnQlbQjyaJ8YlEF+GeQlw6cGKQEn4
98rKmY3dYQKBgG0HIBduL2+ouOrg7ELw/NvU3UGG5r234SwyaZKkvG+Y15ZvlouV
V+PjMw3N9wqGydWpT6ivlJ3RJNkH8+lbkkuHU+sHa/gitMpGnAnfgMWgIvdahYPu
rkbzc5pTYToGDKzfESnWL29bUv99ZxJVvrDizQr3ymFq84PSeiHPLJexAoGAF+3c
+riIkmSrjBGUJqMB0ZazX1W0xxzwzUEngw/es2lNfgeuLnIoa3I1A7+SDCrWp4I9
v8rjh6n6ZTcpkqBdwbCTXda83oJtjBVaC/AOLiEhlPVa14LznVRUsA/wgOEjzAQh
m6YUMjp88eoz5UkR+5gOdqY9nK2QQ/ZWTOiDMSECgYEAmzmFlPgJozKRQzkTC9PZ
M15gFjn5QDHOe/j0my2DQJG+R94ZNzOjqpw1VV6QgjSxi3LkSNa4VXMO413emxcQ
FDc5/AONLui5Y3lXdxo1qX7e7p0RWAWdnJ9/6+RZkpzhrCAd/x1w4uWO4VJqsy2l
eUH8jmv2clOILjfJjTqyu0s=
-----END PRIVATE KEY-----
PEM;

    protected function setUp(): void
    {
        parent::setUp();

        $this->credentialsPath = tempnam(sys_get_temp_dir(), 'fcm').'.json';
        file_put_contents($this->credentialsPath, json_encode([
            'type' => 'service_account',
            'project_id' => 'test-project',
            'client_email' => 'svc@test-project.iam.gserviceaccount.com',
            'private_key' => self::TEST_PRIVATE_KEY,
        ]));

        config([
            'services.fcm.project_id' => 'test-project',
            'services.fcm.credentials' => $this->credentialsPath,
        ]);
        Cache::forget('fcm_access_token');
    }

    protected function tearDown(): void
    {
        @unlink($this->credentialsPath);
        parent::tearDown();
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U'.uniqid(),
            'email' => uniqid().'@test',
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);
    }

    private function fakeTokenAndSend(array $sendResponse = ['name' => 'projects/test-project/messages/1'], int $sendStatus = 200): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.fake', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response($sendResponse, $sendStatus),
        ]);
    }

    public function test_sends_fcm_to_each_device_with_the_matching_sound_channel(): void
    {
        $this->fakeTokenAndSend();
        $user = $this->makeUser();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok-mario', 'platform' => 'android', 'sound' => 'mario']);

        $notification = new ShfNotification([
            'user_id' => $user->id,
            'title' => 'Stage Assigned',
            'message' => 'You have a new stage.',
            'link' => '/loans/5/stages',
        ]);
        $notification->user_id = $user->id;

        app(FcmService::class)->sendForNotification($notification);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'messages:send')) {
                return false;
            }
            $msg = $request->data()['message'];

            return $msg['token'] === 'tok-mario'
                && $msg['android']['notification']['channel_id'] === 'shf_sound_mario'
                && $msg['data']['url'] === '/loans/5/stages'
                && $msg['data']['sound'] === 'mario';
        });
    }

    public function test_unregistered_token_is_pruned(): void
    {
        $this->fakeTokenAndSend(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404);
        $user = $this->makeUser();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'dead-token', 'platform' => 'android', 'sound' => 'smooth']);

        $notification = new ShfNotification(['user_id' => $user->id, 'title' => 'X', 'message' => 'Y']);
        $notification->user_id = $user->id;

        app(FcmService::class)->sendForNotification($notification);

        $this->assertDatabaseMissing('device_tokens', ['token' => 'dead-token']);
    }

    public function test_no_send_when_not_configured(): void
    {
        config(['services.fcm.credentials' => '/path/does/not/exist.json']);
        Http::fake();
        $user = $this->makeUser();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok', 'platform' => 'android', 'sound' => 'smooth']);

        $notification = new ShfNotification(['user_id' => $user->id, 'title' => 'X', 'message' => 'Y']);
        $notification->user_id = $user->id;

        app(FcmService::class)->sendForNotification($notification);

        Http::assertNothingSent();
    }

    public function test_no_send_when_user_has_no_devices(): void
    {
        $this->fakeTokenAndSend();
        $user = $this->makeUser();

        $notification = new ShfNotification(['user_id' => $user->id, 'title' => 'X', 'message' => 'Y']);
        $notification->user_id = $user->id;

        app(FcmService::class)->sendForNotification($notification);

        Http::assertNothingSent();
    }

    public function test_creating_a_notification_dispatches_the_queued_job(): void
    {
        Queue::fake();
        $user = $this->makeUser();

        $notification = ShfNotification::create(['user_id' => $user->id, 'title' => 'X', 'message' => 'Y']);

        Queue::assertPushed(SendFcmPush::class, fn ($job) => $job->notificationId === $notification->id);
    }

    public function test_no_job_dispatched_when_unconfigured(): void
    {
        Queue::fake();
        config(['services.fcm.credentials' => '/path/does/not/exist.json']);
        $user = $this->makeUser();

        ShfNotification::create(['user_id' => $user->id, 'title' => 'X', 'message' => 'Y']);

        Queue::assertNotPushed(SendFcmPush::class);
    }

    public function test_web_push_is_skipped_when_user_has_a_native_device(): void
    {
        Notification::fake();
        Queue::fake();
        $user = $this->makeUser();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok-native', 'platform' => 'android', 'sound' => 'smooth']);

        ShfNotification::create(['user_id' => $user->id, 'title' => 'X', 'message' => 'Y']);

        Notification::assertNothingSentTo($user);
        Queue::assertPushed(SendFcmPush::class);
    }

    public function test_web_push_is_sent_when_user_has_no_native_device(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        ShfNotification::create(['user_id' => $user->id, 'title' => 'X', 'message' => 'Y']);

        Notification::assertSentTo($user, ShfPushNotification::class);
    }

    public function test_end_to_end_create_notification_sends_fcm_via_job(): void
    {
        // Queue is `sync` under phpunit, so the dispatched job runs inline —
        // this exercises the whole chain: created() → SendFcmPush → FcmService.
        $this->fakeTokenAndSend();
        $user = $this->makeUser();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok-e2e', 'platform' => 'android', 'sound' => 'classic']);

        ShfNotification::create([
            'user_id' => $user->id,
            'title' => 'Disbursement',
            'message' => 'Loan disbursed.',
            'link' => '/loans/9/stages',
        ]);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'messages:send')) {
                return false;
            }
            $msg = $request->data()['message'];

            return $msg['token'] === 'tok-e2e'
                && $msg['android']['notification']['channel_id'] === 'shf_sound_classic';
        });
    }
}
