# Stage J: Settings Restructure + Polish

## Overview

Restructures the settings navigation into "Quotation Settings" (existing) and "Loan Settings" (new). Also audits permissions, adds CSS/JS, and writes tests.

## Dependencies

- All previous stages (A through I)

---

## Settings Restructure

### Current State
- One "Settings" page at `/settings` with 8 tabs (Company, Banks, IOM, Bank Charges, GST, Services, Tenures, Documents)
- Separate workflow config page at `/settings/workflow` (Banks, Products, Branches)
- Banks exist in TWO places: config-based list (quotation dropdown) + `banks` table (loan system)

### New Structure

**Navigation** (before → after):
```
Before: Dashboard | New Quotation | Loans | Users | Settings         | Permissions | Activity Log
After:  Dashboard | New Quotation | Loans | Users | Quotation Settings | Loan Settings | Permissions | Activity Log
```

### Step 1: Rename Settings → Quotation Settings
- **Nav**: Change label in `navigation.blade.php` (desktop "Settings" → "Quotation Settings", mobile same)
- **Page**: Change title in `settings/index.blade.php` header
- **Route**: No change (`/settings` stays)
- **Permission**: No change (`view_settings` still controls it)

### Step 2: Create Loan Settings Page

**Route**: `GET /loan-settings` → `LoanSettingsController@index`
**Permission**: `view_loans` (any loan user can view, edit actions require `manage_workflow_config`)

**View**: `resources/views/loan-settings/index.blade.php` with 5 tabs:

| Tab | Content | Edit Permission |
|-----|---------|-----------------|
| Banks | `banks` table CRUD (shared with quotation). Add/edit/delete. "Sync to Quotation Config" button. | `manage_workflow_config` |
| Branches | Branch CRUD (name, code, city, phone). Moved from workflow.blade.php | `manage_workflow_config` |
| Products | Per-bank product list. Add product. "Configure Stages" link per product. | `manage_workflow_config` |
| Stage Config | Summary of products with custom stage configs. Links to per-product config page. | `manage_workflow_config` |
| User Roles | Table of users showing task_role, bank, branches. Edit via modal: task_role dropdown, task_bank_id (for bank_employee), employee_id, branch multi-select. | `manage_workflow_config` |

### Step 3: Banks Synchronization
- Quotation Settings "Banks" tab manages the **config-based bank list** (stored in app_config JSON, used by quotation create form)
- Loan Settings "Banks" tab manages the **`banks` table** (used by loan system for FK references)
- These are separate stores but should stay in sync
- Add "Sync from Banks Table" button on Quotation Settings → Banks tab: pulls bank names from `banks` table into config list

### Step 4: Move Workflow Config
- Remove standalone `/settings/workflow` route and `settings/workflow.blade.php` view
- All content (banks, branches, products) now lives in Loan Settings tabs
- `settings/workflow-product-stages.blade.php` stays as separate page, breadcrumb updated to "Loan Settings > Products > [Product] > Stage Config"

### Step 5: User Roles Tab
Instead of adding task_role fields to user create/edit forms, all task role management is centralized in the Loan Settings "User Roles" tab:

```
┌────────────────────────────────────────────────────────────────┐
│ User Roles                                                      │
├──────────┬──────────────┬──────────┬───────────┬───────────────┤
│ User     │ System Role  │ Task Role│ Bank      │ Branches      │
├──────────┼──────────────┼──────────┼───────────┼───────────────┤
│ Admin    │ super_admin  │ —        │ —         │ —             │
│ Ramesh   │ staff        │ Loan Adv │ —         │ Rajkot        │
│ Amit     │ staff        │ Bank Emp │ HDFC Bank │ —             │
│ Priya    │ staff        │ Office   │ —         │ Rajkot, Surat │
│ [Edit]   │              │          │           │               │
└──────────┴──────────────┴──────────┴───────────┴───────────────┘
```

Edit via AJAX modal: task_role dropdown, task_bank_id (shown only for bank_employee), employee_id, branch multi-select checkboxes.

### Controller: LoanSettingsController

```php
class LoanSettingsController extends Controller
{
    public function index()  // GET /loan-settings — tabbed page
    public function updateUserRole(Request $request, User $user): JsonResponse  // POST /loan-settings/users/{user}/role
}
```

Other actions (bank/branch/product/stage CRUD) reuse existing `WorkflowConfigController` methods with updated routes.

---

## 1. Complete Permission Audit

### Final Permission List (11 new under "Loans" group)

