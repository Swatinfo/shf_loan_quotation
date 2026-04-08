<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Stage;
use App\Models\User;
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
        $users = User::with(['branches', 'taskBank'])->where('is_active', true)->orderBy('name')->get();
        $allBranches = Branch::orderBy('name')->get();
        $activeBranches = Branch::active()->orderBy('name')->get();
        $allActiveUsers = User::where('is_active', true)
            ->whereNotNull('task_role')
            ->with('employerBanks')
            ->orderBy('name')->get();

        return view('loan-settings.index', compact('banks', 'branches', 'stages', 'enabledStages', 'users', 'allBranches', 'activeBranches', 'allActiveUsers'));
    }

    /**
     * Update user task role via AJAX (User Roles tab).
     */
    public function updateUserRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'task_role' => 'nullable|in:branch_manager,loan_advisor,bank_employee,office_employee,legal_advisor',
            'task_bank_id' => 'nullable|required_if:task_role,bank_employee|exists:banks,id',
            'employee_id' => 'nullable|string|max:50',
            'branches' => 'nullable|array',
            'branches.*' => 'exists:branches,id',
        ]);

        $user->update([
            'task_role' => $validated['task_role'] ?? null,
            'task_bank_id' => ($validated['task_role'] ?? null) === 'bank_employee' ? ($validated['task_bank_id'] ?? null) : null,
            'employee_id' => $validated['employee_id'] ?? null,
        ]);

        // Sync branches
        if (isset($validated['branches'])) {
            $user->branches()->sync($validated['branches']);
        } else {
            $user->branches()->detach();
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'task_role' => $user->task_role,
                'task_role_label' => $user->task_role_label,
                'task_bank_id' => $user->task_bank_id,
                'employee_id' => $user->employee_id,
            ],
        ]);
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
            $validRoles = array_intersect($roles, User::TASK_ROLES);

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
                            $subActions[$saIdx]['roles'] = array_values(array_intersect($saRoles, User::TASK_ROLES));
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

        return redirect(route('loan-settings.index') . '#locations')->with('success', $message);
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
}
