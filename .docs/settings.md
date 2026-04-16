# Settings

## Overview

Application configuration is managed through `ConfigService` which reads from the `app_config` database table with `config/app-defaults.php` as fallback.

## ConfigService

### Storage
- **Table:** `app_config` with key='main' and `config_json` column (JSON cast)
- **Fallback:** `config/app-defaults.php` provides defaults
- **Merge:** DB values merged with defaults via `array_replace_recursive`
- **Sequential arrays:** DB values replace defaults entirely (deletions respected)

### Methods
- `load()` — load merged config
- `save(array)` — persist full config
- `reset()` — reset to defaults
- `get(dotKey)` — dot-notation access (e.g., `iomCharges.thresholdAmount`)
- `updateSection(section, value)` — update one section
- `updateMany(updates)` — batch update

### Important: JSON Casts
`AppConfig` model uses `'array'` cast on `config_json`. Always pass raw arrays to the model — never manually `json_encode` (causes double-encoding).

## Settings Page (`SettingsController`)

### Tabs and Permissions

| Tab | Route | Permission |
|-----|-------|------------|
| Company Info | settings.company | edit_company_info |
| IOM Charges | settings.charges | edit_charges |
| Bank Charges | settings.bank-charges | edit_charges |
| GST | settings.gst | edit_gst |
| Services | settings.services | edit_services |
| Banks | settings.banks | edit_banks |
| Tenures | settings.tenures | edit_tenures |
| Documents | settings.documents | edit_documents |
| DVR Contact Types | settings.dvr-contact-types | view_settings |
| DVR Purposes | settings.dvr-purposes | view_settings |

### Reset
`POST /settings/reset` — resets all config to defaults from `config/app-defaults.php`.

## Config Keys (`config/app-defaults.php`)

| Key | Type | Default |
|-----|------|---------|
| companyName | string | "Shreenathji Home Finance" |
| companyAddress | string | Office address |
| companyPhone | string | "+91 99747 89089" |
| companyEmail | string | "info@shf.com" |
| banks | array | ["HDFC Bank", "ICICI Bank", "Axis Bank", "Kotak Mahindra Bank"] |
| iomCharges.thresholdAmount | int | 10000000 (1 crore) |
| iomCharges.fixedCharge | int | 5500 |
| iomCharges.percentageAbove | float | 0.35 |
| tenures | array | [5, 10, 15, 20] |
| documents_en | object | Per customer_type document lists (English) |
| documents_gu | object | Per customer_type document lists (Gujarati) |
| dvrContactTypes | array | 6 types with key, label_en, label_gu |
| dvrPurposes | array | 7 purposes with key, label_en, label_gu |
| gstPercent | int | 18 |
| ourServices | string | Service description text |

## Loan Settings (separate page)

Managed by `LoanSettingsController` and `WorkflowConfigController`. See `workflow-developer.md`.

Covers: Banks, Products, Branches, Locations, Master Stages, Product Stage config, Task Role Permissions.

## View: `settings/index.blade.php`

Tabbed interface with form per tab. All tabs render inputs on page load (not lazy-loaded) to prevent data loss when switching tabs.

Tag inputs auto-add pending values on form submit.
