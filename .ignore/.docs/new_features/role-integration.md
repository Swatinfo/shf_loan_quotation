# Role Integration: Combining Existing + Workflow Roles

## The Two Systems

### Current System (quotationshf)

**`users.role`** — enum column in SQLite: `super_admin`, `admin`, `staff`

| Role | Purpose | Scope |
|------|---------|-------|
| `super_admin` | Full system access | All features, bypass all permissions |
| `admin` | System administration | Settings, users, all quotations, activity log |
| `staff` | Regular user | Create quotations, view own quotations |

**Permission system**: 21 permissions, 4 groups (Settings, Quotations, Users, System). Resolution: super_admin bypass → user_permissions override → role_permissions default.

### Reference System (shf_task)

**7 roles** with different scopes:

| Role | Purpose | Assignment |
|------|---------|------------|
| `super-admin` | Full access | All branches |
| `admin` | Multi-branch management | All branches |
| `branch-manager` | Branch operations, oversight | Multiple branches |
| `loan-advisor` | Creates/processes loans, stages 1-3, coordination | Multiple branches |
| `bank-employee` | Bank approvals (BSM/OSV, Legal, Rate, Sanction, E-Sign) | Specific **bank** (not branches) |
| `office-employee` | Back-office (Docket, KFS, Valuation entry, Disbursement) | Specific branches |
| `legal-advisor` | Legal document review and approval | Multiple branches |

---

## Analysis: Why Two Separate Concepts

These are **two different concerns**:

1. **System Role** (`users.role`) = What system features can this user access?
   - Can they manage settings? View all quotations? Create users?
   - This is the existing permission-based access control

2. **Workflow Role** (`users.task_role`) = What loan processing stages can they be assigned to?
   - Can they be assigned to BSM/OSV approval? Legal verification?
   - This determines stage assignment eligibility and UI filtering

**Examples of coexistence:**
- An `admin` with `task_role = 'loan_advisor'` → manages settings AND processes loans
- A `staff` with `task_role = 'bank_employee'` → creates quotations AND does bank approvals
- A `super_admin` with `task_role = null` → manages everything but doesn't appear in stage assignment dropdowns
- A `staff` with `task_role = null` → quotation-only user, no loan workflow involvement
- A `staff` with `task_role = 'legal_advisor'` → creates quotations AND does legal reviews

**Why NOT expand the `role` enum:**
- `role` is a SQLite enum (CHECK constraint). Expanding it requires migration and risks breaking existing data
- System access and workflow capability are orthogonal. A branch_manager still needs to be `admin` or `staff` for system features
- The existing permission system already handles fine-grained access via the `role` column
- Adding 5 more values to `role` would make the permission matrix (PermissionController UI) unwieldy

---

## Combined Design

### System Role (unchanged)

**Column**: `users.role` — enum: `super_admin`, `admin`, `staff`

Controls: Settings access, user management, quotation visibility, activity log, system features.

### Workflow Role (new)

**Column**: `users.task_role` — string, nullable

**Values**:
| Value | Label (EN) | Label (GU) | Stage Eligibility |
|-------|-----------|-----------|-------------------|
| `branch_manager` | Branch Manager | બ્રાન્ચ મેનેજર | Technical Valuation, oversight, reassignment |
| `loan_advisor` | Loan Advisor | લોન સલાહકાર | Stages 1-3, Application Number, Rate/PF initiation, KFS, OTC clearance |
| `bank_employee` | Bank Employee | બેંક કર્મચા���ી | BSM/OSV, Legal (bank-side), Rate/PF approval, Sanction, E-Sign |
| `office_employee` | Office Employee | ઓફિસ કર્મચારી | Legal (office-side), Valuation entry, Docket, Disbursement |
| `legal_advisor` | Legal Advisor | કાનૂની સલાહકાર | Legal Verification (independent legal review) |
| `null` | *(none)* | | Not part of loan workflow — quotation-only user |

**Important naming**: We use `loan_advisor` (not `mortgage_advisor` from our initial plan) to match the shf_task naming convention.

### Bank Association (new — for bank employees)

**Column**: `users.task_bank_id` — FK → banks, nullable

Bank employees are tied to a specific bank. When a stage needs a bank employee, the system should filter to users whose `task_bank_id` matches the loan's `bank_id`.

