# Settings

Three settings pages manage application configuration: **Quotation Settings**, **Loan Settings**, and **Product Stage Configuration**.

---

## ConfigService Pattern

All quotation-related settings use the `ConfigService` (`app/Services/ConfigService.php`) which provides a key-value config store backed by the `app_config` table.

### How It Works

1. **Storage**: Single row in `app_config` table with `config_key = 'main'` and `config_json` (JSON column)
2. **Fallback**: If no DB record exists, initializes from `config/app-defaults.php`
3. **Merge**: DB values are merged with defaults via `array_replace_recursive`, ensuring new default keys are always available. Sequential arrays (lists) are replaced entirely from DB to respect deletions.
4. **Model**: `AppConfig` with `config_json` cast to `array`

### Key Methods

| Method | Purpose |
|--------|---------|
| `load(): array` | Load full config (DB merged with defaults) |
| `get(string $key, $default)` | Get single value by dot-notation key |
| `updateSection(string $section, $value)` | Update one top-level key |
| `updateMany(array $updates)` | Update multiple keys at once |
| `save(array $config)` | Write full config to DB |
| `reset(): array` | Reset to `config/app-defaults.php` defaults |

### Important: JSON Cast Rule

The `AppConfig` model casts `config_json` as `'array'`. When passing data to `updateSection` or `updateMany`, pass raw arrays — never manually `json_encode()` or you get double-encoding.

---

## Quotation Settings Page

**Route**: `GET /settings` — `SettingsController@index`
**Permission**: `view_settings` (to view), individual permissions per section (to save)
**View**: `resources/views/settings/index.blade.php`

### Tabs

| Tab | Permission to Save | Description |
|-----|-------------------|-------------|
| **Company** | `edit_company_info` | Company name, address, phone, email |
| **Banks** | — (read-only) | Shows banks from Loan Settings (`Bank` model). Link to Loan Settings for editing. |
| **IOM Stamp Paper** | `edit_charges` | Threshold amount, fixed charge, percentage above threshold |
| **Bank Charges** | `edit_charges` | Per-bank charges table: PF%, admin, stamp/notary, registration, advocate, TC, 2 custom extras |
| **GST** | `edit_gst` | GST percentage applied on PF & admin charges |
| **Services** | `edit_services` | Comma-separated services list shown in PDF footer |
| **Tenures** | `edit_tenures` | EMI tenure options in years (tag-style input) |
| **Documents** | `edit_documents` | Required documents per customer type, bilingual (EN + GU). Sub-tabs for proprietor, partnership_llp, pvt_ltd, salaried. |
| **Permissions** | `manage_permissions` | User permission management |

### Save Endpoints

| Route | Method | Permission | Controller Method |
|-------|--------|-----------|-------------------|
| `POST /settings/company` | POST | `edit_company_info` | `updateCompany` |
| `POST /settings/banks` | POST | `edit_banks` | `updateBanks` |
| `POST /settings/tenures` | POST | `edit_tenures` | `updateTenures` |
| `POST /settings/documents` | POST | `edit_documents` | `updateDocuments` |
| `POST /settings/charges` | POST | `edit_charges` | `updateCharges` |
| `POST /settings/bank-charges` | POST | `edit_charges` | `updateBankCharges` |
| `POST /settings/services` | POST | `edit_services` | `updateServices` |
| `POST /settings/gst` | POST | `edit_gst` | `updateGst` |
| `POST /settings/reset` | POST | `view_settings` | `reset` |

### Bank Charges Detail

Bank charges use a separate `bank_charges` table (not ConfigService). On save, the table is truncated and re-inserted. Each row contains:
- `bank_name`, `pf` (percentage), `admin`, `stamp_notary`, `registration_fee`, `advocate`, `tc`
- Optional: `extra1_name`/`extra1_amt`, `extra2_name`/`extra2_amt`

### Default Config Values (`config/app-defaults.php`)

```
companyName, companyAddress, companyPhone, companyEmail
banks: [HDFC, ICICI, Axis, Kotak]
iomCharges: { thresholdAmount: 10000000, fixedCharge: 5500, percentageAbove: 0.35 }
tenures: [5, 10, 15, 20]
documents_en: { proprietor: [...], partnership_llp: [...], pvt_ltd: [...], salaried: [...] }
documents_gu: { proprietor: [...], partnership_llp: [...], pvt_ltd: [...], salaried: [...] }
gstPercent: 18
ourServices: "Home Loan, Mortgage Loan, ..."
```

---

## Loan Settings Page

**Route**: `GET /loan-settings` — `LoanSettingsController@index`
**Permission**: `view_loans` (to view), `manage_workflow_config` (to edit)
**View**: `resources/views/loan-settings/index.blade.php`

### Tabs

| Tab | Description |
|-----|-------------|
| **Locations** | Manage states and cities (hierarchical). Branches, users, and products are assigned to locations. |
| **Banks** | Create/edit/delete banks with optional code and location assignments |
| **Branches** | Create/edit/delete branches with manager assignment and location |
| **Stage Master** | Enable/disable stages globally, set default roles per stage, configure sub-action roles and enablement |
| **Products & Stages** | Bank products with per-product stage configuration link |
| **Role Permissions** | Loan-group permission matrix for workflow roles |

### Locations Tab

CRUD for the `locations` table (hierarchical: state -> city).

| Route | Method | Description |
|-------|--------|-------------|
| `POST /loan-settings/locations` | POST | Create or update location |
| `DELETE /loan-settings/locations/{location}` | DELETE | Delete (blocked if has children or branches) |

Validation: type (state/city), parent_id (required for city), name, optional code.

