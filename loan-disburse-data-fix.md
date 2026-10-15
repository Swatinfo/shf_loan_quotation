# Loan / Disbursement Data Fix — Production Runbook

Independent data corrections tied to the Oct‑2026 fixes. **Each section has a SHOW query
(run first, confirm the rows) and an UPDATE query (run only after confirming).** All UPDATEs are
wrapped in a transaction and are **idempotent** (re‑running after the fix affects 0 rows).

> **Section 4 is the complete Rate & PF recompute** (PF GST, Total PF, Admin GST, Total Admin) and
> supersedes Section 1 (which fixed only the severe comma‑bug admin cases). If you run Section 4 you
> do not also need Section 1.

> ⚠️ Take a DB backup / snapshot before running. Run SHOW → eyeball the rows → run UPDATE →
> run the VERIFY query (should return 0 rows). Run inside a transaction so you can `ROLLBACK`
> if the affected count looks wrong.

Database: `shf_all_operations` (MySQL/MariaDB). `disbursement_details` is **not** soft‑deleted;
`disbursement_entries` **is** (`deleted_at`). Stage data lives in `stage_assignments.notes` (JSON).

---

## Section 1 — Rate & PF: wrong Admin GST / Total Admin

**Why:** the Rate & PF form read the comma‑formatted Admin Charges input with `parseFloat`
(`parseFloat("4,250") = 4`), so `admin_charges_gst_amount` and `total_admin_charges` were saved
far too low (e.g. admin ₹4,250 → GST ₹0.72, Total ₹4.72). `admin_charges` itself is correct.
The code bug is fixed separately; this only repairs already‑saved rows.

Corrupted rows are detectable precisely: a correct row always has
`total_admin_charges >= admin_charges` (total = admin + GST). Any row where total < admin is broken.

### 1a. SHOW affected rows (run first)

```sql
SELECT
    sa.loan_id,
    sa.id AS stage_assignment_id,
    JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges'))             AS admin_charges,
    JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges_gst_percent')) AS gst_percent,
    JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges_gst_amount'))  AS stored_gst_amount,
    JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.total_admin_charges'))       AS stored_total,
    ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges')) AS DECIMAL(15,2))
          * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges_gst_percent')) AS DECIMAL(6,2)) / 100, 2)
                                                                        AS correct_gst_amount,
    ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges')) AS DECIMAL(15,2))
          + ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges')) AS DECIMAL(15,2))
                  * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges_gst_percent')) AS DECIMAL(6,2)) / 100, 2), 2)
                                                                        AS correct_total
FROM stage_assignments sa
WHERE sa.stage_key = 'rate_pf'
  AND JSON_VALID(sa.notes) = 1
  AND (
        -- top-level (the current/bank-reviewed values)
        ( JSON_EXTRACT(sa.notes, '$.total_admin_charges') IS NOT NULL
          AND CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.total_admin_charges')) AS DECIMAL(15,2))
              < CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges')) AS DECIMAL(15,2)) )
        -- original_values snapshot (what the advisor first entered, shown as "Original:" hints)
     OR ( JSON_EXTRACT(sa.notes, '$.original_values.total_admin_charges') IS NOT NULL
          AND CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.original_values.total_admin_charges')) AS DECIMAL(15,2))
              < CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.original_values.admin_charges')) AS DECIMAL(15,2)) )
      )
ORDER BY sa.loan_id;
```

Expected today (6 loans): **306, 318, 323, 335, 341** (admin 5,000 → GST 900, Total 5,900) and
**347** (admin 4,250 → GST 765, Total 5,015).

