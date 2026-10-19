#!/usr/bin/env bash
#
# Backup the LIVE versions of the files changed in this deploy, BEFORE you
# overwrite them with the new code. Run this ON THE SERVER, from your Laravel
# project root (or pass the root as the first argument).
#
#   bash _deploy/backup-before-deploy.sh          # project root = the parent of _deploy
#   bash backup-before-deploy.sh /var/www/shf     # explicit project root
#
# Produces: backup-before-deploy-YYYYmmdd-HHMMSS.zip  (only files that already exist)
#
set -euo pipefail

# Default ROOT = the project root, resolved as the parent of THIS script's folder
# (the script lives in <project>/_deploy), so it works from any CWD.
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="${1:-$(cd "$SCRIPT_DIR/.." && pwd)}"
cd "$ROOT"

STAMP="$(date +%Y%m%d-%H%M%S)"
OUT="backup-before-deploy-$STAMP.zip"
LIST="$(mktemp)"
trap 'rm -f "$LIST"' EXIT

# The files changed in this deploy (repo-relative paths).
FILES="
.claude/rules/coding-feedback.md
.claude/rules/project-context.md
.claude/services-reference.md
.docs/loans.md
.docs/settings.md
app/Http/Controllers/DashboardController.php
app/Http/Controllers/LoanController.php
app/Http/Controllers/PayoutController.php
app/Http/Controllers/PayoutRunController.php
app/Http/Controllers/ReportController.php
app/Models/DisbursementEntry.php
app/Models/LoanDetail.php
app/Services/LoanPipelineBreakdownService.php
app/Services/PayoutRunService.php
resources/views/newtheme/loans/_stage-readonly-detail.blade.php
resources/views/newtheme/loans/_stages-body.blade.php
resources/views/newtheme/loans/index.blade.php
resources/views/newtheme/payouts/_product-filter.blade.php
resources/views/newtheme/payouts/reconcile.blade.php
resources/views/newtheme/payouts/report.blade.php
resources/views/newtheme/payouts/runs.blade.php
resources/views/newtheme/payouts/run-show.blade.php
routes/web.php
tasks/lessons.md
tasks/todo.md
tests/Feature/ConnectorLoanVisibilityTest.php
tests/Feature/LoanListingAppNumberAndSubStageTest.php
tests/Feature/LoanVisibilityScopeTest.php
tests/Feature/PayoutReconcileTest.php
tests/Feature/PayoutRunControllerTest.php
"

missing=0
for f in $FILES; do
  if [ -f "$f" ]; then
    printf '%s\n' "$f" >> "$LIST"
  else
    echo "  (new file on this deploy, nothing to back up): $f"
    missing=$((missing + 1))
  fi
done

if [ ! -s "$LIST" ]; then
  echo "No existing files found to back up (is '$ROOT' the Laravel project root?). Aborting."
  exit 1
fi

if command -v zip >/dev/null 2>&1; then
  zip -q "$OUT" -@ < "$LIST"
else
  # Fallback when zip is not installed: produce a .tar.gz instead.
  OUT="backup-before-deploy-$STAMP.tar.gz"
  tar -czf "$OUT" -T "$LIST"
fi

echo "-------------------------------------------------------------"
echo "Backup written to : $(pwd)/$OUT"
echo "Files backed up   : $(wc -l < "$LIST")"
echo "New files skipped : $missing"
echo "Keep this file safe — to roll back, extract it over the project root."
