<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('newtheme.auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        ActivityLog::log('login', Auth::user(), [
            'name' => Auth::user()->name,
        ]);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {

        $user = Auth::user();

        if ($user) {
            ActivityLog::log('logout', $user, [
                'name' => $user->name,
            ]);

            $this->dropCurrentDeviceToken($request, $user);
            $this->dropCurrentPushSubscription($request, $user);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Reliable backstop for the logout-form interceptor: if the WebView shell
     * posted its FCM token along with the logout request, drop that one device's
     * token so the logged-out user stops receiving native pushes on it. Scoped
     * to the current user + exact token so other devices stay registered.
     */
    private function dropCurrentDeviceToken(Request $request, User $user): void
    {
        $token = $request->input('device_token');
        // if ($token && ($user = Auth::user())) {
        //     $user->deviceTokens()->where('token', $token)->delete();
        // }
        if ($token) {
            $user->deviceTokens()->where('token', $token)->delete();
        }
    }

    /**
     * Backstop for the logout interceptor: if the browser posted its Web Push
     * endpoint, delete that subscription server-side so the logged-out user stops
     * receiving pushes on this device even if the async /api/push/unsubscribe call
     * was dropped. Scoped to this user + exact endpoint so other devices stay
     * subscribed. (The next user's login re-keys any leftover endpoint to them.)
     */
    private function dropCurrentPushSubscription(Request $request, User $user): void
    {
        $endpoint = $request->input('push_endpoint');
        if ($endpoint) {
            $user->pushSubscriptions()->where('endpoint', $endpoint)->delete();
        }
    }
}
