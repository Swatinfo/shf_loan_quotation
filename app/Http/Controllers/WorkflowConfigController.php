<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Bank;
use App\Models\BankStageConfig;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\ProductPayoutSlab;
use App\Models\ProductStage;
use App\Models\ProductStageUser;
use App\Models\Role;
use App\Models\Stage;
use App\Models\User;
use App\Services\LoanStageService;
use App\Services\PayoutConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkflowConfigController extends Controller
{
    public function index()
    {
        $banks = Bank::with('products')->orderBy('name')->get();
        $branches = Branch::orderBy('name')->get();
        $stages = Stage::where('is_enabled', true)->orderBy('sequence_order')->get();

        $template = 'newtheme.settings.workflow';

        return view($template, compact('banks', 'branches', 'stages') + ['pageKey' => 'settings']);
    }

    public function storeBank(Request $request)
    {
        $bankId = $request->input('id');

        $validated = $request->validate([
            'id' => 'nullable|exists:banks,id',
            'name' => 'required|string|max:255|unique:banks,name,'.($bankId ?? 'NULL'),
            'code' => 'nullable|string|max:20|unique:banks,code,'.($bankId ?? 'NULL'),
            'bank_locations' => 'nullable|array',
            'bank_locations.*' => 'exists:locations,id',
        ]);

        if ($validated['id'] ?? null) {
            $bank = Bank::findOrFail($validated['id']);
            $bank->update([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
            ]);
            $message = 'Bank updated';
        } else {
            $bank = Bank::create([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
            ]);
            $message = 'Bank created';
        }

        // Sync bank locations
        $bank->locations()->sync($request->input('bank_locations', []));

        return redirect(route('loan-settings.index').'#banks')->with('success', $message);
    }

    public function destroyBank(Bank $bank): JsonResponse
    {
        if ($bank->products()->exists()) {
            return response()->json(['error' => 'Cannot delete bank with existing products. Remove products first.'], 422);
        }

        if (LoanDetail::where('bank_id', $bank->id)->whereNull('deleted_at')->exists()) {
            return response()->json(['error' => 'Cannot delete bank with active loans.'], 422);
        }

        $bank->delete();

        return response()->json(['success' => true]);
    }

    public function storeProduct(Request $request)
    {
        // Core product identity only — payout config (slabs, cycle, PF, cap) is
        // managed on the Payout Config tab (savePayoutProduct).
        $validated = $request->validate([
            'id' => 'nullable|exists:products,id',
            'bank_id' => 'required|exists:banks,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20',
        ]);

        // Check unique (bank_id + name)
        $exists = Product::where('bank_id', $validated['bank_id'])
            ->where('name', $validated['name'])
            ->when($validated['id'] ?? null, fn ($q, $id) => $q->where('id', '!=', $id))
            ->exists();

        if ($exists) {
            return redirect()->back()->withInput()
                ->with('error', 'This product already exists for the selected bank.');
        }

        $data = [
            'bank_id' => $validated['bank_id'],
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
        ];

        if ($validated['id'] ?? null) {
            Product::findOrFail($validated['id'])->update($data);
        } else {
            Product::create($data);
        }

        return redirect(route('loan-settings.index').'#products')->with('success', 'Product saved');
    }

    /**
     * Save a product's payout config (PF flag, cap, cycle days, standard +
     * connector slabs). Moved off the product form onto the Payout Config tab —
     * identity is edited there, payout here. The posted slabs are the full state
     * (replace, not merge).
     */
    public function savePayoutProduct(Request $request, PayoutConfigService $payoutConfig)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'effective_from' => 'nullable|date',
            'is_pf_based' => 'nullable|boolean',
            'max_payout_amount' => 'nullable|numeric|decimal:0,2|min:0|max:100000000000',
            'payout_cycle_start_day' => 'nullable|integer|min:1|max:31',
            'payout_cycle_end_day' => 'nullable|integer|min:1|max:31',
            'slabs' => 'nullable|array',
            'slabs.*.low_amount' => 'required|integer|min:0',
            'slabs.*.high_amount' => 'required|integer|gt:slabs.*.low_amount',
            'slabs.*.payout_type' => 'required|in:amount,percent',
            'slabs.*.payout_value' => 'required|numeric|min:0',
            'slabs.*.connector_payout_type' => 'nullable|in:amount,percent',
            'slabs.*.connector_payout_value' => 'nullable|numeric|min:0',
        ], [
            'slabs.*.high_amount.gt' => 'Each slab\'s high range must be greater than its low range.',
        ]);

        $slabs = array_values($validated['slabs'] ?? []);

        foreach ($slabs as $i => $slab) {
            if ($slab['payout_type'] === ProductPayoutSlab::TYPE_PERCENT && $slab['payout_value'] > 100) {
                return redirect()->back()->withInput()
                    ->with('error', 'Percentage payout cannot exceed 100%.');
            }
            // Normalize connector payout (defaults: percent / 0).
            $slabs[$i]['connector_payout_type'] = $slab['connector_payout_type'] ?? ProductPayoutSlab::TYPE_PERCENT;
            $slabs[$i]['connector_payout_value'] = (float) ($slab['connector_payout_value'] ?? 0);
            if ($slabs[$i]['connector_payout_type'] === ProductPayoutSlab::TYPE_PERCENT && $slabs[$i]['connector_payout_value'] > 100) {
                return redirect()->back()->withInput()
                    ->with('error', 'Connector percentage payout cannot exceed 100%.');
            }
        }

        // Ranges must not overlap (inclusive bounds).
        usort($slabs, fn (array $a, array $b) => $a['low_amount'] <=> $b['low_amount']);
        foreach ($slabs as $i => $slab) {
            if ($i > 0 && $slab['low_amount'] <= $slabs[$i - 1]['high_amount']) {
                return redirect()->back()->withInput()
                    ->with('error', 'Payout slab ranges overlap (₹ '.inr($slab['low_amount']).' falls inside the previous slab).');
            }
        }

        $product = Product::findOrFail($validated['product_id']);

        DB::transaction(function () use ($product, $validated, $slabs, $request, $payoutConfig) {
            $payoutConfig->saveProductVersion(
                $product,
                [
                    'is_pf_based' => $request->boolean('is_pf_based'),
                    'max_payout_amount' => $validated['max_payout_amount'] ?? null,
                    'payout_cycle_start_day' => $validated['payout_cycle_start_day'] ?? 1,
                    'payout_cycle_end_day' => $validated['payout_cycle_end_day'] ?? 31,
                ],
                $slabs,
                $validated['effective_from'] ?? null,
                $request->user()?->id,
            );
        });

        return redirect(route('loan-settings.index').'#payout-config')->with('success', 'Product payout saved');
    }

    public function saveProductLocations(Request $request, Product $product)
    {
        $product->locations()->sync($request->input('locations', []));

        return redirect(route('loan-settings.index').'#products')
            ->with('success', 'Locations updated for '.$product->name);
    }

    public function productStages(Product $product)
    {
        $product->load(['bank.locations', 'locations']);
        $stages = Stage::where('is_enabled', true)->orderBy('sequence_order')->get();
        $productStages = $product->productStages()->with('branchUsers')->get()->keyBy('stage_id');
        $branches = Branch::active()->orderBy('name')->get();
        $allActiveUsers = User::selectable()
            ->whereHas('roles')
            ->with(['employerBanks', 'locations', 'roles'])
            ->orderBy('name')->get();

        // Load bank-level stage config for this product's bank
        $bankStageConfigs = BankStageConfig::where('bank_id', $product->bank_id)
            ->get()
            ->keyBy('stage_id');

        // Build resolved roles per stage and phase (bank config → master default)
        $resolvedRoles = [];
        foreach ($stages as $stage) {
            $bsc = $bankStageConfigs[$stage->id] ?? null;
            $resolvedRoles[$stage->id] = [
                'role' => $bsc?->assigned_role ?? $stage->assigned_role ?? 'task_owner',
                'phases' => [],
            ];
            if (is_array($stage->sub_actions)) {
                foreach ($stage->sub_actions as $idx => $sa) {
                    $resolvedRoles[$stage->id]['phases'][$idx] = $bsc?->phase_roles[(string) $idx]
                        ?? $sa['role'] ?? 'task_owner';
                }
            }
        }

        return view('newtheme.loan-settings.product-stages', compact('product', 'stages', 'productStages', 'branches', 'allActiveUsers', 'resolvedRoles'));
    }

    public function saveProductStages(Request $request, Product $product)
    {
        $stages = $request->input('stages', []);

        foreach ($stages as $stageData) {
            if (empty($stageData['stage_id'])) {
                continue;
            }

            // Build sub_actions_override from submitted role checkboxes + user assignments
            $subActionsOverride = null;
            if (! empty($stageData['sub_actions_override']) && is_array($stageData['sub_actions_override'])) {
                $subActionsOverride = [];
                foreach ($stageData['sub_actions_override'] as $saIdx => $saData) {
                    $saRoles = $saData['roles'] ?? [];
                    $saUsers = array_map('intval', array_filter($saData['users'] ?? []));
                    $saDefaultUser = ! empty($saData['default_user']) ? (int) $saData['default_user'] : null;
                    // Process location overrides for this sub-action
                    $saLocationOverrides = [];
                    if (! empty($saData['location_overrides']) && is_array($saData['location_overrides'])) {
                        foreach ($saData['location_overrides'] as $saLocOverride) {
                            $saLocId = $saLocOverride['location_id'] ?? null;
                            if (! $saLocId) {
                                continue;
                            }
                            $saLocationOverrides[] = [
                                'location_id' => (int) $saLocId,
                                'users' => array_map('intval', array_filter($saLocOverride['users'] ?? [])),
                                'default' => ! empty($saLocOverride['default']) ? (int) $saLocOverride['default'] : null,
                            ];
                        }
                    }

                    $subActionsOverride[(int) $saIdx] = [
                        'is_enabled' => ($saData['is_enabled'] ?? 0) ? true : false,
                        'roles' => array_values(array_intersect($saRoles, Role::pluck('slug')->toArray())),
                        'users' => $saUsers,
                        'default_user' => $saDefaultUser,
                        'location_overrides' => $saLocationOverrides,
                    ];
                }
            }

            $productStage = ProductStage::updateOrCreate(
                ['product_id' => $product->id, 'stage_id' => $stageData['stage_id']],
                [
                    'is_enabled' => ($stageData['is_enabled'] ?? 0) ? true : false,
                    'default_assignee_role' => $stageData['default_assignee_role'] ?? null,
                    'default_user_id' => $stageData['default_user_id'] ?? null,
                    'auto_skip' => ($stageData['auto_skip'] ?? 0) ? true : false,
                    'sub_actions_override' => $subActionsOverride,
                ],
            );

            // Save branch-wise multi-user assignments with default
            if (isset($stageData['branch_users']) && is_array($stageData['branch_users'])) {
                // Delete only branch-based assignments (keep location-based)
                $productStage->branchUsers()->whereNotNull('branch_id')->delete();

                foreach ($stageData['branch_users'] as $branchId => $branchData) {
                    // Support both old format (single user_id) and new format (users[] + default)
                    if (is_array($branchData) && isset($branchData['users'])) {
                        $userIds = array_filter($branchData['users'] ?? []);
                        $defaultUserId = $branchData['default'] ?? null;
                        foreach ($userIds as $userId) {
                            ProductStageUser::create([
                                'product_stage_id' => $productStage->id,
                                'branch_id' => $branchId,
                                'user_id' => $userId,
                                'is_default' => (int) $userId === (int) $defaultUserId,
                            ]);
                        }
                    } elseif ($branchData) {
                        // Backward compat: single user_id
                        ProductStageUser::create([
                            'product_stage_id' => $productStage->id,
                            'branch_id' => $branchId,
                            'user_id' => $branchData,
                            'is_default' => true,
                        ]);
                    }
                }
            }

            // Save location-based user assignments (stage-level, phase_index = null)
            if (isset($stageData['location_overrides']) && is_array($stageData['location_overrides'])) {
                // Delete only location-based, stage-level assignments
                $productStage->branchUsers()->whereNull('branch_id')->whereNotNull('location_id')->whereNull('phase_index')->delete();

                foreach ($stageData['location_overrides'] as $override) {
                    $locationId = $override['location_id'] ?? null;
                    if (! $locationId) {
                        continue;
                    }
                    $userIds = array_filter($override['users'] ?? []);
                    $defaultUserId = $override['default'] ?? null;
                    foreach ($userIds as $userId) {
                        ProductStageUser::create([
                            'product_stage_id' => $productStage->id,
                            'branch_id' => null,
                            'location_id' => $locationId,
                            'user_id' => $userId,
                            'is_default' => (int) $userId === (int) $defaultUserId,
                        ]);
                    }
                }
            }

            // Save phase-level location user assignments
            if (isset($stageData['phase_location_overrides']) && is_array($stageData['phase_location_overrides'])) {
                foreach ($stageData['phase_location_overrides'] as $phaseIdx => $phaseOverrides) {
                    // Delete existing phase-level location assignments for this phase
                    $productStage->branchUsers()->whereNull('branch_id')->whereNotNull('location_id')
                        ->where('phase_index', (int) $phaseIdx)->delete();

                    if (! is_array($phaseOverrides)) {
                        continue;
                    }
                    foreach ($phaseOverrides as $override) {
                        $locationId = $override['location_id'] ?? null;
                        if (! $locationId) {
                            continue;
                        }
                        $userIds = array_filter($override['users'] ?? []);
                        $defaultUserId = $override['default'] ?? null;
                        foreach ($userIds as $userId) {
                            ProductStageUser::create([
                                'product_stage_id' => $productStage->id,
                                'branch_id' => null,
                                'location_id' => $locationId,
                                'user_id' => $userId,
                                'is_default' => (int) $userId === (int) $defaultUserId,
                                'phase_index' => (int) $phaseIdx,
                            ]);
                        }
                    }
                }
            }
        }

        ActivityLog::log('save_product_stages', $product, [
            'product_name' => $product->name,
            'bank_name' => $product->bank->name,
        ]);

        // Push the new config onto eligible in-flight loans of this product.
        $sync = app(LoanStageService::class)->propagateConfigToEligibleLoans($product->id, null);

        $message = 'Stage configuration saved for '.$product->name;
        if ($sync['stages_reassigned'] > 0) {
            $message .= " — {$sync['stages_reassigned']} active loan stage(s) reassigned.";
        }

        return redirect(route('loan-settings.index').'#products')
            ->with('success', $message);
    }

    /**
     * "Sync Settings" — re-apply the current stage/task-owner config to every
     * eligible in-flight loan (not completed/rejected/cancelled, not disbursed),
     * one product at a time. Manual transfers are preserved.
     */
    public function syncStageConfig()
    {
        $result = app(LoanStageService::class)->propagateConfigToAllEligibleLoans();

        ActivityLog::log('sync_stage_config', null, [
            'products' => $result['products'],
            'loans_processed' => $result['loans_processed'],
            'stages_reassigned' => $result['stages_reassigned'],
        ]);

        return redirect(route('loan-settings.index').'?tab=products#products')
            ->with('success', "Settings synced across {$result['products']} product(s): {$result['stages_reassigned']} stage(s) reassigned on {$result['loans_processed']} active loan(s).");
    }

    public function storeBranch(Request $request)
    {
        $branchId = $request->input('id');

        $validated = $request->validate([
            'id' => 'nullable|exists:branches,id',
            'name' => 'required|string|max:255|unique:branches,name,'.($branchId ?? 'NULL'),
            'code' => 'nullable|string|max:20|unique:branches,code,'.($branchId ?? 'NULL'),
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'manager_id' => 'required|exists:users,id',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        if ($validated['id'] ?? null) {
            Branch::findOrFail($validated['id'])->update($validated);
        } else {
            Branch::create($validated);
        }

        return redirect(route('loan-settings.index').'#branches')->with('success', 'Branch saved');
    }

    /**
     * Save the global payout rates as effective-dated versions. The `calc`
     * decimal (value/100) is derived server-side — the UI field is read-only —
     * and the version in force on a given date drives payout math for tranches
     * disbursed on/after that date. Saving an existing date edits that version.
     */
    public function savePayoutConfig(Request $request, PayoutConfigService $payoutConfig)
    {
        $keys = ['admin_gst', 'pf_gst', 'user_tds', 'user_insurance'];

        $rules = ['payout' => 'required|array'];
        foreach ($keys as $k) {
            $rules["payout.{$k}.value"] = 'required|numeric|min:0|max:100';
            $rules["payout.{$k}.effective_from"] = 'nullable|date';
        }
        $validated = $request->validate($rules);

        $rates = [];
        foreach ($keys as $k) {
            $rates[$k] = [
                'value' => (float) $validated['payout'][$k]['value'],
                'effective_from' => $validated['payout'][$k]['effective_from'] ?? null,
            ];
        }

        $payoutConfig->saveRateVersions($rates, $request->user()?->id);

        ActivityLog::log('update_payout_config', null, ['keys' => $keys]);

        return redirect(route('loan-settings.index').'#payout-config')->with('success', 'Payout config saved');
    }

    public function destroyProduct(Product $product): JsonResponse
    {
        if (LoanDetail::where('product_id', $product->id)->whereNull('deleted_at')->exists()) {
            return response()->json(['error' => 'Cannot delete product — it has active loans.'], 422);
        }

        $product->delete();

        return response()->json(['success' => true]);
    }

    public function destroyBranch(Branch $branch): JsonResponse
    {
        if ($branch->users()->exists()) {
            return response()->json(['error' => 'Cannot delete branch with assigned users.'], 422);
        }

        if (LoanDetail::where('branch_id', $branch->id)->whereNull('deleted_at')->exists()) {
            return response()->json(['error' => 'Cannot delete branch with active loans.'], 422);
        }

        $branch->delete();

        return response()->json(['success' => true]);
    }
}
