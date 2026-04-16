<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\Permission;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('creator')
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(20);

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles = $this->getAllowedRoles();
        return view('users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $roles = $this->getAllowedRoles();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(array_keys($roles))],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'phone' => $validated['phone'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => auth()->id(),
        ]);

        ActivityLog::log('user_created', $user, ['role' => $user->role]);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $roles = $this->getAllowedRoles();
        $permissions = Permission::all()->groupBy('group');
        $userOverrides = UserPermission::where('user_id', $user->id)
            ->pluck('type', 'permission_id')
            ->toArray();

        return view('users.edit', compact('user', 'roles', 'permissions', 'userOverrides'));
    }

    public function update(Request $request, User $user)
    {
        // Prevent editing super_admin unless you are super_admin
        if ($user->isSuperAdmin() && !auth()->user()->isSuperAdmin()) {
            abort(403, 'Cannot edit a Super Admin account.');
        }

        $roles = $this->getAllowedRoles();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(array_keys($roles))],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'phone' => $validated['phone'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        // Handle user-specific permission overrides
        if ($request->has('permissions')) {
            $this->syncUserPermissions($user, $request->input('permissions', []));
        }

        ActivityLog::log('user_updated', $user);
        app(PermissionService::class)->clearUserCache($user);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        // Prevent deleting super_admin unless you are super_admin
        if ($user->isSuperAdmin() && !auth()->user()->isSuperAdmin()) {
            abort(403, 'Cannot delete a Super Admin account.');
        }

        ActivityLog::log('user_deleted', $user, ['name' => $user->name, 'email' => $user->email]);
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    public function toggleActive(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->update(['is_active' => !$user->is_active]);
        ActivityLog::log($user->is_active ? 'user_activated' : 'user_deactivated', $user);

        $status = $user->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "User {$status} successfully.");
    }

    /**
     * Get allowed roles based on current user's role hierarchy.
     */
    protected function getAllowedRoles(): array
    {
        $currentUser = auth()->user();

        if ($currentUser->isSuperAdmin()) {
            return [
                'super_admin' => 'Super Admin',
                'admin' => 'Admin',
                'staff' => 'Staff',
            ];
        }

        if ($currentUser->isAdmin()) {
            return [
                'admin' => 'Admin',
                'staff' => 'Staff',
            ];
        }

        return ['staff' => 'Staff'];
    }

    /**
     * Sync user-specific permission overrides.
     */
    protected function syncUserPermissions(User $user, array $permissions): void
    {
        // Delete existing overrides
        UserPermission::where('user_id', $user->id)->delete();

        foreach ($permissions as $permissionId => $type) {
            if (in_array($type, ['grant', 'deny'])) {
                UserPermission::create([
                    'user_id' => $user->id,
                    'permission_id' => $permissionId,
                    'type' => $type,
                ]);
            }
            // 'default' means no override, so skip
        }
    }
}
