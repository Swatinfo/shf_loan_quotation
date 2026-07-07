<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /**
     * Register or update the current user's device (FCM) token.
     *
     * Called by the native WebView shell's JS bridge after it injects
     * `window.shfNative` and fires `shf-native-ready`. The upsert is keyed on
     * the token, so a device that was previously mapped to another user (e.g.
     * after impersonation or a shared device) gets reassigned to whoever is
     * logged in now — mirroring the Web Push subscribe behaviour.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|max:512',
            'platform' => 'required|string|in:android,ios,web',
            'sound' => 'nullable|string|max:40',
        ]);

        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'],
                'sound' => $data['sound'] ?? null,
            ],
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Remove the current user's device (FCM) token on logout / opt-out.
     *
     * Scoped to the authenticated user and the exact token so logging out on
     * one device never silences the same user's other registered devices.
     */
    public function unregister(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|max:512',
        ]);

        $request->user()->deviceTokens()
            ->where('token', $data['token'])
            ->delete();

        return response()->json(['ok' => true]);
    }
}