```php
// In config/permissions.php
'Loans' => [
    ['slug' => 'convert_to_loan', 'name' => 'Convert to Loan', 'description' => 'Convert quotation to loan task'],
    ['slug' => 'view_loans', 'name' => 'View Loans', 'description' => 'View loan task list'],
    ['slug' => 'view_all_loans', 'name' => 'View All Loans', 'description' => 'View all loans across users/branches'],
    ['slug' => 'create_loan', 'name' => 'Create Loan', 'description' => 'Create loan tasks directly'],
    ['slug' => 'edit_loan', 'name' => 'Edit Loan', 'description' => 'Edit loan details'],
    ['slug' => 'delete_loan', 'name' => 'Delete Loan', 'description' => 'Delete loan tasks'],
    ['slug' => 'manage_loan_documents', 'name' => 'Manage Loan Documents', 'description' => 'Mark documents as received/pending, add/remove documents'],
    ['slug' => 'manage_loan_stages', 'name' => 'Manage Loan Stages', 'description' => 'Update stage status and assignments'],
    ['slug' => 'skip_loan_stages', 'name' => 'Skip Loan Stages', 'description' => 'Skip stages in loan workflow'],
    ['slug' => 'add_remarks', 'name' => 'Add Remarks', 'description' => 'Add remarks to loan stages'],
    ['slug' => 'manage_workflow_config', 'name' => 'Manage Workflow Config', 'description' => 'Configure banks, products, branches, and stage workflows'],
],
```

### Role Default Matrix

| Permission | super_admin | Admin | Staff |
|------------|:-----------:|:-----:|:-----:|
| convert_to_loan | bypass | yes | yes |
| view_loans | bypass | yes | yes |
| view_all_loans | bypass | yes | no |
| create_loan | bypass | yes | yes |
| edit_loan | bypass | yes | no |
| delete_loan | bypass | yes | no |
| manage_loan_documents | bypass | yes | yes |
| manage_loan_stages | bypass | yes | yes |
| skip_loan_stages | bypass | yes | no |
| add_remarks | bypass | yes | yes |
| manage_workflow_config | bypass | yes | no |

**Total permissions after integration**: 18 existing + 11 new = **29 permissions**

---

## 2. Complete ActivityLog Coverage

Every mutation across the entire loan system must call `ActivityLog::log()`.

### Activity Log Actions

| Action String | Subject Model | Properties | Triggered By |
|---------------|--------------|------------|--------------|
| `convert_quotation_to_loan` | LoanDetail | quotation_id, loan_number, customer_name, loan_amount, bank_name | LoanConversionService |
| `create_loan` | LoanDetail | customer_name, loan_amount, bank_name | LoanConversionService |
| `edit_loan` | LoanDetail | changed_fields (array of field names changed) | LoanController |
| `delete_loan` | null | loan_number, customer_name | LoanController |
| `change_loan_status` | LoanDetail | old_status, new_status | LoanController |
| `mark_document_received` | LoanDocument | document_name, loan_number | LoanDocumentService |
| `unmark_document_received` | LoanDocument | document_name, loan_number | LoanDocumentService |
| `add_loan_document` | LoanDocument | document_name, loan_number | LoanDocumentService |
| `remove_loan_document` | LoanDocument | document_name, loan_number | LoanDocumentService |
| `update_stage_status` | StageAssignment | loan_number, stage_key, old_status, new_status | LoanStageService |
| `assign_stage` | StageAssignment | loan_number, stage_key, assigned_to_name | LoanStageService |
| `skip_stage` | StageAssignment | loan_number, stage_key | LoanStageService |
| `parallel_stages_completed` | LoanDetail | loan_number, advanced_to | LoanStageService |
| `save_valuation` | ValuationDetail | loan_number, valuation_type | LoanValuationController |
| `process_disbursement` | DisbursementDetail | loan_number, type, amount | DisbursementService |
| `save_disbursement_pending_otc` | DisbursementDetail | loan_number, otc_branch | DisbursementService |
| `otc_cleared` | DisbursementDetail | loan_number | DisbursementService |
| `add_remark` | Remark | loan_number, stage_key, preview | RemarkService |
| `save_product_stages` | Product | product_name, bank_name | WorkflowConfigController |

### Verify Existing ActivityLog Pattern

All logs follow the existing pattern:
```php
ActivityLog::log(string $action, ?Model $subject, array $properties);
```

Ensure polymorphic `subject_type` and `subject_id` are correctly set for new models.

---

## 3. Scope Enforcement

### LoanDetail::scopeVisibleTo (Final Version)

