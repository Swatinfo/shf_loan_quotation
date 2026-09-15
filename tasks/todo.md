# TODO — Active-only listings + copy-btn fixes + 403 page + more copy sites

## STATUS: DONE — implemented + tested (153/153 loan/dashboard/stage suite green).

- [x] Copy button parse-time fix (SHF.copyBtn top-level) → shows on dashboard My Tasks/Loan Tasks.
- [x] Copy button color orange (--accent).
- [x] Active-only default: loanData (else active; status=all bypass), loans index blade + loans.js
      (default Active, Clear→active, counter baseline), dashboard widgets (->active / where active:
      my-loan-tasks, KPI, tab counts, recent loans, pipeline-by-stage, open queries, bank-mix-MTD),
      GeneralTask::scopeWithActiveLinks (linked loan must be active).
- [x] Themed 403 page (errors/403.blade.php) — header nav + clear message.
- [x] Copy buttons: customer listing (name/mobile/email/pan, table+cards), DVR page + dashboard DVR
      (contact name + phone). Loan Acct # column already on loans list + dashboard.
- [x] Tests: LoanListingActiveDefaultTest, Error403PageTest. Versions bumped 20260831160000.

## Note
- Reverses the 2026-07-07 "show all statuses by default" decision per explicit user request.
- Field-activity + DVR follow-ups are DVR-based (no loan status) → left unchanged.