> ⚠️ The corrupted GST/total live in **two** places in the notes JSON: the **top-level** fields (the
> current/bank-reviewed values, shown in the form) AND the **`original_values`** snapshot (the
> advisor's first entry, shown as the red "Original: ₹…" hints). 1b fixes both — run **both** UPDATEs.

### 1b. UPDATE (run after confirming 1a)

```sql
START TRANSACTION;

UPDATE stage_assignments sa
SET sa.notes = JSON_SET(
        sa.notes,
        '$.admin_charges_gst_amount',
        CAST(ROUND(
            CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges')) AS DECIMAL(15,2))
            * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges_gst_percent')) AS DECIMAL(6,2)) / 100
        , 2) AS CHAR),
        '$.total_admin_charges',
        CAST(ROUND(
            CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges')) AS DECIMAL(15,2))
            + ROUND(
                CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges')) AS DECIMAL(15,2))
                * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges_gst_percent')) AS DECIMAL(6,2)) / 100
            , 2)
        , 2) AS CHAR)
    ),
    sa.updated_at = NOW()
WHERE sa.stage_key = 'rate_pf'
  AND JSON_VALID(sa.notes) = 1
  AND JSON_EXTRACT(sa.notes, '$.admin_charges') IS NOT NULL
  AND JSON_EXTRACT(sa.notes, '$.total_admin_charges') IS NOT NULL
  AND CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.total_admin_charges')) AS DECIMAL(15,2))
      < CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges')) AS DECIMAL(15,2));

-- Confirm the affected-row count, then run the original_values UPDATE below in the
-- same transaction, re-check, then COMMIT.

UPDATE stage_assignments sa
SET sa.notes = JSON_SET(
        sa.notes,
        '$.original_values.admin_charges_gst_amount',
        CAST(ROUND(
            CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.original_values.admin_charges')) AS DECIMAL(15,2))
            * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.original_values.admin_charges_gst_percent')) AS DECIMAL(6,2)) / 100
        , 2) AS CHAR),
        '$.original_values.total_admin_charges',
        CAST(ROUND(
            CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.original_values.admin_charges')) AS DECIMAL(15,2))
            + ROUND(
                CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.original_values.admin_charges')) AS DECIMAL(15,2))
                * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.original_values.admin_charges_gst_percent')) AS DECIMAL(6,2)) / 100
            , 2)
        , 2) AS CHAR)
    ),
    sa.updated_at = NOW()
WHERE sa.stage_key = 'rate_pf'
  AND JSON_VALID(sa.notes) = 1
  AND JSON_EXTRACT(sa.notes, '$.original_values.admin_charges') IS NOT NULL
  AND JSON_EXTRACT(sa.notes, '$.original_values.admin_charges_gst_percent') IS NOT NULL
  AND JSON_EXTRACT(sa.notes, '$.original_values.total_admin_charges') IS NOT NULL
  AND CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.original_values.total_admin_charges')) AS DECIMAL(15,2))
      < CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.original_values.admin_charges')) AS DECIMAL(15,2));

-- Confirm both affected counts look right (≈6 rows total touched), then:
COMMIT;
-- ROLLBACK;  -- if the count is unexpected
```

> Note: some `original_values` snapshots may not carry `admin_charges_gst_percent`; the WHERE skips
> those (they can't be recomputed safely). None of today's 6 loans are affected by that — all carry 18%.

### 1c. VERIFY (should return 0 rows)

Re‑run the **1a SHOW query** — it must return no rows after both UPDATEs.

---

## Section 2 — Disbursement / OTC state mismatch

**Why:** loan status + stage + the `disbursed_amount` mirror are all derived from the disbursement
entries by `DisbursementService::syncDisbursementState()`. When the disbursement stage is completed by
the generic "complete stage" action (or via reset‑then‑re‑advance), `otc_clearance` is opened but the
resolver is never re‑invoked — so the persisted state can drift from the entries: a fully‑settled loan
stays open, a loan that started disbursing stays `active`, or the `disbursed_amount` column goes stale.
Re‑running the resolver re‑derives the correct state. Today only **loan 289** is affected.

### 2a. SHOW affected loans (run first)

```sql
SELECT
    ld.id                              AS loan_id,
    ld.status                          AS loan_status,
    ld.current_stage,
    ld.disbursed_amount,
    COUNT(e.id)                        AS active_entries,
    SUM(CASE WHEN e.otc_status NOT IN ('cleared','skipped') THEN 1 ELSE 0 END) AS unsettled_entries,
    SUM(e.amount)                      AS entries_sum,
    COALESCE(MAX(dd.pf_amount),0)      AS pf_amount,
    COALESCE(MAX(dd.admin_charges),0)  AS admin_charges,
    SUM(e.amount) + COALESCE(MAX(dd.pf_amount),0) + COALESCE(MAX(dd.admin_charges),0) AS gross,
    ld.sanctioned_amount,
    MAX(dd.completion_intent)          AS completion_intent,
    (SELECT status FROM stage_assignments s WHERE s.loan_id = ld.id AND s.stage_key = 'otc_clearance')  AS otc_stage_status,
    (SELECT status FROM stage_assignments s WHERE s.loan_id = ld.id AND s.stage_key = 'disbursement')   AS disbursement_stage_status,
    CASE
        WHEN SUM(CASE WHEN e.otc_status NOT IN ('cleared','skipped') THEN 1 ELSE 0 END) = 0
             AND ( MAX(dd.completion_intent) = 'full'
                   OR SUM(e.amount) + COALESCE(MAX(dd.pf_amount),0) + COALESCE(MAX(dd.admin_charges),0) >= ld.sanctioned_amount )
            THEN 'STUCK_COMPLETE — fully settled + money done, but loan not completed'
        WHEN ld.status = 'active'
            THEN 'STATUS_DRIFT — has tranches but status still active (should be partial_disbursed)'
        WHEN ld.disbursed_amount IS NULL
             OR ld.disbursed_amount <> SUM(e.amount) + COALESCE(MAX(dd.pf_amount),0) + COALESCE(MAX(dd.admin_charges),0)
            THEN 'AMOUNT_MIRROR_DRIFT — disbursed_amount column != gross'
        ELSE 'OK'
    END                                AS diagnosis
FROM loan_details ld
JOIN disbursement_entries e
      ON e.loan_id = ld.id AND e.is_active = 1 AND e.deleted_at IS NULL
LEFT JOIN disbursement_details dd
      ON dd.loan_id = ld.id
WHERE ld.status NOT IN ('completed','cancelled','rejected')
GROUP BY ld.id, ld.status, ld.current_stage, ld.disbursed_amount, ld.sanctioned_amount
HAVING diagnosis <> 'OK'
ORDER BY ld.id;
```

The three mismatch classes this catches:
- **STUCK_COMPLETE** — all tranches OTC-settled and money fully disbursed, but the loan never flipped
  to `completed` (the generic "complete stage" / reset-re-advance gap).
- **STATUS_DRIFT** — tranches exist but the loan is still `active` instead of `partial_disbursed`.
- **AMOUNT_MIRROR_DRIFT** — the `loan_details.disbursed_amount` mirror column disagrees with the GROSS
  (Σ active entry amounts + PF + admin). Cosmetic (reports read the entries table, not this column),
  fixed by the optional query in 2d.

Expected today: **loan 289 only** → `STUCK_COMPLETE` (3 entries all `cleared`, gross 68,00,000 =
sanctioned, intent `full`, status still `active`). No mirror drift exists in the DB right now.

### 2b. UPDATE — RECOMMENDED method (resolver via tinker)

This runs the exact service path and re‑derives the state: promotes `active → partial_disbursed`,
completes `otc_clearance` + flips the loan to `completed` when fully settled, recalculates
`loan_progress` (counts + snapshot), and fires the completion notification once. Fixes
**STUCK_COMPLETE** and **STATUS_DRIFT**. (It does not rewrite the `disbursed_amount` mirror column —
use 2d for `AMOUNT_MIRROR_DRIFT`.) Idempotent — safe to run for each id returned by 2a.

```bash
# Replace 289 with the loan id(s) from 2a. One call per loan.
php artisan tinker --execute 'app(\App\Services\DisbursementService::class)->syncDisbursementState(\App\Models\LoanDetail::find(289));'
```

(If running the whole 2a set at once:)

```bash
php artisan tinker --execute '\App\Models\LoanDetail::whereIn("id", [289])->get()->each(fn($l) => app(\App\Services\DisbursementService::class)->syncDisbursementState($l));'
```

### 2c. UPDATE — RAW SQL fallback (only if tinker is unavailable)

> ⚠️ This updates the two core tables but **does not** refresh `loan_progress.workflow_snapshot`
> or send the "loan completed" notification. `loan_progress` counts are set below; the snapshot
> JSON re‑syncs the next time the loan is touched in the app. Prefer 2b when possible.
> Set `@completed_by` to the acting user id (e.g. the OTC stage assignee or an admin).

```sql
START TRANSACTION;

SET @loan_id      = 289;
SET @completed_by = 26;   -- valid, active user id

-- 1) otc_clearance stage → completed
UPDATE stage_assignments
SET status = 'completed', completed_at = NOW(), completed_by = @completed_by, updated_at = NOW()
WHERE loan_id = @loan_id AND stage_key = 'otc_clearance' AND status = 'in_progress';

-- 2) loan → completed
UPDATE loan_details
SET status = 'completed', current_stage = 'otc_clearance',
    status_changed_at = NOW(), status_changed_by = @completed_by, updated_at = NOW()
WHERE id = @loan_id AND status <> 'completed';

-- 3) loan_progress counts (main stages only = stage_assignments.is_parallel_stage = 0
--    AND parent_stage_key IS NULL — mirrors the mainStages() scope)
UPDATE loan_progress lp
JOIN (
    SELECT sa.loan_id,
           COUNT(*)                                                           AS total_stages,
           SUM(CASE WHEN sa.status IN ('completed','skipped') THEN 1 ELSE 0 END) AS completed_stages
    FROM stage_assignments sa
    WHERE sa.loan_id = @loan_id
      AND sa.is_parallel_stage = 0
      AND sa.parent_stage_key IS NULL
    GROUP BY sa.loan_id
) agg ON agg.loan_id = lp.loan_id
SET lp.total_stages      = agg.total_stages,
    lp.completed_stages  = agg.completed_stages,
    lp.overall_percentage = ROUND(agg.completed_stages / agg.total_stages * 100, 2),
    lp.updated_at        = NOW();

-- Confirm 1 row each updated, then:
COMMIT;
-- ROLLBACK;  -- if anything looks off
```

### 2d. UPDATE — optional, AMOUNT_MIRROR_DRIFT only (realign `disbursed_amount` to GROSS)

Only needed for rows diagnosed `AMOUNT_MIRROR_DRIFT` (none today). Reports read the entries table, so
this column is cosmetic; realign it only if you rely on it directly. Run **after** 2b (completing a
loan can change which rows are "open").

```sql
START TRANSACTION;

UPDATE loan_details ld
JOIN (
    SELECT e.loan_id,
           SUM(e.amount) + COALESCE(MAX(dd.pf_amount),0) + COALESCE(MAX(dd.admin_charges),0) AS gross
    FROM disbursement_entries e
    LEFT JOIN disbursement_details dd ON dd.loan_id = e.loan_id
    WHERE e.is_active = 1 AND e.deleted_at IS NULL
    GROUP BY e.loan_id
) g ON g.loan_id = ld.id
SET ld.disbursed_amount = g.gross, ld.updated_at = NOW()
WHERE ld.status NOT IN ('completed','cancelled','rejected')
  AND (ld.disbursed_amount IS NULL OR ld.disbursed_amount <> g.gross);

-- Confirm the affected count matches the AMOUNT_MIRROR_DRIFT rows in 2a, then:
COMMIT;
-- ROLLBACK;
```

### 2e. VERIFY (should return 0 rows)

Re‑run the **2a SHOW query** — no rows should remain. Also confirm the loan reads `completed`:

```sql
SELECT id, status, current_stage FROM loan_details WHERE id = 289;
```

---

## Section 3 — Reset‑leftover / duplicate disbursement entries

**Why:** on a stage reset to `disbursement` or earlier, `resetClearRelatedData` hard‑deletes the
`disbursement_details` header but **does not** delete the child `disbursement_entries` (and the
`status→active` step reactivates them). A later disbursement redo creates a *new* detail;
`syncEntryRows` only reconciles entries of the **current** detail, so the old rows survive as
leftovers — showing up as duplicate/extra cheques and inflating the disbursed total.

> This DB is currently **clean** (all three scans below return 0 rows). Run them on **production**
> to catch any real leftovers there. The code fix (Part C) stops new ones.

### 3a. SHOW — scan for corruption (run all three; all should be empty)

```sql
-- (i) entries whose parent detail no longer exists (orphaned by a reset)
SELECT e.loan_id, e.id AS entry_id, e.disbursement_detail_id, e.amount, e.method,
       e.cheque_number, e.otc_status, ld.status AS loan_status
FROM disbursement_entries e
JOIN loan_details ld ON ld.id = e.loan_id
LEFT JOIN disbursement_details dd ON dd.id = e.disbursement_detail_id
WHERE e.is_active = 1 AND e.deleted_at IS NULL AND dd.id IS NULL
ORDER BY e.loan_id, e.id;
```

```sql
-- (ii) active entries pointing to a DIFFERENT detail than the loan's current one
--      (leftover from a pre-reset disbursement that was replaced on redo)
SELECT e.loan_id, e.id AS entry_id, e.disbursement_detail_id,
       (SELECT MAX(dd.id) FROM disbursement_details dd WHERE dd.loan_id = e.loan_id) AS current_detail_id,
       e.amount, e.method, e.cheque_number, e.otc_status
FROM disbursement_entries e
WHERE e.is_active = 1 AND e.deleted_at IS NULL
  AND e.disbursement_detail_id <> (SELECT MAX(dd.id) FROM disbursement_details dd WHERE dd.loan_id = e.loan_id)
ORDER BY e.loan_id, e.id;
```

```sql
-- (iii) duplicate cheque rows (same loan + cheque number + amount, more than once)
SELECT loan_id, cheque_number, amount, COUNT(*) AS copies,
       GROUP_CONCAT(id ORDER BY id) AS entry_ids,
       GROUP_CONCAT(disbursement_detail_id ORDER BY id) AS detail_ids
FROM disbursement_entries
WHERE is_active = 1 AND deleted_at IS NULL
  AND method = 'cheque' AND cheque_number IS NOT NULL AND cheque_number <> ''
GROUP BY loan_id, cheque_number, amount
HAVING copies > 1
ORDER BY loan_id;
```

### 3b. UPDATE — soft‑delete the leftovers (only if 3a(i)/(ii) returned rows)

Leftovers = active entries that do **not** belong to the loan's current (latest) disbursement detail.
Soft‑delete them, then re‑run the resolver (Section 2b) for each affected loan to re‑derive
status / amount / stage.

```sql
START TRANSACTION;

SET @deleted_by = 1;   -- acting super-admin user id

UPDATE disbursement_entries e
SET e.deleted_at = NOW(), e.is_active = 0, e.deleted_by = @deleted_by, e.updated_at = NOW()
WHERE e.is_active = 1 AND e.deleted_at IS NULL
  AND e.disbursement_detail_id <> (
        SELECT * FROM (
            SELECT MAX(dd.id) FROM disbursement_details dd WHERE dd.loan_id = e.loan_id
        ) x
  );

-- Review the affected-row count against 3a, then:
COMMIT;
-- ROLLBACK;
```

> After 3b, re‑run **Section 2b** (resolver) for each `loan_id` that had leftovers so its status /
> `disbursed_amount` / OTC stage are recomputed from the surviving entries.

### 3c. VERIFY

Re‑run the three **3a** scans — all must return 0 rows.

---

## Section 4 — Recompute ALL Rate & PF derived charges (PF GST, Total PF, Admin GST, Total Admin)

**Why:** the complete fix for the Rate & PF stage. Each derived field is recomputed from the stored
**base** amounts and GST %:
- `pf_gst_amount`  = round(`processing_fee_amount` × `gst_percent` / 100, 2)
- `total_pf`       = `processing_fee_amount` + `pf_gst_amount`
- `admin_charges_gst_amount` = round(`admin_charges` × `admin_charges_gst_percent` / 100, 2)
- `total_admin_charges`      = `admin_charges` + `admin_charges_gst_amount`

Both in the **top-level** notes and the **`original_values`** snapshot.

> **Guardrails — read before running:**
> - The **base** amounts (`processing_fee_amount`, `admin_charges`) are **never changed** — only the
>   derived GST/totals are recomputed. `processing_fee_amount` is *not* recomputed from the loan amount
>   (the loan amount can change at KFS, after rate_pf).
> - Rows where a base is **0 are skipped.** In particular, loans whose admin charge is captured at
>   **disbursement time** (stored on `disbursement_details`, with `admin_charges = 0` in the rate_pf
>   notes but non-zero GST/total) are **left untouched** — this section never recomputes those and
>   never touches `disbursement_details`.
> - Touches **only `stage_assignments.notes` for `stage_key = 'rate_pf'`.**

### 4a. SHOW — rate_pf rows whose derived charges don't match the stored base amounts

```sql
SELECT sa.loan_id, sa.status,
    JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.processing_fee_amount'))    AS pf_amount,
    JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.pf_gst_amount'))            AS pf_gst_stored,
    ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.processing_fee_amount')) AS DECIMAL(15,2))
        * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.gst_percent')) AS DECIMAL(9,4)) / 100, 2) AS pf_gst_correct,
    JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.total_pf'))                 AS total_pf_stored,
    JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges'))            AS admin_charges,
    JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges_gst_amount')) AS admin_gst_stored,
    ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges')) AS DECIMAL(15,2))
        * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges_gst_percent')) AS DECIMAL(9,4)) / 100, 2) AS admin_gst_correct,
    JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.total_admin_charges'))      AS total_admin_stored
FROM stage_assignments sa
WHERE sa.stage_key = 'rate_pf' AND JSON_VALID(sa.notes) = 1
  AND (
    -- top-level PF derived wrong (base > 0)
    ( CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.processing_fee_amount')) AS DECIMAL(15,2)) > 0
      AND ABS(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.pf_gst_amount')) AS DECIMAL(15,2))
            - ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.processing_fee_amount')) AS DECIMAL(15,2))
              * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.gst_percent')) AS DECIMAL(9,4)) / 100, 2)) > 0.01 )
    -- top-level Admin derived wrong (base > 0; admin_charges = 0 rows are disbursement-time → skipped)
 OR ( CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges')) AS DECIMAL(15,2)) > 0
      AND ABS(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges_gst_amount')) AS DECIMAL(15,2))
            - ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges')) AS DECIMAL(15,2))
              * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.admin_charges_gst_percent')) AS DECIMAL(9,4)) / 100, 2)) > 0.01 )
    -- original_values PF derived wrong (base > 0)
 OR ( CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.original_values.processing_fee_amount')) AS DECIMAL(15,2)) > 0
      AND ABS(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.original_values.pf_gst_amount')) AS DECIMAL(15,2))
            - ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.original_values.processing_fee_amount')) AS DECIMAL(15,2))
              * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.original_values.gst_percent')) AS DECIMAL(9,4)) / 100, 2)) > 0.01 )
    -- original_values Admin derived wrong (base > 0)
 OR ( CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.original_values.admin_charges')) AS DECIMAL(15,2)) > 0
      AND ABS(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.original_values.admin_charges_gst_amount')) AS DECIMAL(15,2))
            - ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.original_values.admin_charges')) AS DECIMAL(15,2))
              * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes, '$.original_values.admin_charges_gst_percent')) AS DECIMAL(9,4)) / 100, 2)) > 0.01 )
  )
ORDER BY sa.loan_id;
```

### 4b. UPDATE (four statements in one transaction; run after confirming 4a)

Values are stored trimmed of trailing decimal zeros (e.g. `900.00` → `900`, `747.90` → `747.9`) to
match the app's format. Each statement is guarded by its own `base > 0` + mismatch condition.

```sql
START TRANSACTION;

-- (i) top-level PF: pf_gst_amount + total_pf
UPDATE stage_assignments sa
SET sa.notes = JSON_SET(sa.notes,
      '$.pf_gst_amount',
      TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM CAST(
        ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.processing_fee_amount')) AS DECIMAL(15,2))
            * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.gst_percent')) AS DECIMAL(9,4))/100, 2) AS CHAR))),
      '$.total_pf',
      TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM CAST(
        ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.processing_fee_amount')) AS DECIMAL(15,2))
            + ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.processing_fee_amount')) AS DECIMAL(15,2))
            * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.gst_percent')) AS DECIMAL(9,4))/100, 2), 2) AS CHAR)))),
    sa.updated_at = NOW()
WHERE sa.stage_key='rate_pf' AND JSON_VALID(sa.notes)=1
  AND CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.processing_fee_amount')) AS DECIMAL(15,2)) > 0
  AND ABS(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.pf_gst_amount')) AS DECIMAL(15,2))
        - ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.processing_fee_amount')) AS DECIMAL(15,2))
          * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.gst_percent')) AS DECIMAL(9,4))/100, 2)) > 0.01;

-- (ii) top-level Admin: admin_charges_gst_amount + total_admin_charges  (admin_charges = 0 skipped)
UPDATE stage_assignments sa
SET sa.notes = JSON_SET(sa.notes,
      '$.admin_charges_gst_amount',
      TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM CAST(
        ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.admin_charges')) AS DECIMAL(15,2))
            * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.admin_charges_gst_percent')) AS DECIMAL(9,4))/100, 2) AS CHAR))),
      '$.total_admin_charges',
      TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM CAST(
        ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.admin_charges')) AS DECIMAL(15,2))
            + ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.admin_charges')) AS DECIMAL(15,2))
            * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.admin_charges_gst_percent')) AS DECIMAL(9,4))/100, 2), 2) AS CHAR)))),
    sa.updated_at = NOW()
WHERE sa.stage_key='rate_pf' AND JSON_VALID(sa.notes)=1
  AND CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.admin_charges')) AS DECIMAL(15,2)) > 0
  AND ABS(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.admin_charges_gst_amount')) AS DECIMAL(15,2))
        - ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.admin_charges')) AS DECIMAL(15,2))
          * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.admin_charges_gst_percent')) AS DECIMAL(9,4))/100, 2)) > 0.01;

-- (iii) original_values PF
UPDATE stage_assignments sa
SET sa.notes = JSON_SET(sa.notes,
      '$.original_values.pf_gst_amount',
      TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM CAST(
        ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.original_values.processing_fee_amount')) AS DECIMAL(15,2))
            * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.original_values.gst_percent')) AS DECIMAL(9,4))/100, 2) AS CHAR))),
      '$.original_values.total_pf',
      TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM CAST(
        ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.original_values.processing_fee_amount')) AS DECIMAL(15,2))
            + ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.original_values.processing_fee_amount')) AS DECIMAL(15,2))
            * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.original_values.gst_percent')) AS DECIMAL(9,4))/100, 2), 2) AS CHAR)))),
    sa.updated_at = NOW()
WHERE sa.stage_key='rate_pf' AND JSON_VALID(sa.notes)=1
  AND JSON_EXTRACT(sa.notes,'$.original_values.processing_fee_amount') IS NOT NULL
  AND CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.original_values.processing_fee_amount')) AS DECIMAL(15,2)) > 0
  AND ABS(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.original_values.pf_gst_amount')) AS DECIMAL(15,2))
        - ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.original_values.processing_fee_amount')) AS DECIMAL(15,2))
          * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.original_values.gst_percent')) AS DECIMAL(9,4))/100, 2)) > 0.01;

-- (iv) original_values Admin  (admin_charges = 0 skipped)
UPDATE stage_assignments sa
SET sa.notes = JSON_SET(sa.notes,
      '$.original_values.admin_charges_gst_amount',
      TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM CAST(
        ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.original_values.admin_charges')) AS DECIMAL(15,2))
            * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.original_values.admin_charges_gst_percent')) AS DECIMAL(9,4))/100, 2) AS CHAR))),
      '$.original_values.total_admin_charges',
      TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM CAST(
        ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.original_values.admin_charges')) AS DECIMAL(15,2))
            + ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.original_values.admin_charges')) AS DECIMAL(15,2))
            * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.original_values.admin_charges_gst_percent')) AS DECIMAL(9,4))/100, 2), 2) AS CHAR)))),
    sa.updated_at = NOW()
WHERE sa.stage_key='rate_pf' AND JSON_VALID(sa.notes)=1
  AND JSON_EXTRACT(sa.notes,'$.original_values.admin_charges') IS NOT NULL
  AND CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.original_values.admin_charges')) AS DECIMAL(15,2)) > 0
  AND ABS(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.original_values.admin_charges_gst_amount')) AS DECIMAL(15,2))
        - ROUND(CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.original_values.admin_charges')) AS DECIMAL(15,2))
          * CAST(JSON_UNQUOTE(JSON_EXTRACT(sa.notes,'$.original_values.admin_charges_gst_percent')) AS DECIMAL(9,4))/100, 2)) > 0.01;

-- Review the four affected-row counts, then:
COMMIT;
-- ROLLBACK;
```

### 4c. VERIFY (should return 0 rows)

Re‑run the **4a SHOW query** — it must return no rows after the four UPDATEs.

> On the local dev DB this already returns 0: the admin comma-bug loans (306/318/323/335/341/347) are
> fixed in both locations, PF is correct everywhere, and the 11 `admin_charges = 0` disbursement-time
> loans (104/129/140/152/219/278/288/295/298/302/305) are correctly **left untouched**.

---

## Notes
- Run **Section 1, Section 2 and Section 3 independently**; they touch different tables and neither
  depends on the other (but within Section 3, run 3b before the Section 2b re-resolve).
- These fixes repair existing data only. The code fixes (comma parsing in the Rate & PF form; the
  disbursement‑complete → `syncDisbursementState` trigger; reset cascade‑deleting entries) prevent
  new occurrences and must be deployed separately.
- After deploying code, also run `php artisan view:clear`.
