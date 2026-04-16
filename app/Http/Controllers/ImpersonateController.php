<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonateController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        if (! $request->user()->canImpersonate()) {
            abort(403);
        }

        $search = $request->get('search', '');

        $users = User::where('id', '!=', auth()->id())
            ->whereDoesntHave('roles', fn ($q) => $q->where('slug', 'super_admin'))
            ->where('is_active', true)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->with('roles:id,name,slug')
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json($users);
    }

    /**
     * Take impersonation with smart redirect.
     * Tries to stay on the referring page if the impersonated user can access it.
     */
    public function take(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        if (! $request->user()->canImpersonate()) {
            abort(403);
        }

        if (! $user->canBeImpersonated()) {
            abort(403, 'This user cannot be impersonated.');
        }

        // Store the referring page before impersonating
        $referrer = $request->header('Referer', url('/dashboard'));
        $referrerPath = parse_url($referrer, PHP_URL_PATH) ?? '/dashboard';

        // Impersonate the user
        auth()->user()->impersonate($user);

        // Check if the impersonated user can access the referring page
        if ($this->canAccessPath($user, $referrerPath)) {
            return redirect($referrer);
        }

        return redirect('/dashboard');
    }

    /**
     * Leave impersonation with smart redirect.
     * Returns to the current page if the original user can access it.
     */
    public function leave(Request $request): RedirectResponse
    {
        $referrer = $request->header('Referer', url('/dashboard'));
        $referrerPath = parse_url($referrer, PHP_URL_PATH) ?? '/dashboard';

        // Leave impersonation (restores original user)
        auth()->user()->leaveImpersonation();

        $originalUser = auth()->user();

        // Check if original user can access the referring page
        if ($this->canAccessPath($originalUser, $referrerPath)) {
            return redirect($referrer);
        }

        return redirect('/dashboard');
    }

    /**
     * Check if a user can access a given path based on route permissions.
     */
    private function canAccessPath(User $user, string $path): bool
    {
        // Super admin can access everything
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Map path prefixes to required permissions
        $permissionMap = [
            '/users' => 'view_users',
            '/settings' => 'view_settings',
            '/permissions' => 'manage_permissions',
            '/roles' => 'manage_permissions',
            '/loan-settings' => 'manage_workflow_config',
            '/loans' => 'view_loans',
            '/quotations' => 'create_quotation',
            '/activity-log' => 'view_activity_log',
            '/notifications' => null, // all authenticated users
            '/profile' => null, // all authenticated users
            '/dashboard' => null, // all authenticated users
        ];

        foreach ($permissionMap as $prefix => $permission) {
            if (str_starts_with($path, $prefix)) {
                return $permission === null || $user->hasPermission($permission);
            }
        }

        return true; // Unknown paths — allow (will 403 naturally if no access)
    }
}
