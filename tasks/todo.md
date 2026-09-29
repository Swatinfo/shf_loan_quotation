# TODO — Stage-Breakdown: per-stage dates (not loan-creation cohort)

## STATUS: DONE — per-stage dates live; disbursement reconciles with Management report (Sep ₹8.77 Cr = report).

## Rule (locked with user)
Each bucket is filtered by its OWN stage event date; where the event hasn't
happened (pending placeholders → no timestamp) fall back to the loan's created_at:
    bucketDate = <stage event date> ?? loan.created_at
    include ⇔ bucketDate ∈ [from,to]   ('All time' = no bound)

## Per-bucket date + amount
- Sanction (sanction_decision):
  - sip: started_at (in_progress) | created_at (pending)
  - query: latest active query.created_at on sanction_decision
  - sanctioned: completed_at
  - hold/withdrawn: loan.status_changed_at
  - rejected: loan.rejected_at ?? sanction_decision.completed_at
- Technical/Legal (own stage_key):
  - not_initiated: created_at  | under_process: started_at
  - completed: completed_at | query: query.created_at | rejected: completed_at
- Disbursement:
  - spill: docket.started_at | logged_in: docket.completed_at (amount = sanctioned_amount)
  - entry: tranche disbursement_date; INCLUDE iff ≥1 tranche in window; AMOUNT = Σ in-window tranches
  - otc: otc_clearance.completed_at ?? loan.status_changed_at ?? created_at; amount = Σ all tranches

## Steps
- [ ] `loans()` — drop created_at query filter; load all visible+scope loans; eager-load
      stageAssignments(started_at,completed_at), stageQueries(created_at), entries(disbursement_date),
      loan status_changed_at + rejected_at.
- [ ] `bucketDate(loan, section, bucket)` + `inWindow(date, window)` + `bucketInWindow(...)`
      (entry special: hasTrancheInWindow); `windowedTrancheAmount(loan, window)`.
- [ ] `aggregate()` — date-gate each classified bucket; entry amount = windowed tranches;
      block total = DISTINCT loans appearing in any in-window bucket + Σ their loan_amount.
- [ ] `loanIdsFor()` — apply the same date gate so click-through matches.
- [ ] Tests — pending-in-window → Not Initiated; completed in/out of window; entry amount =
      Σ in-window tranches (reconciles with Management report); each in-progress dates by started_at.
- [ ] Verify live: Disbursement Entry total vs Management report "Disbursed" for a period.

## Notes
- Sanctioned stays the sanction_decision gate (user's original sketch), not the report's sanction-letter stage.
- Backend only (no JS/CSS) → no asset bump. Docs + lessons after.
- Test runner: `php -c .scratch/php-test.ini vendor/phpunit/phpunit/phpunit --filter=StageBreakdownTest`
