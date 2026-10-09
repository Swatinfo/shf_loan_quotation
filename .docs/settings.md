# Settings & Config

Two settings surfaces:

1. **`/settings`** — quotation-side config (`SettingsController`). Company info, banks list, bank charges, tenures, documents by customer type, IOM charges, GST, services, DVR vocab.
2. **`/loan-settings`** — workflow / org config (`LoanSettingsController` + `WorkflowConfigController`). Locations, branches, banks/products, master stages, product stages + user assignments, role permissions.

Both are stored differently:

- `/settings` data goes to `app_config` table (single row, key `main`) and `bank_charges` table.
- `/loan-settings` data goes to structured tables: `locations`, `branches`, `banks`, `products`, `stages`, `product_stages`, `product_stage_users`, `bank_stage_configs`, `role_permission`.

## ConfigService — the glue

`app/Services/ConfigService.php` is the only code that should read/write `app_config.main`. Controllers call it; nothing else.

### Methods

| Method | Purpose |
|---|---|
| `load(): array` | Merged config (defaults + DB). Seeds from defaults on first call if table empty. |
| `save(array)` | Upsert `app_config.main` |
| `reset(): array` | Overwrite DB with `config('app-defaults')` |
| `get(string $key, $default)` | Dot-notation read (`iomCharges.fixedCharge`) |
| `updateSection(string $key, $value)` | Dot-notation write + save |
| `updateMany(array $updates)` | Batch dot-notation writes + single save |

### Merge behavior (important)

`mergeWithDefaults()` uses `array_replace_recursive($defaults, $loaded)` then `replaceSequentialArrays()` walks the merged tree and **replaces any sequential (indexed) array entirely** with the loaded DB value.

Why: so a user deleting an entry in a list (e.g., a bank, a tenure, a document) stays deleted — otherwise the default would re-appear after next merge.

- Assoc arrays (`iomCharges: {thresholdAmount, fixedCharge, percentageAbove}`) merge per key.
- Sequential arrays (`banks: ["HDFC Bank", ...]`, `tenures: [5, 10, 15, 20]`, `documents_en.proprietor: [...]`) replace entirely.

### Double-encode pitfall

`AppConfig.config_json` is cast to `array`. **Never `json_encode()` before saving** — the cast handles serialization. Double-encoding produces a JSON-escaped string stored as JSON.

## `/settings` — tabs

Controller: `SettingsController`. View: `resources/views/settings/index.blade.php`.

All endpoints require `auth`. Specific permission per tab — see `.claude/routes-reference.md`.

| Tab | POST route | Permission | Notes |
|---|---|---|---|
| Company | `/settings/company` | `edit_company_info` | companyName, Address, Phone, Email |
| Banks | `/settings/banks` | `edit_banks` | `banks[]` list; dedup + sort; replaces entire list |
| Tenures | `/settings/tenures` | `edit_tenures` | `tenures[]` ints 1–50; dedup + numeric sort |
| Documents | `/settings/documents` | `edit_documents` | `documents_en[]`, `documents_gu[]` by customer type (4 tabs) |
| IOM Charges | `/settings/charges` | `edit_charges` | `iomCharges.thresholdAmount`, `fixedCharge`, `percentageAbove` |
| Bank Charges | `/settings/bank-charges` | `edit_charges` | Truncates `bank_charges`, bulk inserts rows from form |
| Services | `/settings/services` | `edit_services` | `ourServices` multiline string |
| GST | `/settings/gst` | `edit_gst` | `gstPercent` numeric 0–100 |
| DVR Contact Types | `/settings/dvr-contact-types` | `view_settings` | `dvrContactTypes[]` of `{key, label_en, label_gu}` |
| DVR Purposes | `/settings/dvr-purposes` | `view_settings` | `dvrPurposes[]` of `{key, label_en, label_gu}` |
| Quotation Hold Reasons | `/settings/quotation-hold-reasons` | `view_settings` | `quotationHoldReasons[]` of `{key, label_en, label_gu, group}` — `group` buckets the reason in the hold modal via `<optgroup>`; missing = `Other` |
| Quotation Cancel Reasons | `/settings/quotation-cancel-reasons` | `view_settings` | `quotationCancelReasons[]` of `{key, label_en, label_gu, group}` — same `<optgroup>` grouping |
| Quotation Referral | `/settings/quotation-referral-types` | `view_settings` | `quotationReferralTypes[]` of `{key, label_en, label_gu}` — feeds the "Referral Type" dropdown on the quotation form |
| Reset | `/settings/reset` | `view_settings` | Reset config, truncate bank_charges |

### Documents tab (important UX detail)

Customer types: `proprietor`, `partnership_llp`, `pvt_ltd`, `salaried`. Each has a separate document list.

**All tabs must render their inputs on page load**, not only the currently active tab — otherwise hidden tabs lose their data on save.

### Tag inputs