```php
public function scopeVisibleTo($query, User $user): void
{
    // Super admin bypass is handled by PermissionService
    if ($user->hasPermission('view_all_loans')) {
        return; // No restriction — sees all
    }

    $query->where(function ($q) use ($user) {
        $q->where('created_by', $user->id)              // I created it
          ->orWhere('assigned_advisor', $user->id)       // I'm the advisor
          ->orWhereHas('stageAssignments', function ($sq) use ($user) {
              $sq->where('assigned_to', $user->id);      // I'm assigned to a stage
          });
    });
}
```

### Apply to All Loan Controllers

Every controller that lists or displays loans must use this scope:

| Controller | Method | Must Use Scope |
|------------|--------|---------------|
| `LoanController` | `index` / `loanData` | `LoanDetail::visibleTo(auth()->user())` |
| `LoanController` | `show` / `edit` | Authorization check via `authorizeView()` |
| `LoanDocumentController` | `index` | Verify loan is visible |
| `LoanStageController` | `index` | Verify loan is visible |
| `LoanRemarkController` | `index` / `store` | Verify loan is visible |
| `LoanDisbursementController` | `show` / `store` | Verify loan is visible |
| `LoanValuationController` | `show` / `store` | Verify loan is visible |

### Common Authorization Helper

Add to a base trait or each controller:

```php
private function authorizeView(LoanDetail $loan): void
{
    $user = auth()->user();
    if ($user->hasPermission('view_all_loans')) return;
    if ($loan->created_by === $user->id) return;
    if ($loan->assigned_advisor === $user->id) return;
    if ($loan->stageAssignments()->where('assigned_to', $user->id)->exists()) return;
    abort(403);
}
```

---

## 4. User Management Enhancement

### Modify User Create/Edit Views

**Files**: `resources/views/users/create.blade.php`, `resources/views/users/edit.blade.php`

Add fields after existing role dropdown:

```blade
{{-- Task Role (for loan workflow) --}}
<div class="mb-3">
    <label class="form-label">Task Role (Loan Workflow)</label>
    <select name="task_role" class="form-select">
        <option value="">-- None (quotation only) --</option>
        <option value="branch_manager" {{ old('task_role', $user->task_role ?? '') === 'branch_manager' ? 'selected' : '' }}>
            Branch Manager
        </option>
        <option value="loan_advisor" {{ old('task_role', $user->task_role ?? '') === 'loan_advisor' ? 'selected' : '' }}>
            Loan Advisor
        </option>
        <option value="bank_employee" {{ old('task_role', $user->task_role ?? '') === 'bank_employee' ? 'selected' : '' }}>
            Bank Employee
        </option>
        <option value="office_employee" {{ old('task_role', $user->task_role ?? '') === 'office_employee' ? 'selected' : '' }}>
            Office Employee
        </option>
        <option value="legal_advisor" {{ old('task_role', $user->task_role ?? '') === 'legal_advisor' ? 'selected' : '' }}>
            Legal Advisor
        </option>
    </select>
</div>

{{-- Bank (only for bank_employee) --}}
<div class="mb-3" id="taskBankField" style="display:none;">
    <label class="form-label">Bank <span class="text-danger">*</span></label>
    <select name="task_bank_id" class="form-select">
        <option value="">-- Select Bank --</option>
        @foreach($banks as $bank)
            <option value="{{ $bank->id }}"
                {{ old('task_bank_id', $user->task_bank_id ?? '') == $bank->id ? 'selected' : '' }}>
                {{ $bank->name }}
            </option>
        @endforeach
    </select>
</div>

{{-- Employee ID --}}
<div class="mb-3">
    <label class="form-label">Employee ID</label>
    <input type="text" name="employee_id" class="form-control"
           value="{{ old('employee_id', $user->employee_id ?? '') }}">
</div>

{{-- Default Branch --}}
<div class="mb-3">
    <label class="form-label">Default Branch</label>
    <select name="default_branch_id" class="form-select">
        <option value="">-- None --</option>
        @foreach($branches as $branch)
            <option value="{{ $branch->id }}"
                {{ old('default_branch_id', $user->default_branch_id ?? '') == $branch->id ? 'selected' : '' }}>
                {{ $branch->name }}
            </option>
        @endforeach
    </select>
</div>

{{-- Branch Assignments (multi-select) --}}
<div class="mb-3">
    <label class="form-label">Branch Assignments</label>
    <select name="branches[]" class="form-select" multiple size="4">
        @foreach($branches as $branch)
            <option value="{{ $branch->id }}"
                {{ in_array($branch->id, old('branches', $userBranches ?? [])) ? 'selected' : '' }}>
                {{ $branch->name }}
            </option>
        @endforeach
    </select>
    <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
</div>
```

### Modify UserController

**In `create()`**: Load branches for dropdown:
```php
$branches = Branch::active()->orderBy('name')->get();
```

