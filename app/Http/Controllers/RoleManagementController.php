<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Stage;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleManagementController extends Controller
{
    public function index()
    {
        $roles = Role::withCount('users')->orderBy('id')->get();

        $template = 'newtheme.roles.index';

        return view($template, compact('roles'));
    }

    public function create()
    {
        $existingRoles = Role::where('is_system', false)->orderBy('name')->get();

        $template = 'newtheme.roles.create';

        return view($template, compact('existingRoles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('roles', 'slug'),
            ],
            'description' => 'nullable|string|max:500',
            'can_be_advisor' => 'boolean',
            'copy_from' => 'nullable|exists:roles,id',
            'copy_permissions' => 'boolean',
            'copy_stage_eligibility' => 'boolean',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['slug'], '_'),
            'description' => $validated['description'] ?? null,
            'can_be_advisor' => $validated['can_be_advisor'] ?? false,
            'is_system' => false,
        ]);

        // Copy from existing role
        if (! empty($validated['copy_from'])) {
            $sourceRole = Role::find($validated['copy_from']);

            if ($sourceRole) {
                // Copy permissions
                if ($request->boolean('copy_permissions')) {
                    $permissionIds = $sourceRole->permissions()->pluck('permissions.id')->toArray();
                    $role->permissions()->sync($permissionIds);
                }

                // Copy stage eligibility
                if ($request->boolean('copy_stage_eligibility')) {
                    $stages = Stage::whereNotNull('default_role')->get();
                    foreach ($stages as $stage) {
                        $roles = is_array($stage->default_role) ? $stage->default_role : json_decode($stage->default_role, true) ?? [];
                        if (in_array($sourceRole->slug, $roles)) {
                            $roles[] = $role->slug;
                            $stage->update(['default_role' => array_values(array_unique($roles))]);
                        }
                    }

                    // Also copy sub_actions role eligibility
                    foreach ($stages as $stage) {
                        if (is_array($stage->sub_actions)) {
                            $subActions = $stage->sub_actions;
                            $changed = false;
                            foreach ($subActions as &$sa) {
                                if (isset($sa['roles']) && is_array($sa['roles']) && in_array($sourceRole->slug, $sa['roles'])) {
                                    $sa['roles'][] = $role->slug;
                                    $sa['roles'] = array_values(array_unique($sa['roles']));
                                    $changed = true;
                                }
                            }
                            unset($sa);
                            if ($changed) {
                                $stage->update(['sub_actions' => $subActions]);
                            }
                        }
                    }
                }
            }
        }

        Role::clearAdvisorCache();
        app(PermissionService::class)->clearAllCaches();

        ActivityLog::log('role_created', $role, [
            'name' => $role->name,
            'slug' => $role->slug,
            'copied_from' => $sourceRole->name ?? null,
        ]);

        return redirect()->route('roles.index')->with('success', "Role \"{$role->name}\" created successfully.");
    }

    public function edit(Role $role)
    {
        $permissions = Permission::all()->groupBy('group');
        $rolePermissionIds = $role->permissions()->pluck('permissions.id')->toArray();

        // Stage eligibility
        $stages = Stage::orderBy('sequence_order')->get();
        $stageEligibility = [];
        foreach ($stages as $stage) {
            $roles = is_array($stage->default_role) ? $stage->default_role : json_decode($stage->default_role, true) ?? [];
            $stageEligibility[$stage->stage_key] = in_array($role->slug, $roles);
        }

        $template = 'newtheme.roles.edit';

        return view($template, compact('role', 'permissions', 'rolePermissionIds', 'stages', 'stageEligibility'));
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('roles', 'slug')->ignore($role->id),
            ],
            'description' => 'nullable|string|max:500',
            'can_be_advisor' => 'boolean',
        ]);

        $role->update([
            'name' => $validated['name'],
            'slug' => $role->is_system ? $role->slug : Str::slug($validated['slug'], '_'),
            'description' => $validated['description'] ?? null,
            'can_be_advisor' => $validated['can_be_advisor'] ?? false,
        ]);

        // Sync permissions if submitted
        if ($request->has('permissions')) {
            $role->permissions()->sync($request->input('permissions', []));
        }

        // Sync stage eligibility if submitted
        if ($request->has('stage_eligibility')) {
            $selectedStageKeys = $request->input('stage_eligibility', []);
            $stages = Stage::all();
            foreach ($stages as $stage) {
                $roles = is_array($stage->default_role) ? $stage->default_role : json_decode($stage->default_role, true) ?? [];

                if (in_array($stage->stage_key, $selectedStageKeys)) {
                    if (! in_array($role->slug, $roles)) {
                        $roles[] = $role->slug;
                    }
                } else {
                    $roles = array_values(array_diff($roles, [$role->slug]));
                }

                $stage->update(['default_role' => ! empty($roles) ? array_values($roles) : null]);
            }
        }

        Role::clearAdvisorCache();
        app(PermissionService::class)->clearAllCaches();

        ActivityLog::log('role_updated', $role, ['name' => $role->name]);

        return redirect()->route('roles.index')->with('success', "Role \"{$role->name}\" updated successfully.");
    }

    /**
     * AJAX: Check if a role name is available (unique).
     */
    public function checkName(Request $request): \Illuminate\Http\JsonResponse
    {
        $name = $request->input('name', '');
        $excludeId = $request->integer('exclude_id');

        $query = Role::where('name', $name);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return response()->json(['available' => ! $query->exists()]);
    }
}