Auto-add pending typed values on form submit before posting. Users expect "Save" to capture text they typed but didn't hit Enter on.

## `/loan-settings` — workflow config

Controller: `LoanSettingsController` + delegation to `WorkflowConfigController`. View: `resources/views/loan-settings/index.blade.php`.

Permission to view: `view_loans`. Most write actions: `manage_workflow_config`.

Tabs (query param `?tab=`):

### Locations

`storeLocation` creates/edits `locations` (state or city with `parent_id`). `destroyLocation` blocks if the location has children or branches.

### Branches

`WorkflowConfigController::storeBranch` — name, code (unique), manager_id required, location_id (city) optional. `destroyBranch` blocks if users or active loans reference it.

### Banks / Products

`storeBank`, `destroyBank`, `storeProduct`, `destroyProduct`. Delete is blocked when dependent records exist. `saveProductLocations` syncs `location_product` pivot.

`storeProduct` now saves **identity only** (bank, name, code, uniqueness per bank). **Product payout config moved to the Payout Config tab** (2026-10-06) — see below. The product's Edit button on this tab populates name/code/bank only.

### Master Stages

`saveMasterStages` — one big form covering:
- Per-stage: `is_enabled`, `assigned_role`, `phase_roles[]` (for multi-phase stages)
- Per (bank, stage): `BankStageConfig` override

Only writes `bank_stage_configs` rows when overrides **differ** from master; otherwise removed. When a bank's role changes, downstream `product_stages` with stale overrides for that (bank, stage) are cleared so they re-resolve at runtime.

### Products → Stages

`productStages($product)` shows the detailed per-product config. `saveProductStages($product)` writes:

- `product_stages` row: `is_enabled`, `default_assignee_role`, `default_user_id`, `auto_skip`, `sub_actions_override` (per-phase roles/users)
- `product_stage_users`: stage-level default user, per-branch, per-location (city/state), per-phase

Multi-tier user resolution: branch → city → state → global default. See `user-assignment.md`.

**Auto-propagation on save**: after writing config, `saveProductStages` (scoped to that product) and `saveMasterStages` (all eligible loans) call `LoanStageService::propagateConfigToEligibleLoans()`. This rebuilds `loan_details.workflow_config` and re-points the **in_progress** stage owners of eligible loans — **eligible = any loan whose `status != 'completed'`** (active/on_hold/rejected/cancelled/disbursed-but-open) — that are **still on their auto-resolved default**; manually-transferred stages are preserved. The new owner is notified; the reassignment count is appended to the success flash.

**"Sync Settings" button** (Products & Stages tab): `POST /loan-settings/sync-stage-config` (`loan-settings.sync-stage-config`, `manage_workflow_config`) → `WorkflowConfigController::syncStageConfig()` → `propagateConfigToAllEligibleLoans()` loops every product and re-applies current config to its eligible loans. Native `confirm()` guard (this page has no Bootstrap/Swal).

### Role Permissions (Loans group only)