**In `store()` and `update()`**: Add validation + save:
```php
// Validation additions:
'task_role' => 'nullable|in:branch_manager,loan_advisor,bank_employee,office_employee,legal_advisor',
'task_bank_id' => 'nullable|required_if:task_role,bank_employee|exists:banks,id',
'employee_id' => 'nullable|string|max:50',
'default_branch_id' => 'nullable|exists:branches,id',
'branches' => 'nullable|array',
'branches.*' => 'exists:branches,id',

// After saving user:
if ($request->has('branches')) {
    $user->branches()->sync($request->input('branches', []));
}
```

### User List Enhancement

Add task_role column to users index table:
```blade
<td>{{ $user->task_role_label }}</td>
```

---

## 5. CSS Additions

**File**: `public/css/shf.css` (add to existing)

```css
/* === Loan Stage Workflow === */

/* Stage status indicators */
.shf-stage-pending { border-left: 4px solid var(--bs-secondary); }
.shf-stage-in_progress, .shf-stage-in-progress { border-left: 4px solid var(--bs-primary); }
.shf-stage-completed { border-left: 4px solid var(--bs-success); }
.shf-stage-rejected { border-left: 4px solid var(--bs-danger); }
.shf-stage-skipped { border-left: 4px solid var(--bs-warning); }

/* Stage card */
.shf-stage-card { transition: border-color 0.3s; }
.shf-stage-card .card-header { background: transparent; }

/* Progress bar track */
.shf-progress-track { display: flex; align-items: center; padding: 1rem 0; overflow-x: auto; }
.shf-progress-dot {
    width: 36px; height: 36px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.8rem; font-weight: 600; flex-shrink: 0;
    background: var(--bs-secondary-bg); color: var(--bs-secondary);
    border: 2px solid var(--bs-secondary);
}
.shf-progress-dot.shf-stage-completed { background: var(--bs-success); color: #fff; border-color: var(--bs-success); }
.shf-progress-dot.shf-stage-in_progress,
.shf-progress-dot.shf-stage-in-progress { background: var(--bs-primary); color: #fff; border-color: var(--bs-primary); }
.shf-progress-dot.shf-stage-skipped { background: var(--bs-warning); color: #fff; border-color: var(--bs-warning); }
.shf-progress-dot.shf-stage-current { box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb), 0.3); }
.shf-progress-line { flex: 1; height: 3px; background: var(--bs-secondary-bg); min-width: 20px; }
.shf-progress-line.shf-stage-completed { background: var(--bs-success); }

/* Parallel stage grid */
.shf-parallel-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 768px) { .shf-parallel-grid { grid-template-columns: 1fr; } }

/* Document toggle */
.shf-doc-item { padding: 0.75rem 1rem; border-bottom: 1px solid var(--bs-border-color); transition: background 0.2s; }
.shf-doc-item:hover { background: var(--bs-light); }
.shf-doc-received { border-left: 3px solid var(--bs-success); }
.shf-doc-pending { border-left: 3px solid var(--bs-secondary); }
.shf-doc-toggle { cursor: pointer; }
.shf-doc-progress { height: 8px; border-radius: 4px; }

/* Remarks */
.shf-remark-item { padding: 0.75rem; border-bottom: 1px solid var(--bs-border-color); }
.shf-remark-item:last-child { border-bottom: none; }
.shf-remark-meta { font-size: 0.8rem; color: var(--bs-secondary); }

/* Notification badge */
.shf-notification-badge {
    font-size: 0.65rem; padding: 2px 5px;
    transform: translate(-50%, -25%) !important;
}

/* Decision tree (disbursement) */
.shf-decision-tree .shf-decision-option {
    padding: 1rem; border: 2px solid var(--bs-border-color); border-radius: 8px;
    cursor: pointer; transition: border-color 0.2s;
}
.shf-decision-tree .shf-decision-option:hover,
.shf-decision-tree .shf-decision-option.active { border-color: var(--accent); }

/* Stat cards */
.shf-stat-card { text-align: center; }
.shf-stat-card h3 { font-size: 1.8rem; font-weight: 700; color: var(--accent); }
```

---

## 6. JavaScript Additions

**File**: `public/js/shf-loans.js` (new file)