| Role | Uses `task_bank_id`? | Uses branches? |
|------|---------------------|----------------|
| `branch_manager` | No | Yes (multi-branch via `user_branches`) |
| `loan_advisor` | No | Yes (multi-branch via `user_branches`) |
| `bank_employee` | **Yes** (assigned to one bank) | No (works across branches for that bank) |
| `office_employee` | No | Yes (multi-branch via `user_branches`) |
| `legal_advisor` | No | Yes (multi-branch via `user_branches`) |

---

## Stage-to-Role Mapping

Which `task_role` can be assigned to which workflow stage:

| Stage | Primary Role | Can Also Be Assigned To |
|-------|-------------|------------------------|
| 1. Inquiry | `loan_advisor` | `branch_manager` |
| 2. Document Selection | `loan_advisor` | `branch_manager` |
| 3. Document Collection | `loan_advisor` | `office_employee` |
| 4. Parallel Processing (parent) | *(auto-managed)* | |
| 4a. Application Number | `loan_advisor` | |
| 4b. BSM/OSV Approval | `bank_employee` | |
| 4c. Legal Verification | `legal_advisor` | `bank_employee`, `office_employee` |
| 4d. Technical Valuation | `office_employee` | `branch_manager` |
| 5. Rate & PF Request | `loan_advisor` → `bank_employee` | |
| 6. Sanction Letter | `bank_employee` | `loan_advisor` |
| 7. Docket Login | `office_employee` | `loan_advisor` |
| 8. KFS Generation | `loan_advisor` | `office_employee` |
| 9. E-Sign & eNACH | `bank_employee` | |
| 10. Disbursement | `office_employee` | `loan_advisor` |

**Key insight**: The "Primary Role" is used for auto-assignment in `product_stages.default_assignee_role`. The "Can Also" roles are allowed in manual assignment dropdowns.

### Stage Role Eligibility Config

```php
// In LoanStageService — defines which task_roles can be assigned to which stages
// NOTE: super_admin bypasses this entirely. admin and branch_manager can act on any stage
// via the override_stages permission. This constant filters the assignment DROPDOWN only.

const STAGE_ROLE_ELIGIBILITY = [
    // Base stages
    'inquiry' => ['loan_advisor', 'branch_manager'],
    'document_selection' => ['loan_advisor', 'branch_manager'],
    'document_collection' => ['loan_advisor', 'branch_manager'],
    'app_number' => ['loan_advisor', 'branch_manager'],
    'bsm_osv' => ['bank_employee'],
    'legal_verification' => ['legal_advisor'],
    'technical_valuation' => ['branch_manager', 'office_employee'],
    'rate_pf' => ['loan_advisor', 'bank_employee', 'branch_manager'],
    'sanction' => ['bank_employee', 'loan_advisor', 'branch_manager'],
    'docket' => ['office_employee', 'branch_manager'],
    'kfs' => ['office_employee', 'loan_advisor', 'branch_manager'],
    'esign' => ['bank_employee', 'loan_advisor', 'branch_manager'],
    'disbursement' => ['loan_advisor', 'branch_manager'],

    // Optional bank-specific stages
    'cibil_check' => ['bank_employee'],
    'property_valuation' => ['branch_manager', 'office_employee'],
    'vehicle_valuation' => ['branch_manager', 'office_employee'],
    'business_valuation' => ['branch_manager', 'office_employee'],
    'title_search' => ['legal_advisor'],
    'financial_analysis' => ['bank_employee'],
    'site_visit' => ['branch_manager'],
    'approval_committee' => ['bank_employee'],
    'credit_committee' => ['bank_employee'],
    'insurance' => ['office_employee'],
    'mortgage' => ['office_employee', 'legal_advisor'],
];
```

**Override permission**: Users with `skip_loan_stages` permission (admin role default) can act on ANY stage regardless of this eligibility map. `super_admin` always bypasses.
```

This is used to filter the "Assign to" dropdown in the stage card UI — only showing users with eligible `task_role` for that stage.

---

## Migration Changes (Updated from Stage A)

### `add_task_fields_to_users_table` (revised)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `task_role` | string | yes | null | values: branch_manager, loan_advisor, bank_employee, office_employee, legal_advisor |
| `employee_id` | string | yes | null | internal employee ID or bank employee ID |
| `default_branch_id` | FK → branches | yes | null | set null on delete |
| `task_bank_id` | FK → banks | yes | null | set null on delete — **only for bank_employee role** |

**Validation rules** (enforced in controller):
```php
'task_role' => 'nullable|in:branch_manager,loan_advisor,bank_employee,office_employee,legal_advisor',
'task_bank_id' => 'nullable|required_if:task_role,bank_employee|exists:banks,id',
```

If `task_role = 'bank_employee'`, then `task_bank_id` is required.
For all other roles, `task_bank_id` should be null.

---

## User Model Updates (Revised)

### Constants

```php
// In User model
const TASK_ROLES = [
    'branch_manager',
    'loan_advisor',
    'bank_employee',
    'office_employee',
    'legal_advisor',
];

