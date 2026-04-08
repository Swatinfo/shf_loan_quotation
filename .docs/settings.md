# Settings System

## Overview

The settings module manages all configurable aspects of the application: company info, bank list, tenures, documents, charges, services, and GST. All settings are stored in the `app_config` database table with a fallback to `config/app-defaults.php`.

## Architecture

```
config/app-defaults.php           → Hardcoded defaults (fallback)
app_config table (key: 'main')    → User-modified settings (primary)
app/Services/ConfigService.php    → Load/save/merge logic
app/Http/Controllers/SettingsController.php → UI + 8 update endpoints
resources/views/settings/index.blade.php    → Tabbed settings UI
```

## Service: ConfigService

**File**: `app/Services/ConfigService.php`

### load(): array
- Reads from `app_config` where `config_key = 'main'`
- If no DB record: loads from `config/app-defaults.php` and saves to DB
- Merges loaded config with defaults (to pick up new default keys added in code)
- Uses `replaceSequentialArrays()` to handle list deletions correctly

### save(array $config): void
- Writes full config to `app_config` table via `updateOrCreate` with key `'main'`

### get(string $key, $default = null)
- Dot-notation access: `get('companyName')`, `get('tenures')`, `get('iomCharges.thresholdAmount')`
- Uses Laravel's `data_get()` helper

### updateSection(string $section, $value): array
- Updates one top-level key: `updateSection('banks', ['HDFC', 'ICICI'])`
- Uses `data_set()` for dot-notation support

### updateMany(array $updates): array
- Batch update: `updateMany(['banks' => [...], 'tenures' => [...]])`

### reset(): array
- Replaces DB config with `config/app-defaults.php` defaults
- Returns the fresh default config

### mergeWithDefaults($configJson): array
- Merges DB config with defaults so new default keys are always available
- `replaceSequentialArrays()`: Prevents deleted items from reappearing — if the user deletes a bank, the default bank list doesn't re-add it on next load

## Config Structure

The full config is a single JSON object stored in `app_config.config_json`:

Config sections: `companyName`, `companyAddress`, `companyPhone`, `companyEmail`, `banks`, `tenures`, `documents_en`, `documents_gu`, `iomCharges`, `gstPercent`, `ourServices`

```php
[
    'companyName' => 'Shreenathji Home Finance',
    'companyAddress' => '...',
    'companyPhone' => '...',
    'companyEmail' => '...',
    'banks' => ['HDFC', 'ICICI', 'Axis Bank', 'Kotak Mahindra'],
    'tenures' => [5, 10, 15, 20],
    'documents_en' => [
        'proprietor' => ['Doc 1 EN', ...],
        'partnership_llp' => [...],
        'pvt_ltd' => [...],
        'salaried' => [...],
    ],
    'documents_gu' => [
        'proprietor' => ['Doc 1 GU', ...],
        'partnership_llp' => [...],
        'pvt_ltd' => [...],
        'salaried' => [...],
    ],
    'iomCharges' => [
        'thresholdAmount' => 10000000,
        'fixedCharge' => 5500,
        'percentageAbove' => 0.35,
    ],
    'gstPercent' => 18,
    'ourServices' => 'Home Loan, LAP, Commercial, Industrial, Land Purchase, Overdraft',
]
```

## Controller: SettingsController

**File**: `app/Http/Controllers/SettingsController.php`

**Constructor**: Injects `ConfigService`

### Settings Page (`GET /settings`)

**Permission**: `view_settings`

- Loads full config via `ConfigService::load()`
- Loads bank charges from `BankCharge::orderBy('bank_name')` 
- Renders tabbed UI with permission-based tab visibility

### Tab: Company Info (`POST /settings/company`)

**Permission**: `edit_company_info`

**Validation**:
| Field | Rules |
|-------|-------|
| `companyName` | required, max:255 |
| `companyAddress` | required, max:500 |
| `companyPhone` | required, max:50 |
| `companyEmail` | required, email, max:255 |

**Action**: Updates company fields in config

### Tab: Banks (`POST /settings/banks`)

**Permission**: `edit_banks`

**Validation**:
| Field | Rules |
|-------|-------|
| `banks` | required, array, min:1 |
| `banks.*` | string, max:100 |

**Processing**: Removes duplicates and empty values before saving