```javascript
/**
 * SHF Loans — Client-side interactions for loan workflow system.
 * Loaded on loan pages via @push('scripts')
 */

const SHFLoans = {
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.content,

    /**
     * Toggle document received status via AJAX
     */
    initDocumentToggles() {
        $(document).on('change', '.shf-doc-toggle', function() {
            const $toggle = $(this);
            const url = $toggle.data('toggle-url');
            const $item = $toggle.closest('.shf-doc-item');

            $.ajax({
                url: url,
                method: 'POST',
                data: { _token: SHFLoans.csrfToken },
                success(response) {
                    if (response.success) {
                        const doc = response.document;
                        $item.toggleClass('shf-doc-received', doc.is_received);
                        $item.toggleClass('shf-doc-pending', !doc.is_received);
                        $item.find('.shf-doc-status').html(
                            doc.is_received
                                ? `Received ${doc.received_date} by ${doc.received_by}`
                                : 'Pending'
                        );
                        SHFLoans.updateProgressBar(response.progress);
                    }
                },
                error(xhr) { SHFLoans.showError(xhr); }
            });
        });
    },

    /**
     * Stage status update via AJAX
     */
    initStageActions() {
        $(document).on('click', '.shf-stage-action', function() {
            const $btn = $(this);
            const stageKey = $btn.closest('.shf-stage-card').data('stage-key');
            const action = $btn.data('action');
            const url = `/loans/${$btn.data('loan-id')}/stages/${stageKey}/status`;

            $.ajax({
                url: url,
                method: 'POST',
                data: { _token: SHFLoans.csrfToken, status: action },
                success(response) {
                    if (response.success) {
                        location.reload(); // Simplest approach — full reload for stage changes
                    }
                },
                error(xhr) { SHFLoans.showError(xhr); }
            });
        });
    },

    /**
     * Stage assignment via AJAX
     */
    initStageAssignment() {
        $(document).on('change', '.shf-stage-assign', function() {
            const $select = $(this);
            const stageKey = $select.data('stage');
            const userId = $select.val();
            const loanId = $select.data('loan-id');

            if (!userId) return;

            $.ajax({
                url: `/loans/${loanId}/stages/${stageKey}/assign`,
                method: 'POST',
                data: { _token: SHFLoans.csrfToken, user_id: userId },
                success(response) {
                    if (response.success) {
                        SHFLoans.showToast(`Assigned to ${response.assigned_to}`, 'success');
                    }
                },
                error(xhr) { SHFLoans.showError(xhr); }
            });
        });
    },

    /**
     * Remark submission via AJAX
     */
    initRemarks() {
        $(document).on('submit', '.shf-remark-form', function(e) {
            e.preventDefault();
            const $form = $(this);

            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: $form.serialize(),
                success(response) {
                    if (response.success) {
                        const r = response.remark;
                        const html = `<div class="shf-remark-item">
                            <div class="shf-remark-meta">${r.user_name} · ${r.created_at}</div>
                            <div>${r.remark}</div>
                        </div>`;
                        $('.shf-remarks-list').prepend(html);
                        $form.find('textarea').val('');
                    }
                },
                error(xhr) { SHFLoans.showError(xhr); }
            });
        });
    },

    /**
     * Disbursement form field toggling
     */
    initDisbursementForm() {
        $('input[name="disbursement_type"]').on('change', function() {
            const type = $(this).val();
            $('.shf-fund-transfer-fields').toggle(type === 'fund_transfer');
            $('.shf-cheque-fields').toggle(type === 'cheque');
        }).trigger('change');

        $('input[name="is_otc"]').on('change', function() {
            $('.shf-otc-fields').toggle($(this).is(':checked'));
        }).trigger('change');
    },

    /**
     * Product dependent dropdown (when bank changes)
     */
    initProductDropdown() {
        const $bank = $('select[name="bank_id"]');
        const $product = $('select[name="product_id"]');
        const allProducts = $product.find('option').clone();

        $bank.on('change', function() {
            const bankId = $(this).val();
            $product.empty().append('<option value="">-- Select Product --</option>');
            if (bankId) {
                allProducts.each(function() {
                    if ($(this).data('bank-id') == bankId) {
                        $product.append($(this).clone());
                    }
                });
            }
        });
    },

    /**
     * Stage notes form (stages 5-9)
     */
    initStageNotes() {
        $(document).on('submit', '.shf-stage-notes-form', function(e) {
            e.preventDefault();
            const $form = $(this);
            const formData = {};
            $form.serializeArray().forEach(item => { formData[item.name] = item.value; });

            $.ajax({
                url: $form.data('notes-url'),
                method: 'POST',
                data: { _token: SHFLoans.csrfToken, notes_data: formData },
                success() { SHFLoans.showToast('Details saved', 'success'); },
                error(xhr) { SHFLoans.showError(xhr); }
            });
        });
    },

    /**
     * Update document progress bar
     */
    updateProgressBar(progress) {
        $('.shf-doc-progress .progress-bar').css('width', progress.percentage + '%');
        $('.shf-doc-progress-text').text(
            `${progress.received}/${progress.total} documents collected (${progress.percentage}%)`
        );
    },

    showToast(message, type = 'info') {
        // Use existing toast pattern from shf-app.js or simple alert
        const $toast = $(`<div class="alert alert-${type === 'success' ? 'success' : 'info'} alert-dismissible fade show position-fixed bottom-0 end-0 m-3" style="z-index:9999">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`);
        $('body').append($toast);
        setTimeout(() => $toast.alert('close'), 3000);
    },

    showError(xhr) {
        const msg = xhr.responseJSON?.error || 'An error occurred';
        SHFLoans.showToast(msg, 'error');
    },

    init() {
        this.initDocumentToggles();
        this.initStageActions();
        this.initStageAssignment();
        this.initRemarks();
        this.initDisbursementForm();
        this.initProductDropdown();
        this.initStageNotes();
    }
};

$(document).ready(() => SHFLoans.init());
```