const TASK_ROLE_LABELS = [
    'branch_manager' => 'Branch Manager',
    'loan_advisor' => 'Loan Advisor',
    'bank_employee' => 'Bank Employee',
    'office_employee' => 'Office Employee',
    'legal_advisor' => 'Legal Advisor',
];

const TASK_ROLE_LABELS_GU = [
    'branch_manager' => 'બ્રાન્ચ મેનેજર',
    'loan_advisor' => 'લોન સલાહકાર',
    'bank_employee' => 'બેંક કર્મચારી',
    'office_employee' => 'ઓફિસ કર્મચારી',
    'legal_advisor' => 'કાનૂની સલાહકાર',
];
```

### New Fillable

Add: `task_role`, `employee_id`, `default_branch_id`, `task_bank_id`

### New Relationships

| Method | Type | Related | FK |
|--------|------|---------|-----|
| `branches()` | BelongsToMany | Branch | pivot: `user_branches` |
| `defaultBranch()` | BelongsTo | Branch | `default_branch_id` |
| `taskBank()` | BelongsTo | Bank | `task_bank_id` |

### New Methods

```php
public function hasTaskRole(): bool
{
    return $this->task_role !== null;
}

public function isTaskRole(string $role): bool
{
    return $this->task_role === $role;
}

public function isBankEmployee(): bool
{
    return $this->task_role === 'bank_employee';
}

public function isLoanAdvisor(): bool
{
    return $this->task_role === 'loan_advisor';
}

public function isLegalAdvisor(): bool
{
    return $this->task_role === 'legal_advisor';
}

public function getTaskRoleLabelAttribute(): string
{
    return self::TASK_ROLE_LABELS[$this->task_role] ?? '';
}

public function getTaskRoleLabelGuAttribute(): string
{
    return self::TASK_ROLE_LABELS_GU[$this->task_role] ?? '';
}
```

### Scopes for Filtering

```php
/**
 * Users eligible for a specific stage.
 */