### Tab: Tenures (`POST /settings/tenures`)

**Permission**: `edit_tenures`

**Validation**:
| Field | Rules |
|-------|-------|
| `tenures` | required, array, min:1 |
| `tenures.*` | integer, 1-50 |

**Processing**: Removes duplicates, converts to integers, sorts ascending

### Tab: Documents (`POST /settings/documents`)

**Permission**: `edit_documents`

**Validation**:
| Field | Rules |
|-------|-------|
| `documents_en` | required, array |
| `documents_gu` | required, array |

**Structure**: Documents are organized by customer type, each with EN + GU arrays. The form sends all types within each language array.

### Tab: IOM Stamp Paper Charges (`POST /settings/charges`)

**Permission**: `edit_charges`

**Validation**:
| Field | Rules |
|-------|-------|
| `iomCharges.thresholdAmount` | required, integer, min:0 |
| `iomCharges.fixedCharge` | required, integer, min:0 |
| `iomCharges.percentageAbove` | required, numeric, 0-100 |

### Tab: Bank Charges (`POST /settings/bank-charges`)

**Permission**: `edit_charges`

**Validation** (per bank in `charges` array):
| Field | Rules |
|-------|-------|
| `bank_name` | required, string |
| `pf` | required, numeric, 0-99.99 |
| `admin` | required, integer, min:0 |
| `stamp_notary` | required, integer, min:0 |
| `registration_fee` | required, integer, min:0 |
| `advocate` | required, integer, min:0 |
| `tc` | required, integer, min:0 |
| `extra1_name` | nullable, string |
| `extra1_amt` | nullable, integer |
| `extra2_name` | nullable, string |
| `extra2_amt` | nullable, integer |

**Action**: Truncates `bank_charges` table, re-inserts all charges

### Tab: Services (`POST /settings/services`)

**Permission**: `edit_services`

**Validation**: `ourServices` — required, string, max:1000

### Tab: GST (`POST /settings/gst`)

**Permission**: `edit_gst`

**Validation**: `gstPercent` — required, numeric, 0-100

### Reset (`POST /settings/reset`)

**Permission**: `view_settings`

- Calls `ConfigService::reset()` to restore defaults
- Truncates `bank_charges` table
- Logs activity: `settings_reset`

## View: settings/index.blade.php

**Layout**: extends `layouts.app`

**Tab Navigation**: Horizontal tabs with permission-based visibility
| Tab | Permission Required |
|-----|-------------------|
| Company | edit_company_info |
| Banks | edit_banks |
| IOM Stamp Paper | edit_charges |
| Bank Charges | edit_charges |
| GST | edit_gst |
| Services | edit_services |
| Tenures | edit_tenures |
| Documents | edit_documents |

**Tab Content Pattern**:
- Each tab is a `<div class="settings-tab-pane">` (shown/hidden via JS)
- Active tab tracked via `request('tab', 'company')` query parameter
- Each tab contains a `<form>` posting to its specific endpoint
- Forms use `shf-card` container with `shf-section` structure

**UI Elements**:
- **Tag/Chip Lists** (Banks, Tenures): Add/remove items with `+ Add` button, auto-submit pending input on save
- **Document Editor**: Tabbed by customer type, each tab has EN + GU textarea lists
- **Bank Charges Table**: Editable table with rows per bank, columns per charge type
- **Reset Button**: Red button in header with confirmation modal

## Defaults File

**File**: `config/app-defaults.php`

Returns the complete default config array. Used as:
1. Initial seed when no DB config exists
2. Fallback for missing keys after DB load
3. Reset target when "Reset to Defaults" is clicked

## Important Patterns

1. **Never hardcode settings**: Always use `ConfigService::get()` or load the full config
2. **Array cast warning**: The `AppConfig` model casts `config_json` to array. Pass raw arrays, never `json_encode()` manually (causes double-encoding)
3. **Tab auto-submit**: Settings forms with tag UI auto-add pending input on form submit (lesson from `tasks/lessons.md`)
4. **All tabs render on load**: Document tabs render all customer type inputs on page load, not just the active tab (otherwise only active tab data is submitted)

## Activity Logging

All settings changes log: `settings_updated` with `['section' => 'company|banks|tenures|...']`

Reset logs: `settings_reset` with no properties