**Include in loan views** via `@push('scripts')`:
```blade
@push('scripts')
<script src="{{ asset('js/shf-loans.js') }}"></script>
@endpush
```

---

## 7. User Impersonation (HIGHEST PRIORITY)

### Package

**`lab404/laravel-impersonate`** (^1.7) — `composer require lab404/laravel-impersonate`

### Env Flag: `ALLOW_IMPERSONATE_ALL`

**File**: `.env`

```
ALLOW_IMPERSONATE_ALL=0
```

| Value | Behavior |
|-------|----------|
| `0` (default, production) | Only `super_admin` can impersonate |
| `1` (dev/testing) | ALL authenticated users can impersonate any other user (except super_admin targets remain protected) |

**File**: `config/app.php` (or a custom config)

```php
'allow_impersonate_all' => env('ALLOW_IMPERSONATE_ALL', false),
```

### How It Works

1. User with impersonate permission clicks "Impersonate" button in header → dropdown with search
2. AJAX search endpoint returns eligible users
3. Select user → confirmation dialog → session switches to impersonated user
4. Amber banner appears: "Impersonating **[Name]**" with "Leave" button
5. Click Leave → session restores to original user

### User Model Changes

Add `Impersonate` trait to `User` model:

```php
use Lab404\Impersonate\Models\Impersonate;

class User extends Authenticatable
{
    use Impersonate;

    /**
     * Who CAN impersonate others?
     * - If ALLOW_IMPERSONATE_ALL=1 → any authenticated user
     * - If ALLOW_IMPERSONATE_ALL=0 → only super_admin
     */
    public function canImpersonate(): bool
    {
        if (config('app.allow_impersonate_all')) {
            return true; // All users can impersonate in dev mode
        }

        return $this->isSuperAdmin();
    }

    /**
     * Who CAN BE impersonated?
     * - super_admin can NEVER be impersonated (always protected)
     * - All other active users can be impersonated
     */
    public function canBeImpersonated(): bool
    {
        return !$this->isSuperAdmin();
    }
}
```

### Controller: `ImpersonateController`

**File**: `app/Http/Controllers/ImpersonateController.php`

```php
class ImpersonateController extends Controller
{
    /**
     * Search users available for impersonation.
     * GET /api/impersonate/users?search=...
     */
    public function users(Request $request): JsonResponse
    {
        // Verify current user can impersonate
        if (!$request->user()->canImpersonate()) {
            abort(403);
        }

        $search = $request->get('search', '');

        $users = User::where('id', '!=', auth()->id())
            ->where('role', '!=', 'super_admin') // super_admin always protected
            ->where('is_active', true)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('role', 'like', "%{$search}%")
                        ->orWhere('task_role', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'role', 'task_role']);

        return response()->json($users);
    }
}
```

### Routes

```php
// Impersonation — no permission middleware, controlled by canImpersonate() on model
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/api/impersonate/users', [ImpersonateController::class, 'users'])
        ->name('impersonate.users');
});

// Package routes registered automatically via service provider:
// GET /impersonate/take/{id}/{guard?} → start impersonating
// GET /impersonate/leave → stop impersonating
```

**Note**: No `permission:` middleware on the route. Authorization is handled by `canImpersonate()` on the User model (which the package calls internally). The controller also checks `canImpersonate()` explicitly for the search endpoint.

### Navigation UI

**Modify**: `resources/views/layouts/navigation.blade.php`