public function scopeEligibleForStage($query, string $stageKey, ?int $loanBankId = null): void
{
    $eligibleRoles = LoanStageService::STAGE_ROLE_ELIGIBILITY[$stageKey] ?? [];

    $query->where('is_active', true)
        ->whereIn('task_role', $eligibleRoles);

    // For stages that need bank employees, filter by loan's bank
    if (in_array('bank_employee', $eligibleRoles) && $loanBankId) {
        $query->where(function ($q) use ($loanBankId, $eligibleRoles) {
            // Bank employees must match the loan's bank
            $q->where(function ($sub) use ($loanBankId) {
                $sub->where('task_role', 'bank_employee')
                    ->where('task_bank_id', $loanBankId);
            });
            // Other eligible roles don't need bank matching
            $otherRoles = array_diff($eligibleRoles, ['bank_employee']);
            if ($otherRoles) {
                $q->orWhereIn('task_role', $otherRoles);
            }
        });
    }
}
```

---

## How Roles Map Between Systems

| shf_task Role | System `role` | Workflow `task_role` | Notes |
|---------------|--------------|---------------------|-------|
| `super-admin` | `super_admin` | any or `null` | System bypass. Can optionally have a task_role |
| `admin` | `admin` | any or `null` | Full system access. Can optionally have a task_role |
| `branch-manager` | `admin` or `staff` | `branch_manager` | Usually `admin` for branch oversight |
| `loan-advisor` | `staff` | `loan_advisor` | Most common combination |
| `bank-employee` | `staff` | `bank_employee` | External bank staff with limited system access |
| `office-employee` | `staff` | `office_employee` | Internal office staff |
| `legal-advisor` | `staff` | `legal_advisor` | Could be external legal consultant |

**Typical real-world users:**

| User | system `role` | `task_role` | `task_bank_id` |
|------|-------------|-----------|---------------|
| Company Owner | `super_admin` | `null` | null |
| Office Manager | `admin` | `branch_manager` | null |
| Loan Officer Ramesh | `staff` | `loan_advisor` | null |
| HDFC Rep Amit | `staff` | `bank_employee` | (HDFC bank id) |
| Back Office Priya | `staff` | `office_employee` | null |
| Legal Advisor Mehta | `staff` | `legal_advisor` | null |
| Quotation-only Staff | `staff` | `null` | null |

---

## Permission Impact

### Existing Permissions (unchanged)

The 21 existing permissions continue to work via `users.role`. No changes needed.

### New Loan Permissions (11)

These are also controlled via `users.role` through the permission system. The `task_role` does NOT affect permissions — it only affects stage assignment eligibility.

**Key distinction:**
- `users.role` + `permissions` → **Can this user ACCESS the loan features?** (view loans, manage stages, etc.)
- `users.task_role` → **Which specific STAGES can this user be assigned to work on?**

A user with `role = 'staff'` and permissions `view_loans + manage_loan_stages` CAN update any stage they can see. But the `task_role` determines which stages the **assignment dropdown** shows them as an option for.

### No New role_permissions Rows for task_role

The `role_permissions` table only maps `role` values (super_admin/admin/staff) to permission slugs. `task_role` is NOT a permission system role — it's a workflow assignment filter.

---

## UI Changes for User Management

### User Create/Edit Form

Add these fields after the existing role dropdown:

```
┌──────────────────────────────────────────────────┐
│ System Role: [super_admin ▼ | admin | staff]     │  ← existing
├──────────────────────────────────────────────────┤
│ Workflow Role (Loan Processing):                  │  ← new section
│                                                    │
│ Task Role: [-- None -- ▼]                         │
│            [Branch Manager]                        │
│            [Loan Advisor]                          │
│            [Bank Employee]                         │
│            [Office Employee]                       │
│            [Legal Advisor]                         │
│                                                    │
│ Employee ID: [___________]  (optional)            │
│                                                    │
│ [If task_role = bank_employee]:                   │
│ Bank: [HDFC Bank ▼]  ← required for bank emp     │
│                                                    │
│ Default Branch: [Rajkot Main ▼]  (optional)      │
│                                                    │
│ Branch Assignments:                                │
│ [☑ Rajkot Main]                                   │
│ [☐ Ahmedabad]                                     │
│ [☑ Surat]                                         │
│ (hold Ctrl to select multiple)                    │
└──────────────────────────────────────────────────┘
```

**JS behavior:**
- When `task_role` changes to `bank_employee`, show the Bank dropdown (required)
- When `task_role` changes to anything else, hide Bank dropdown and clear value
- When `task_role` is `null`/empty, hide Employee ID, Branch fields (optional: still show them)

### User Index Table

Add column showing both roles:

```
| Name      | Email          | Role   | Task Role      | Status |
|-----------|----------------|--------|----------------|--------|
| Admin     | admin@shf.com  | Admin  | Branch Manager | Active |
| Ramesh    | ramesh@shf.com | Staff  | Loan Advisor   | Active |
| Amit (HDFC)| amit@hdfc.com | Staff  | Bank Employee  | Active |
| Priya     | priya@shf.com  | Staff  | —              | Active |
```

---

## Stage Assignment Dropdown Logic

When rendering the "Assign to" dropdown for a stage, filter users by eligibility:

```php
// In LoanStageController or view
$eligibleUsers = User::eligibleForStage($stageKey, $loan->bank_id)
    ->orderBy('name')
    ->get();
```

For example, for `bsm_osv` stage on a HDFC loan:
- Only shows users with `task_role = 'bank_employee'` AND `task_bank_id = (HDFC id)`

For `legal_verification`:
- Shows users with `task_role` in ['legal_advisor', 'bank_employee', 'office_employee']
- Bank employees are further filtered to match the loan's bank

For `document_collection`:
- Shows users with `task_role` in ['loan_advisor', 'office_employee']
- No bank filtering needed

---

## LoanStageService Updates

### STAGE_ROLE_ELIGIBILITY constant

```php
// In LoanStageService
public const STAGE_ROLE_ELIGIBILITY = [
    'inquiry' => ['loan_advisor', 'branch_manager'],
    'document_selection' => ['loan_advisor', 'branch_manager'],
    'document_collection' => ['loan_advisor', 'office_employee'],
    'app_number' => ['loan_advisor'],
    'bsm_osv' => ['bank_employee'],
    'legal_verification' => ['legal_advisor', 'bank_employee', 'office_employee'],
    'technical_valuation' => ['office_employee', 'branch_manager'],
    'rate_pf' => ['loan_advisor', 'bank_employee'],
    'sanction' => ['bank_employee', 'loan_advisor'],
    'docket' => ['office_employee', 'loan_advisor'],
    'kfs' => ['loan_advisor', 'office_employee'],
    'esign' => ['bank_employee'],
    'disbursement' => ['office_employee', 'loan_advisor'],
];
```

### Updated `findUserByRoleInBranch` → `findEligibleUser`

```php
/**
 * Find a user eligible for a stage in a specific branch/bank context.
 */
