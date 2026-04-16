# Plan: Fix quotations deleted by SeedScreenshotLoans

## Context

After running `php artisan migrate:fresh --seed` then `php artisan app:seed-screenshot-loans`, quotations disappear but loans show up. The user expects both to coexist.

## Root Cause

The flow is:

```
1. migrate:fresh --seed
   └── DatabaseSeeder → DefaultDataSeeder
       └── Seeds 18 quotations with bank options + EMI data ✓

2. app:seed-screenshot-loans
   └── cleanExistingData() (line 179)
       └── loans:purge --force --with-quotations=true
           └── DELETES ALL from:
               - quotation_documents
               - quotation_emi
               - quotation_banks
               - quotations          ← 18 quotations gone
       └── Then creates test loans directly (no quotations)
```

**The `--with-quotations` flag in `SeedScreenshotLoans::cleanExistingData()` is the culprit.** It purges all quotation data even though the screenshot loans command only needs to clear loan data, not quotations.

## Fix

**File:** `app/Console/Commands/SeedScreenshotLoans.php` (line 179)

Change:
```php
$this->call('loans:purge', ['--force' => true, '--with-quotations' => true]);
```
To:
```php
$this->call('loans:purge', ['--force' => true]);
```

This keeps `--with-quotations` as `false` (the default), so only loan tables are purged. The 18 seeded quotations survive.

## After the fix, the flow becomes:

```
1. migrate:fresh --seed → 18 quotations seeded ✓
2. app:seed-screenshot-loans
   └── loans:purge --force (WITHOUT --with-quotations)
       └── Deletes loan_details, stage_assignments, etc.
       └── Quotations PRESERVED ✓
   └── Creates test loans ✓
```

## Verification

```bash
php artisan migrate:fresh --seed
php artisan app:seed-screenshot-loans
# Then check:
php artisan tinker --execute="echo 'Quotations: ' . \App\Models\Quotation::count() . ', Loans: ' . \App\Models\LoanDetail::count();"
# Expected: Quotations: 18, Loans: (screenshot loan count)
```

Also verify on dashboard that both Quotations tab and Loans tab show data.