```blade
{{-- Impersonation Banner (shown when currently impersonating) --}}
@impersonating
    <a href="{{ route('impersonate.leave') }}"
       class="btn btn-warning btn-sm d-flex align-items-center gap-1">
        <i class="bi bi-person-badge"></i>
        <span class="d-none d-sm-inline">Impersonating <strong>{{ Auth::user()->name }}</strong></span>
        <span class="badge bg-dark">Leave</span>
    </a>
@endImpersonating

{{-- Impersonate Button (shown when user can impersonate) --}}
@canImpersonate
    <li class="nav-item dropdown">
        <a class="nav-link" href="#" data-bs-toggle="dropdown" title="Impersonate User">
            <i class="bi bi-person-badge"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-end" style="width: 340px; padding: 0.75rem;">
            <input type="text" class="form-control form-control-sm mb-2"
                   id="impersonateSearch" placeholder="Search by name, email or role...">
            <div id="impersonateResults" style="max-height: 250px; overflow-y: auto;">
                <small class="text-muted">Type to search users...</small>
            </div>
        </div>
    </li>
@endCanImpersonate
```

**How `@canImpersonate` works**: The Blade directive calls `auth()->user()->canImpersonate()` — which respects the env flag. So:
- `ALLOW_IMPERSONATE_ALL=0` → only super_admin sees the button
- `ALLOW_IMPERSONATE_ALL=1` → ALL users see the button

### JavaScript

**Add to** `public/js/shf-app.js` or page-level script:

```javascript
// Impersonate search (debounced 300ms)
let impersonateTimer;
$('#impersonateSearch').on('input', function() {
    clearTimeout(impersonateTimer);
    const search = $(this).val().trim();
    if (search.length < 1) {
        $('#impersonateResults').html('<small class="text-muted">Type to search users...</small>');
        return;
    }
    impersonateTimer = setTimeout(function() {
        $.get('/api/impersonate/users', { search: search }, function(users) {
            let html = '';
            users.forEach(function(u) {
                html += `<a href="/impersonate/take/${u.id}" class="dropdown-item py-2"
                    onclick="return confirm('Impersonate ${u.name}?')">
                    <strong>${u.name}</strong><br>
                    <small class="text-muted">${u.email} · ${u.role}${u.task_role ? ' / ' + u.task_role : ''}</small>
                </a>`;
            });
            $('#impersonateResults').html(html || '<small class="text-muted">No users found</small>');
        });
    }, 300);
});
```

### Restrictions

| Rule | Enforcement | Env Override? |
|------|------------|---------------|
| Only `super_admin` can impersonate (default) | `canImpersonate()` checks role | Yes — `ALLOW_IMPERSONATE_ALL=1` allows all users |
| Cannot impersonate a `super_admin` | `canBeImpersonated()` returns false + search excludes them | **No** — super_admin always protected regardless of env |
| Cannot impersonate inactive users | Search query filters `is_active = true` | No |
| Cannot impersonate yourself | Package checks user ID | No |
| Cannot nest impersonation | Package checks `isImpersonating()` | No |

### Security Notes

- **Production**: Set `ALLOW_IMPERSONATE_ALL=0` (or omit — defaults to false)
- **Development/Testing**: Set `ALLOW_IMPERSONATE_ALL=1` so any user can impersonate to test workflows from different roles
- **super_admin protection is ALWAYS active** — even with `ALLOW_IMPERSONATE_ALL=1`, nobody can impersonate a super_admin user
- The `.env.example` file should include `ALLOW_IMPERSONATE_ALL=0` with a comment

### Activity Logging

Listen to package events for audit trail:

```php
// In EventServiceProvider
Event::listen(TakeImpersonation::class, function ($event) {
    ActivityLog::log('impersonate_start', $event->impersonated, [
        'impersonator' => $event->impersonator->name,
        'impersonated' => $event->impersonated->name,
    ]);
});

Event::listen(LeaveImpersonation::class, function ($event) {
    ActivityLog::log('impersonate_end', $event->impersonated, [
        'impersonator' => $event->impersonator->name,
        'impersonated' => $event->impersonated->name,
    ]);
});
```

### Config

Publish config: `php artisan vendor:publish --tag=impersonate`

```php
// config/laravel-impersonate.php
'take_redirect_to' => '/dashboard',
'leave_redirect_to' => '/dashboard',
```

---

## 8. Testing Checklist

### PHPUnit Tests to Create

**File**: `tests/Feature/LoanConversionTest.php`
- Test converting quotation to loan (data mapping)
- Test quotation marked as converted
- Test double-conversion blocked
- Test direct loan creation
- Test loan number generation format

**File**: `tests/Feature/LoanStageWorkflowTest.php`
- Test stage initialization (14 assignments created)
- Test sequential advancement (complete 1 → advance to 2)
- Test parallel completion (all 4 done → advance to 5)
- Test invalid transitions rejected (completed → in_progress)
- Test skip permission enforcement
- Test progress recalculation

