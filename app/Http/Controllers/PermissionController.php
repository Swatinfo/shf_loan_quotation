<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Services\PermissionService;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::all()->groupBy('group');
        $roles = ['super_admin', 'admin', 'staff'];

        // Get current role-permission mappings
        $rolePermissions = [];
        foreach ($roles as $role) {
            $rolePermissions[$role] = RolePermission::where('role', $role)
                ->pluck('permission_id')
                ->toArray();
        }

        return view('permissions.index', compact('permissions', 'roles', 'rolePermissions'));
    }

    public function update(Request $request)
    {
        $roles = ['admin', 'staff']; // super_admin always has all, not editable
        $allPermissions = Permission::pluck('id')->toArray();

        foreach ($roles as $role) {
            // Clear existing
            RolePermission::where('role', $role)->delete();

            $selectedPermissions = $request->input("role.{$role}", []);

            foreach ($selectedPermissions as $permissionId) {
                if (in_array((int) $permissionId, $allPermissions)) {
                    RolePermission::create([
                        'role' => $role,
                        'permission_id' => (int) $permissionId,
                    ]);
                }
            }
        }

        // Super admin always gets all
        RolePermission::where('role', 'super_admin')->delete();
        foreach ($allPermissions as $permId) {
            RolePermission::create(['role' => 'super_admin', 'permission_id' => $permId]);
        }

        ActivityLog::log('permissions_updated', null, ['roles' => $roles]);

        $permissionService = app(PermissionService::class);
        $permissionService->clearAllCaches();

        return redirect()->route('permissions.index')->with('success', 'Permissions updated successfully.');
    }
}
