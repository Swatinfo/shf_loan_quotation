<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\Stage;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanSettingsController extends Controller
{
    public function index()
    {
        $banks = Bank::with(['products.productStages.branchUsers', 'products.locations', 'employees', 'locations.parent'])->orderBy('name')->get();
        $branches = Branch::with('location.parent')->orderBy('name')->get();
        $stages = Stage::orderBy('sequence_order')->get(); // All stages for Stage Master tab
        $enabledStages = Stage::where('is_enabled', true)->orderBy('sequence_order')->get(); // For product config
        $activeBranches = Branch::active()->orderBy('name')->get();
        $allActiveUsers = User::where('is_active', true)
            ->whereHas('roles')
            ->with(['employerBanks', 'roles'])
            ->orderBy('name')->get();

        // Role permissions data (using new roles system)
        $loanPermissions = Permission::where('group', 'Loans')->orderBy('id')->get();
        $workflowRoles = \App\Models\Role::whereNotIn('slug', ['super_admin'])->orderBy('id')->get();
        $rolePermissions = [];
        foreach ($workflowRoles as $wfRole) {
            $rolePermissions[$wfRole->slug] = $wfRole->permissions()->pluck('permissions.id')->toArray();
        }

        return view('loan-settings.index', compact('banks', 'branches', 'stages', 'enabledStages', 'activeBranches', 'allActiveUsers', 'loanPermissions', 'rolePermissions', 'workflowRoles'));
    }

    /**
     * Save master stage default roles.
     */
    public function saveMasterStages(Request $request)
    {
        $stages = $request->input('stages', []);

        foreach ($stages as $stageData) {
            if (empty($stageData['id'])) {
                continue;
            }

            $roles = $stageData['default_role'] ?? [];
            $allRoleSlugs = \App\Models\Role::pluck('slug')->toArray();
            $validRoles = array_intersect($roles, $allRoleSlugs);

            $updateData = [
                'is_enabled' => ($stageData['is_enabled'] ?? 0) ? true : false,
                'default_role' => ! empty($validRoles) ? array_values($validRoles) : null,
            ];

            // Update sub-action roles if submitted
            if (! empty($stageData['sub_actions'])) {
                $stage = Stage::find($stageData['id']);
                if ($stage && is_array($stage->sub_actions)) {
                    $subActions = $stage->sub_actions;
                    foreach ($stageData['sub_actions'] as $saIdx => $saData) {
                        if (isset($subActions[$saIdx])) {
                            $saRoles = $saData['roles'] ?? [];
                            $subActions[$saIdx]['roles'] = array_values(array_intersect($saRoles, $allRoleSlugs));
                            $subActions[$saIdx]['is_enabled'] = ($saData['is_enabled'] ?? 0) ? true : false;
                        }
                    }
                    $updateData['sub_actions'] = $subActions;
                }
            }

            Stage::where('id', $stageData['id'])->update($updateData);

            // When a stage is disabled, also disable it in all product stage configs
            if (! $updateData['is_enabled']) {
                \App\Models\ProductStage::where('stage_id', $stageData['id'])->update(['is_enabled' => false]);
            }
        }

        return redirect()->route('loan-settings.index', ['tab' => 'master-stages'])
            ->with('success', 'Stage defaults saved');
    }

    public function storeLocation(Request $request)
    {
        $locationId = $request->input('id');

        $validated = $request->validate([
            'id' => 'nullable|exists:locations,id',
            'type' => 'required|in:state,city',
            'parent_id' => 'nullable|required_if:type,city|exists:locations,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20',
        ]);

        if ($validated['type'] === 'state') {
            $validated['parent_id'] = null;
        }

        if ($locationId) {
            \App\Models\Location::findOrFail($locationId)->update($validated);
            $message = 'Location updated';
        } else {
            \App\Models\Location::create($validated);
            $message = 'Location added';
        }

        return redirect(route('loan-settings.index').'#locations')->with('success', $message);
    }

    public function destroyLocation(\App\Models\Location $location): JsonResponse
    {
        if ($location->children()->exists()) {
            return response()->json(['error' => 'Cannot delete — has cities under it. Delete cities first.'], 422);
        }

        if (\App\Models\Branch::where('location_id', $location->id)->exists()) {
            return response()->json(['error' => 'Cannot delete — branches are assigned to this location.'], 422);
        }

        $location->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Save role permissions (Role Permissions tab).
     */
    public function saveTaskRolePermissions(Request $request)
    {
        $loanPermissionIds = Permission::where('group', 'Loans')->pluck('id')->toArray();
        $workflowRoles = \App\Models\Role::whereNotIn('slug', ['super_admin'])->get();

        foreach ($workflowRoles as $wfRole) {
            // Clear existing Loans-group role permissions
            \DB::table('role_permission')
                ->where('role_id', $wfRole->id)
                ->whereIn('permission_id', $loanPermissionIds)
                ->delete();

            $selected = $request->input("task_role.{$wfRole->slug}", []);
            foreach ($selected as $permissionId) {
                if (in_array((int) $permissionId, $loanPermissionIds)) {
                    \DB::table('role_permission')->insertOrIgnore([
                        'role_id' => $wfRole->id,
                        'permission_id' => (int) $permissionId,
                    ]);
                }
            }
        }

        app(PermissionService::class)->clearAllCaches();
        ActivityLog::log('role_permissions_updated', null, [
            'updated_by' => auth()->user()->name,
        ]);

        return redirect()->route('loan-settings.index', ['tab' => 'role-permissions'])
            ->with('success', 'Task role permissions updated.');
    }
}