`saveTaskRolePermissions` — role × Loans-group permission matrix. Clears only Loans permissions per role, syncs selected. Does **not** touch non-Loans permissions (they're managed on `/permissions`).

Clears `PermissionService` caches after save.

### Payout Config

`WorkflowConfigController::savePayoutConfig` (`POST /loan-settings/payout-config`, `loan-settings.payout-config.save`, `manage_workflow_config`). **Unlike the other loan-settings tabs, this is config-backed** — stored in `app_config.main` under `payoutConfig` via `ConfigService::updateSection`, NOT a structured table. Four percentage rates: `admin_gst`, `pf_gst`, `user_tds`, `user_insurance`. Each `{ value (percent 0–100), calc (decimal = value/100), effective_from (Y-m-d, nullable) }`. The operator enters `value` + `effective_from`; `calc` is **derived server-side** (never trusted from the client) and shown read-only in the UI (JS mirrors it live as value÷100). The calc has no own date — it inherits the value's `effective_from`. `LoanSettingsController::index` passes `$payoutConfig` (merged defaults + DB).

**Product Payout sub-section** (moved here from Products & Stages, 2026-10-06): `WorkflowConfigController::savePayoutProduct` (`POST /loan-settings/payout-config/product`, `loan-settings.payout-product.save`, `manage_workflow_config`). Per-product `is_pf_based`, `max_payout_amount` cap, `payout_cycle_start_day`/`end_day` (1–31), and payout slabs (low/high range, internal type ₹-or-% + value, connector type + value) in `product_payout_slabs`. Each product row shows an **Effective <date>** badge (the current version's `effective_from`), and the **Edit Payout** button carries `data-effective` so the form's Effective From field is pre-filled with the current version's date — a save then updates that version rather than silently creating a new today-dated one (leave it blank/enter a future date to create a new version). A product list (grouped by bank) each has an **Edit Payout** button that populates one shared collapse form (hidden `product_id`); the slab repeater + validation (high>low, percent≤100, no overlap) is reused from the old product form. Slabs are replaced wholesale in a transaction. New products created on the Products tab get DB defaults (not PF, no cap, cycle 1–31) until edited here.

**Consumed by `PayoutRunService`** (aggregate payout runs, 2026-10-07 redesign): `pf_gst.calc`, `user_tds.calc`, `user_insurance.calc` drive each user's net payout in a run —
amount-based products: product-wide in-range disbursed **volume** picks the slab tier, each user earns `their disbursed volume × tier rate` (capped by `max_payout`, `-1` = uncapped). PF-based products are excluded from volume: commission on `pf_base = exGst(PF, pf_gst.calc)` × the PF slab rate, per the loan's payout user. Insurance = `insurance × user_insurance.calc` to the loan's payout user. Per user: `total = Σcommission + Σpf_payout + Σinsurance_payout`; `net = max(0, total − total × user_tds.calc)`. **No PF-GST deduction in the aggregate model** (PF base is already ex-GST). Snapshotted onto `payout_run_*` (run stores `insurance_rate`/`tds_rate`/`gst_rate`; each `payout_run_products` row stores the slab/version/tier/aggregate/max; `payout_run_users` stores the per-user rollup). Finalize stamps `disbursement_entries.payout_run_id` + `paid_amount_counted` and `disbursement_details.pf_payout_run_id`/`insurance_payout_run_id` for idempotency. `admin_gst` is not used in the payout.

`PayoutService` now holds only the shared helpers: `payoutRates()` (current GST rates for the disbursement form) and `breakdown()`/`computeAmount()` (per-entry informational breakdown on the **verify-only** reconcile screen). The old per-loan finalize engine (`previewFinalize`/`finalizePayout`/`finalizeEntries`) and the loan-page / reconcile finalize buttons were retired — payouts finalize only via **Payout Runs** (`payouts.runs`). The legacy `loan_payouts` ledger remains read-only history on the Payout Report.

**Effective-dated history (2026-10-06):** rates + product payout are now versioned (`payout_rate_versions`, `product_payout_versions`). A finalize resolves the version **in force on each tranche's disbursement date** (`PayoutConfigService::ratesAsOf()` / `productVersionAsOf()` — greatest `effective_from ≤ date`). Tranches straddling a rate/slab change are **split into one `loan_payouts` row per period**. Saving a rate/product with a new `effective_from` inserts a version and keeps the old one; the UI shows a per-rate / per-product history list. `payoutConfig` (app_config) and `products.*` + `products.current_payout_version_id` stay as today's denormalized mirrors (UI defaults, legacy reads); `Product::payoutSlabs` resolves to the current version's slabs via the pointer.

## Defaults file: `config/app-defaults.php`

Source of truth for **what the config SHOULD be** when first seeded or reset. Top-level keys:

| Key | Shape | Notes |
|---|---|---|
| `companyName`, `companyAddress`, `companyPhone`, `companyEmail` | string | Simple scalars |
| `banks` | `string[]` | Sequential — replaced entirely |
| `iomCharges` | `{ thresholdAmount:int, fixedCharge:int, percentageAbove:float }` | Assoc — merged |
| `tenures` | `int[]` | Sequential |
| `documents_en.{type}` | `string[]` per customer type | Sequential, 4 customer types |
| `documents_gu.{type}` | same in Gujarati | |
| `dvrContactTypes` | `[{key, label_en, label_gu}]` | Sequential |
| `dvrPurposes` | same | |
| `quotationReferralTypes` | `[{key, label_en, label_gu}]` | Sequential — quotation "Referral Type" dropdown |
| `gstPercent` | `int` | |
| `ourServices` | multiline string | |
| `payoutConfig` | `{ admin_gst, pf_gst, user_tds, user_insurance }` each `{ value:number, calc:float, effective_from:?Y-m-d }` | Assoc — merged per key; `calc` derived server-side (value/100) |

## Seed vs runtime

- **Seeder** (`DefaultDataSeeder`) populates structural tables (locations, banks, products, stages, roles).
- **Config defaults** populate `app_config.main` on first `ConfigService::load()` call.
- Settings UIs only mutate `app_config.main` (and `bank_charges`), never structural tables.

## Adding a new config key

1. Add to `config/app-defaults.php` with default value.
2. Add a form field on the relevant `/settings` tab view.
3. Add a `SettingsController` action that validates + calls `ConfigService::updateSection()` or `updateMany()`.
4. Add the route in `routes/web.php` with the appropriate permission.
5. Add permission to `config/permissions.php` if it's a new one (seeded via migration).
6. Update this doc.
7. Controller validates as usual; don't re-validate in ConfigService.

## See also

- `.claude/services-reference.md` — `ConfigService` methods
- `.claude/database-schema.md` — `app_config`, `app_settings`, `bank_charges` tables
- `permissions.md` — `edit_*` permissions
- `workflow-developer.md` — master stages + product stages deeply
