<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\ShfNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends Firebase Cloud Messaging (FCM v1) pushes to a user's registered native
 * devices. Authenticates to the FCM v1 HTTP API with a service-account key by
 * minting a short-lived OAuth2 access token from a signed JWT (RS256) — no
 * external SDK required. Every public path is best-effort: failures are logged,
 * never thrown, so a push problem can't 500 the request that created the
 * notification (mirrors the Web Push rule in ShfNotification::booted()).
 */
class FcmService
{
    /** Chime preset key → bundled sound resource name (matches the Flutter app). */
    private const SOUND_RESOURCES = [
        'smooth' => 'smooth_notification',
        'cyan' => 'cyan_message',
        'luster' => 'luster',
        'mario' => 'mario_coin',
        'classic' => 'notification9',
    ];

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const TOKEN_CACHE_KEY = 'fcm_access_token';

    /**
     * True when a service-account credentials file and project id are present.
     */
    public function isConfigured(): bool
    {
        $path = config('services.fcm.credentials');

        return is_string($path) && is_file($path) && (bool) config('services.fcm.project_id');
    }

    /**
     * Push the given in-app notification to every device the recipient has
     * registered. No-op if FCM isn't configured or the user has no devices.
     * Returns diagnostics (used by the `fcm:test` command); callers that don't
     * care (the queued job) just ignore the return value.
     *
     * @return array{configured: bool, devices: int, token_ok: bool, results: list<array<string, mixed>>}
     */
    public function sendForNotification(ShfNotification $notification): array
    {
        $diagnostics = [
            'configured' => $this->isConfigured(),
            'devices' => 0,
            'token_ok' => false,
            'results' => [],
        ];

        if (! $diagnostics['configured'] || ! $notification->user_id) {
            return $diagnostics;
        }

        $devices = DeviceToken::where('user_id', $notification->user_id)->get();
        $diagnostics['devices'] = $devices->count();
        if ($devices->isEmpty()) {
            return $diagnostics;
        }

        $accessToken = $this->accessToken();
        $diagnostics['token_ok'] = (bool) $accessToken;
        if (! $accessToken) {
            return $diagnostics;
        }

        foreach ($devices as $device) {
            $diagnostics['results'][] = $this->sendToDevice($device, $notification, $accessToken);
        }

        return $diagnostics;
    }

    /**
     * @return array{token: string, status: int, ok: bool, pruned: bool, error: ?string}
     */
    private function sendToDevice(DeviceToken $device, ShfNotification $notification, string $accessToken): array
    {
        $soundKey = $device->sound ?: 'smooth';
        $hasSound = isset(self::SOUND_RESOURCES[$soundKey]);
        $channelId = $hasSound ? 'shf_sound_'.$soundKey : 'shf_default';
        $apnsSound = ($hasSound ? self::SOUND_RESOURCES[$soundKey] : 'smooth_notification').'.caf';

        $title = $notification->title ?: 'SHF World';
        $body = (string) $notification->message;
        $url = $notification->link ?: '/dashboard';

        $message = [
            'message' => [
                'token' => $device->token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => $channelId,
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => $apnsSound,
                        ],
                    ],
                ],
                'data' => [
                    'url' => $url,
                    'sound' => $soundKey,
                    'title' => $title,
                    'body' => $body,
                ],
            ],
        ];

        try {
            $projectId = config('services.fcm.project_id');
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $message);

            if ($response->successful()) {
                return ['token' => $device->token, 'status' => $response->status(), 'ok' => true, 'pruned' => false, 'error' => null];
            }

            // A stale token returns 404 (UNREGISTERED) or 400 (INVALID_ARGUMENT).
            // Prune it so we stop sending to dead devices.
            $json = $response->json();
            $errorStatus = data_get($json, 'error.status');
            $errorCode = data_get($json, 'error.details.0.errorCode') ?? $errorStatus;
            $errorMessage = data_get($json, 'error.message');
            $pruned = false;
            if ($response->status() === 404 || in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                $device->delete();
                $pruned = true;
            }

            Log::warning('FCM send failed', [
                'device_token_id' => $device->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $error = trim(($errorStatus ?: '').' '.($errorMessage ?: '')) ?: $response->body();

            return ['token' => $device->token, 'status' => $response->status(), 'ok' => false, 'pruned' => $pruned, 'error' => $error];
        } catch (\Throwable $e) {
            Log::warning('FCM send threw', [
                'device_token_id' => $device->id,
                'error' => $e->getMessage(),
            ]);

            return ['token' => $device->token, 'status' => 0, 'ok' => false, 'pruned' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cached OAuth2 access token for the FCM v1 API. Minted from the
     * service-account key via a signed JWT. Only successful tokens are cached
     * (just under their 1h lifetime) so a transient failure isn't memoised.
     */
    private function accessToken(): ?string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $credentials = $this->credentials();
        if (! $credentials) {
            return null;
        }

        $now = time();
        $jwt = $this->signJwt([
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ], $credentials['private_key']);

        if (! $jwt) {
            return null;
        }

        try {
            $response = Http::asForm()->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful() && ($token = $response->json('access_token'))) {
                Cache::put(self::TOKEN_CACHE_KEY, $token, 3300);

                return $token;
            }

            Log::warning('FCM token exchange failed', ['body' => $response->body()]);
        } catch (\Throwable $e) {
            Log::warning('FCM token exchange threw', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * @return array{client_email: string, private_key: string}|null
     */
    private function credentials(): ?array
    {
        $path = config('services.fcm.credentials');
        if (! is_string($path) || ! is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            return null;
        }

        return [
            'client_email' => $json['client_email'],
            'private_key' => $json['private_key'],
        ];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function signJwt(array $claims, string $privateKey): ?string
    {
        $segments = [
            $this->base64UrlEncode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->base64UrlEncode((string) json_encode($claims)),
        ];
        $signingInput = implode('.', $segments);

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return null;
        }
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
