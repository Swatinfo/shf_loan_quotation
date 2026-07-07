# Customers & KYC

Customer identity in SHF is established **only at loan creation** (quotation→loan conversion and direct/manual loans) and is keyed by **PAN**. There is no "add customer" form — customers are never created from the Customers screen (read/edit only).

## Two tables

- **`customers`** — the identity anchor, one row per PAN. Created **once** when a PAN is first seen and then **never updated**. Columns: `customer_name, mobile, email, date_of_birth, pan_number` + audit + soft-deletes.
- **`customer_kyc_details`** — the **per-loan KYC snapshot** (the details as entered for that loan). One row per loan; this is what the loan **displays**, so differing details across deals are preserved instead of overwriting the master.

`loan_details.customer_id` → master; `loan_details.customer_kyc_details_id` → the snapshot.

## Write path — `CustomerService`

`app/Services/CustomerService.php`:

- `normalizePan($pan)` — uppercase + trim (null if empty).
- `resolveMasterByPan($kyc)` — find master by normalized PAN → reuse; else create (the only time a master is written; **never updates** an existing one).
- `recordKyc($master, $kyc, $context)` — write a `customer_kyc_details` snapshot (`source`: conversion/direct/edit/cleanup).
- `captureForLoan($kyc, $context)` — resolve master + record snapshot.
- `syncLoanKyc($loan, $kyc)` — on loan edit: if PAN unchanged, update the loan's existing snapshot **in place**; if PAN changed (or none yet), re-resolve/create master + link a new snapshot.

Wired into `LoanConversionService::convertFromQuotation()` (source `conversion`), `LoanConversionService::createDirectLoan()` (source `direct`), and `LoanController::update()` (source `edit`). **Quotations are NOT linked to customers** — identity is established at loan creation only.

## Autofill (PAN lookup)

`GET /customers/lookup?pan=…` (`customers.lookup`, permission `view_customers`) returns the latest known KYC details for a PAN **globally** (not visibility-scoped). On the convert / edit / (create) forms the **PAN field sits immediately after Product**; entering a valid PAN autofills empty phone/email/DOB (and name on edit) — non-destructive (never overwrites entered values).

## Display

Loan show reads the customer block (name/phone/email/PAN) from `loan->customerKycDetails`, falling back to the loan's inline columns for loans not yet backfilled.

## Uniqueness & one-time cleanup

- PAN uniqueness is enforced by `CustomerService` (find-or-create) plus a **unique index** on `customers.pan_number` (migration `add_unique_pan_index_to_customers`). SQLite/Postgres use a **partial** index (`WHERE deleted_at IS NULL AND pan_number IS NOT NULL`); MySQL uses a plain unique (merged duplicates get their PAN nulled so they don't collide). **The real app DB is MySQL; tests run on in-memory SQLite.**
- The `create_customer_kyc_details_table` migration **runs this backfill automatically** (guarded — only when loans already exist, so it's a no-op on fresh installs) right after creating the table, before the unique-index migration. The same logic is runnable on demand:
- **`php artisan customers:backfill-kyc [--dry-run]`** — builds a snapshot for every loan + dedupes masters by normalized PAN. Confidently-same duplicates (share mobile **or** DOB) are merged into the earliest row (PAN nulled + soft-deleted, loans repointed); same-PAN rows that look like **different people** (no shared mobile/DOB) are **NOT merged** — they're reported as conflicts to fix manually.
- The unique-index migration **aborts** if active duplicate PANs remain, so run order is: `customers:backfill-kyc` → resolve reported conflicts → `php artisan migrate` (adds the index).

Covered by `tests/Feature/CustomerKycTest.php`.
