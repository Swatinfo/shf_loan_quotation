# Roles

8 roles. Stored in `roles` table, assigned via `role_user` pivot (users can have multiple roles), granted permissions via `role_permission` pivot.

## Role catalogue

| Slug | Label (EN) | Label (GU) | can_be_advisor | is_system |
|---|---|---|---|---|
| `super_admin` | Super Admin | સુપર એડમિન | false | **true** |
| `admin` | Admin | એડમિન | false | **true** |
| `branch_manager` | Branch Manager | બ્રાન્ચ મેનેજર | **true** | false |
| `bdh` | Business Development Head | બિઝનેસ ડેવલપમેન્ટ હેડ | **true** | false |
| `loan_advisor` | Loan Advisor | લોન સલાહકાર | **true** | false |
| `bank_employee` | Bank Employee | બેંક કર્મચારી | false | false |
| `office_employee` | Office Employee | ઓફિસ કર્મચારી | false | false |
| `connector` | Connector | કનેક્ટર | false | **true** |

Gujarati labels come from `Role::gujaratiLabels()`.

**`connector`** (2026-10-05) — an external introducer. Perms: `create_quotation`, `edit_quotation`, `generate_pdf`, `view_own_quotations`, `download_pdf`, `download_pdf_plain` (**plain/unbranded only — no `download_pdf_branded`**), `change_own_password`, `view_dashboard`, `manage_notifications`. **No `convert_to_loan` / loan access.** The quotation UI gates its Convert / Branded / Plain buttons on these slugs, so a connector sees only the plain download and no convert. super_admin/admin/branch_manager/bdh convert a connector's quotation to a loan; the loan's payout can be directed to the connector (see payout feature). Seeded by `2026_10_05_160000_add_connector_role`. **Read-only loan tracking (2026-10-06):** a connector also holds `view_connector_loans` — they can VIEW (not act on) the loans created from their own quotations (list, loan detail, all stages + progress, timeline), scoped by `scopeVisibleTo` on `quotation.user_id`. All mutating loan routes stay blocked (connectors hold no action permissions).

## Flags

- **`is_system`** — true for `super_admin` and `admin`. Slug is locked (cannot be renamed) and role cannot be deleted via UI.
- **`can_be_advisor`** — true for roles allowed to be the loan advisor owner of a loan. `Role::advisorEligibleSlugs()` returns this list, cached for 5 minutes (invalidate via `Role::clearAdvisorCache()`).

## Default permissions by role

(Seeded by `DefaultDataSeeder::seedRolePermissionMappings()`; runtime-editable.)

### super_admin
All 45+ permissions **except** `skip_loan_stages` (explicitly disabled project-wide by migration).

### admin
Same as super_admin minus: `delete_users`, `delete_loan`, `download_pdf_branded`, `download_pdf_plain`.

### branch_manager / bdh (identical defaults, 20 perms)
- **Quotations**: create_quotation, generate_pdf, view_own_quotations, view_all_quotations, download_pdf
- **Users**: view_users
- **Loans**: convert_to_loan, view_loans, view_all_loans, create_loan, edit_loan, manage_loan_documents, manage_loan_stages, add_remarks
- **System**: change_own_password, view_activity_log
- **Extensions**: manage_customers, view_customers, view_dashboard, manage_notifications, transfer_loan_stages, reject_loan, change_loan_status, view_loan_timeline, manage_disbursement, manage_valuation, raise_query, resolve_query

### loan_advisor (~17 perms)
Quotations (own only): create_quotation, generate_pdf, view_own_quotations, download_pdf. Loans: convert_to_loan, view_loans, create_loan, edit_loan, manage_loan_documents, manage_loan_stages, add_remarks. Plus change_own_password, view_customers, view_dashboard, manage_notifications, transfer_loan_stages, change_loan_status, view_loan_timeline, manage_disbursement, raise_query, resolve_query.

### bank_employee (~7 perms)
view_loans, manage_loan_stages, add_remarks, change_own_password, view_customers, view_dashboard, manage_notifications, view_loan_timeline, raise_query.

### office_employee (~12 perms)
view_loans, edit_loan, manage_loan_documents, manage_loan_stages, add_remarks, change_own_password, view_customers, view_dashboard, manage_notifications, transfer_loan_stages, view_loan_timeline, manage_valuation, raise_query.

**DVR permissions** (`view_dvr`, `create_dvr`, etc.) are not assigned to any role by default — admins grant them per-role or per-user.

## Assignment

Users can hold multiple roles via `role_user` pivot. Any role that grants the permission is sufficient.

```php
$user->roles()->sync([$branchManagerRole->id, $loanAdvisorRole->id]);
$user->hasRole('loan_advisor');        // checks by slug
$user->hasAnyRole(['admin','bdh']);    // checks for any match
```

## Creating new roles

`RoleManagementController@store` — `/roles/create`:

1. Validates name (unique), slug (alpha_dash, unique), description, can_be_advisor.
2. Creates `Role` with `is_system=false`.
3. Optional: **copy from** another non-system role:
   - Copy permissions → syncs `role_permission`
   - Copy stage eligibility → updates `Stage.default_role` JSON and `Stage.sub_actions[i].role` as applicable
4. Clears `Role::clearAdvisorCache()` and `PermissionService::clearAllCaches()`.
5. Logs activity.

## Editing roles

`RoleManagementController@update`:
- System role slugs are locked (cannot change slug).
- Can change name, description, can_be_advisor.
- Permission checkboxes push through `role_permission` sync.
- Stage eligibility editor updates `Stage.default_role` array.
- Clears caches post-save.

## UI surfaces

- `/roles` — role list (user count per role, edit/delete)
- `/roles/create` — create role with copy-from option
- `/roles/{id}/edit` — edit role + permission matrix + stage eligibility
- `/permissions` — global non-Loans permission matrix (role × permission)
- `/loan-settings` Role Permissions tab — Loans-group permission matrix

## Workflow role resolution

Several features ask for a user's "primary workflow role":

- `User::workflowRoleLabel` / `workflowRoleLabelGu` — first non-system role
- `LoanDetail::userRoleSlug($user)` — priority order: bank_employee → office_employee → bdh → branch_manager → loan_advisor. Used for the stage assignment role suffix.

## See also

- `.claude/database-schema.md` — `roles`, `role_user`, `role_permission` tables
- `permissions.md` — permission catalogue and resolution
- `user-assignment.md` — how users get auto-assigned to stages by role