### Banks Tab

Managed by `WorkflowConfigController`. Banks are shared between quotation and loan systems.

| Route | Method | Description |
|-------|--------|-------------|
| `POST /loan-settings/banks` | POST | Create or update bank |
| `DELETE /loan-settings/banks/{bank}` | DELETE | Delete (blocked if has products or active loans) |

Each bank has: `name` (unique), `code` (unique, optional), location assignments (many-to-many).

### Branches Tab

| Route | Method | Description |
|-------|--------|-------------|
| `POST /loan-settings/branches` | POST | Create or update branch |
| `DELETE /loan-settings/branches/{branch}` | DELETE | Delete (blocked if has assigned users or active loans) |

Fields: name (unique), code (unique, optional), address, city, phone, manager_id (required), location_id.

### Stage Master Tab

Configure the global stage definitions in the `stages` table.

**Save route**: `POST /loan-settings/master-stages` — `LoanSettingsController@saveMasterStages`

Per stage:
- **is_enabled**: Toggle stage on/off globally. Disabling a stage also disables it in all product stage configs.
- **default_role**: Array of role slugs that can be assigned this stage by default.
- **sub_actions**: Array of sub-action configurations, each with `roles` (array of role slugs) and `is_enabled`.

### Products & Stages Tab

Lists all products grouped by bank. Each product has a "Configure Stages" link that opens the product-stage configuration page.

| Route | Method | Description |
|-------|--------|-------------|
| `POST /loan-settings/products` | POST | Create or update product |
| `DELETE /loan-settings/products/{product}` | DELETE | Delete (blocked if has active loans) |
| `POST /loan-settings/products/{product}/locations` | POST | Sync product locations |

Products have: `bank_id`, `name` (unique per bank), `code` (optional).

### Role Permissions Tab

Matrix editor for loan-group permissions across workflow roles.

**Save route**: `POST /loan-settings/task-role-permissions` — `LoanSettingsController@saveTaskRolePermissions`

- Shows all permissions in the `Loans` group
- Columns: one per workflow role (excluding `super_admin`)
- On save: clears existing loan-group role_permission entries and re-inserts selected ones
- Clears permission cache via `PermissionService::clearAllCaches()`

---

## Product Stage Configuration Page

**Route**: `GET /loan-settings/products/{product}/stages` — `WorkflowConfigController@productStages`
**Permission**: `manage_workflow_config`
**View**: `resources/views/settings/workflow-product-stages.blade.php`

**Save route**: `POST /loan-settings/products/{product}/stages` — `WorkflowConfigController@saveProductStages`

### Per-Stage Configuration

Each enabled stage can be configured with:

| Field | Description |
|-------|-------------|
| `is_enabled` | Enable/disable this stage for this product |
| `default_assignee_role` | Role slug for auto-assignment |
| `default_user_id` | Specific user for auto-assignment |
| `auto_skip` | Auto-skip this stage during workflow |
| `sub_actions_override` | Per sub-action: `is_enabled`, `roles`, `users`, `default_user`, `location_overrides` |

### Branch-wise User Assignments

Per stage, users can be assigned per branch via the `product_stage_users` table:
- Multiple users per branch
- One user marked as `is_default` per branch
- Stored in `ProductStageUser` model with `product_stage_id`, `branch_id`, `user_id`, `is_default`

### Location-based User Assignments

Separate from branch assignments. Per stage, users can be assigned per location:
- Stored in `ProductStageUser` with `branch_id = null`, `location_id` set
- Sub-actions also support location overrides

### Data Model

```
Product -> ProductStage (per stage) -> ProductStageUser (per branch or location)
```

`ProductStage` fields: `product_id`, `stage_id`, `is_enabled`, `default_assignee_role`, `default_user_id`, `auto_skip`, `sub_actions_override` (JSON)

---

## Permission Requirements Summary

| Page | View Permission | Edit Permission |
|------|----------------|----------------|
| Quotation Settings | `view_settings` | Per-section (see table above) |
| Loan Settings | `view_loans` | `manage_workflow_config` |
| Product Stage Config | `manage_workflow_config` | `manage_workflow_config` |
| Activity Log | `view_activity_log` | — (read-only) |
| Settings Reset | `view_settings` | `view_settings` |

---

## Activity Logging

All settings changes are logged via `ActivityLog::log()`:

| Action | Trigger |
|--------|---------|
| `settings_updated` | Any quotation settings section save (with `section` in properties) |
| `settings_reset` | Full settings reset to defaults |
| `save_product_stages` | Product stage configuration save (with product/bank name) |
| `role_permissions_updated` | Role permission matrix save |

---

## Key Files

| File | Purpose |
|------|---------|
| `app/Http/Controllers/SettingsController.php` | Quotation settings CRUD |
| `app/Http/Controllers/LoanSettingsController.php` | Loan settings index, master stages, locations, role permissions |
| `app/Http/Controllers/WorkflowConfigController.php` | Banks, branches, products, product stage config |
| `app/Services/ConfigService.php` | Config read/write/merge logic |
| `config/app-defaults.php` | Default quotation config values |
| `app/Models/AppConfig.php` | Config DB model (`config_key` + `config_json`) |
| `app/Models/BankCharge.php` | Bank charges model |
| `app/Models/ProductStage.php` | Product-stage config model |
| `app/Models/ProductStageUser.php` | Branch/location user assignments |
| `resources/views/settings/index.blade.php` | Quotation settings view |
| `resources/views/loan-settings/index.blade.php` | Loan settings view |
| `resources/views/settings/workflow-product-stages.blade.php` | Product stage config view |
