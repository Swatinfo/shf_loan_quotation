<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\ActivityLog;
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
        ActivityLog::log('logout', Auth::user(), [
            'name' => Auth::user()->name,
        ]);

        $this->dropCurrentDeviceToken($request);

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
    private function dropCurrentDeviceToken(Request $request): void
    {
        $token = $request->input('device_token');
        if ($token && ($user = Auth::user())) {
            $user->deviceTokens()->where('token', $token)->delete();
        }
    }
}