**File**: `tests/Feature/LoanDocumentTest.php`
- Test document population from quotation
- Test document population from defaults
- Test toggle received/not received
- Test progress calculation
- Test custom document addition

**File**: `tests/Feature/LoanPermissionTest.php`
- Test route permission enforcement (each route + required permission)
- Test visibility scoping (staff sees own loans, admin sees all)
- Test super_admin bypass

**File**: `tests/Feature/DisbursementTest.php`
- Test fund transfer → loan completed
- Test cheque without OTC → loan completed
- Test cheque with OTC → pending, then clear → completed

### Manual Testing Script

```
1. Create quotation with 2 banks + documents
2. Convert to loan (select bank 1)
3. Verify: loan created, documents copied, stages 1-2 auto-completed
4. Go to documents → toggle all received
5. Go to stages → complete stage 3
6. Stage 4: start all 4 sub-stages, complete each independently
7. After all 4 → verify auto-advance to stage 5
8. Fill stage 5-9 forms, complete each
9. Stage 10: test Fund Transfer → loan completed
10. Create another loan → test Cheque + OTC flow
11. Test notifications: check bell badge, mark read
12. Test remarks: add, view per stage and general
13. Test dashboard: loan stats visible, my tasks list works
14. Test permissions: staff vs admin access
15. Test workflow config: disable a stage for a product → verify loan creation skips it
```

---

## 8. Documentation Updates

After implementation, update these reference docs:

| File | Update |
|------|--------|
| `.claude/database-schema.md` | Add all 14 new tables + 2 modified |
| `.claude/routes-reference.md` | Add all new routes |
| `.claude/services-reference.md` | Add all new services and methods |
| `.docs/models.md` | Add all 13 new models + modified User/Quotation |
| `.docs/permissions.md` | Add 11 new permissions under Loans group |
| `.docs/views.md` | Add all new view files |
| `.docs/frontend.md` | Add shf-loans.js documentation, new CSS classes |
| `.docs/users.md` | Add task_role, employee_id, branch assignment |

---

## Summary of ALL Files Across All Stages

### Migrations (10 files)
1. `create_banks_table`
2. `create_branches_table`
3. `create_products_table`
4. `create_stages_table`
5. `create_user_branches_table`
6. `add_task_fields_to_users_table`
7. `create_loan_details_table`
8. `add_loan_id_to_quotations_table`
9. `create_loan_documents_table`
10. `create_stage_assignments_table`
11. `create_loan_progress_table`
12. `create_valuation_details_table`
13. `create_disbursement_details_table`
14. `create_remarks_table`
15. `create_notifications_table`
16. `create_product_stages_table`

### Models (13 new)
Bank, Branch, Product, Stage, LoanDetail, LoanDocument, StageAssignment, LoanProgress, ValuationDetail, DisbursementDetail, Remark, Notification, ProductStage

### Services (6 new)
LoanStageService, LoanConversionService, LoanDocumentService, DisbursementService, NotificationService, RemarkService

### Controllers (8 new)
LoanConversionController, LoanController, LoanDocumentController, LoanStageController, LoanValuationController, LoanDisbursementController, LoanRemarkController, NotificationController, WorkflowConfigController

### Views (~20 new)
- `quotations/convert.blade.php`
- `loans/index.blade.php`
- `loans/create.blade.php`
- `loans/show.blade.php`
- `loans/edit.blade.php`
- `loans/documents.blade.php`
- `loans/stages.blade.php`
- `loans/valuation.blade.php`
- `loans/disbursement.blade.php`
- `loans/partials/progress-bar.blade.php`
- `loans/partials/stage-card.blade.php`
- `loans/partials/remarks.blade.php`
- `loans/partials/stage-rate-pf.blade.php`
- `loans/partials/stage-sanction.blade.php`
- `loans/partials/stage-docket.blade.php`
- `loans/partials/stage-kfs.blade.php`
- `loans/partials/stage-esign.blade.php`
- `notifications/index.blade.php`
- `settings/workflow.blade.php`
- `settings/workflow-product-stages.blade.php`

### Assets (2 files)
- `public/css/shf.css` (modified — add ~80 lines)
- `public/js/shf-loans.js` (new — ~200 lines)

### Modified Existing Files
- `app/Models/User.php`
- `app/Models/Quotation.php`
- `config/permissions.php`
- `routes/web.php`
- `resources/views/layouts/navigation.blade.php`
- `resources/views/quotations/show.blade.php`
- `resources/views/dashboard.blade.php`
- `app/Http/Controllers/DashboardController.php`
- `resources/views/users/create.blade.php`
- `resources/views/users/edit.blade.php`
- `app/Http/Controllers/UserController.php`
- `public/css/shf.css`
