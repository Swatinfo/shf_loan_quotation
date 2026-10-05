# TODO — Block stage actions while a query is open (stage-wise for parallel)

## STATUS: IN PROGRESS

## Rule
Active query = StageQuery status `pending` OR `responded` (only `resolved` unblocks).
While a stage has an active query, ALL its actions are hidden; only Raise/Respond/Resolve
remain. Parallel = per sub-stage (keyed on each sub's stage_key + assignment).

## Layer 1 — UI gating + "query open" indicator (_stages-body.blade.php + valuation-map.blade.php)
- [ ] Main stages: before action switch (~:1699) compute `$mainHasQuery = $assignment->activeQueries->isNotEmpty()`; when true render a 🔒 "Query — action blocked, resolve below" lock card INSTEAD of the action switch. Keep the Active-Queries banner (Respond/Resolve).
- [ ] Parallel subs: before sub switch (~:850) compute per-sub `$subHasQuery = $sub->activeQueries->isNotEmpty()`; lock card for that sub only. Gate bsm_osv, legal(+waive), technical_valuation(send-to-office + valuation link), ODV, sanction_decision. Siblings unaffected.
- [ ] Header chip "⚠ Query" on stage/sub header (stage-wise).
- [ ] valuation-map.blade.php: disable Save when technical_valuation has an active query.
- [ ] Eager-load stageAssignments.activeQueries on the stages page (kill N+1).

## Layer 2 — Server backstop: try/catch(\RuntimeException) → 422 {error} (AJAX) / redirect-back (valuation)
- [ ] LoanValuationController::store (:208/:210) → redirect back + error
- [ ] LoanStageController::legalAction (:405) → 422 (covers nested ODV :1004)
- [ ] LoanStageController::esignAction (:551) → 422
- [ ] LoanStageController::ratePfAction (:677) → 422
- [ ] LoanStageController::saveNotes auto-complete (:773/:775) → 422
- [ ] LoanDocumentController::store auto-complete (:60/:62) → 422
- (already safe: updateStatus, decisionAction, skip, disbursement)

## Layer 3 — JS
- [ ] `.fail` handlers: `responseJSON.error ?? responseJSON.message ?? 'Failed'` consistently.

## Tests
- [ ] Blade: stage/sub with active query → no action buttons + lock card + Resolve present.
- [ ] Stage-wise: query on technical_valuation blocks only it; legal/sanction still actionable.
- [ ] Responded still blocks; Resolve unblocks → completion succeeds.
- [ ] Server: each endpoint with open query → 422 / redirect-back, stage stays in_progress, no exception; nested ODV blocks legalAction cleanly.

## Deploy
- [ ] Bump SHF_VERSION/SHF_SW_VERSION + view:clear (views changed).

## Notes
- Branch: feat/per-entry-otc-partial-disbursed (OTC+partial+error-pages already committed/pushed).
- 4 stray *BKP.php files left untracked (flagged to user; belong in .ignore/bkp_files/).