protected function findEligibleUser(string $stageKey, ?int $branchId, ?int $bankId): ?int
{
    $eligibleRoles = self::STAGE_ROLE_ELIGIBILITY[$stageKey] ?? [];
    if (empty($eligibleRoles)) return null;

    $query = User::where('is_active', true)
        ->whereIn('task_role', $eligibleRoles);

    // For bank_employee roles, must match bank
    if (in_array('bank_employee', $eligibleRoles) && $bankId) {
        $query->where(function ($q) use ($bankId, $eligibleRoles) {
            $q->where(function ($sub) use ($bankId) {
                $sub->where('task_role', 'bank_employee')
                    ->where('task_bank_id', $bankId);
            });
            $otherRoles = array_diff($eligibleRoles, ['bank_employee']);
            if ($otherRoles) {
                $q->orWhereIn('task_role', $otherRoles);
            }
        });
    }

    // For branch-based roles, prefer users in the loan's branch
    if ($branchId) {
        $branchUser = (clone $query)
            ->whereHas('branches', fn($q) => $q->where('branches.id', $branchId))
            ->first();
        if ($branchUser) return $branchUser->id;
    }

    return $query->first()?->id;
}
```

---

## PermissionService Updates

### `clearAllCaches()` — add new task_roles awareness

No changes needed. The permission system operates on `users.role` (super_admin/admin/staff), not `task_role`. The cache keys remain `role_perms:super_admin`, `role_perms:admin`, `role_perms:staff`.

---

## Seeder Data (Optional)

For development/demo, seed typical users:

```php
// In a UserSeeder or LoanDemoSeeder
$users = [
    ['name' => 'Loan Advisor Demo', 'email' => 'advisor@shf.com', 'role' => 'staff', 'task_role' => 'loan_advisor'],
    ['name' => 'HDFC Employee Demo', 'email' => 'hdfc@shf.com', 'role' => 'staff', 'task_role' => 'bank_employee', 'task_bank_id' => $hdfcBankId],
    ['name' => 'Office Staff Demo', 'email' => 'office@shf.com', 'role' => 'staff', 'task_role' => 'office_employee'],
    ['name' => 'Legal Advisor Demo', 'email' => 'legal@shf.com', 'role' => 'staff', 'task_role' => 'legal_advisor'],
    ['name' => 'Branch Manager Demo', 'email' => 'bm@shf.com', 'role' => 'admin', 'task_role' => 'branch_manager'],
];
```

---

## Summary of Changes from Original Plan

| Original Plan | Updated |
|---------------|---------|
| `task_role` values: branch_manager, mortgage_advisor, bank_employee, office_employee | **Added `legal_advisor`**, renamed `mortgage_advisor` → `loan_advisor` |
| No bank association for users | **Added `users.task_bank_id`** FK → banks (for bank_employee) |
| Assignment dropdown shows all task_role users | **Stage-aware filtering** via `STAGE_ROLE_ELIGIBILITY` constant |
| `findUserByRoleInBranch()` | Replaced with **`findEligibleUser()`** that handles bank matching |
| No Gujarati labels for task roles | **Added bilingual labels** |

---

## Documents That Need Updating

| Document | What Changed |
|----------|-------------|
| `stage-a-foundation.md` | Migration adds `task_bank_id`; User model constants updated; 5 task_role values |
| `stage-b-quotation-to-loan.md` | LoanConversionService uses `loan_advisor` not `mortgage_advisor` |
| `stage-e-stage-workflow.md` | Stage card assignment dropdown uses `eligibleForStage()` scope |
| `stage-f-parallel-processing.md` | Legal verification sub-stage includes `legal_advisor` role |
| `stage-i-workflow-config.md` | `default_assignee_role` dropdown includes `legal_advisor` |
| `stage-j-polish.md` | User management form includes bank dropdown, role labels updated |
