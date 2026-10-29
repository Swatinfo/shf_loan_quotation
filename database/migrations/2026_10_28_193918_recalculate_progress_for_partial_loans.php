<?php

use App\Models\LoanDetail;
use App\Services\LoanStageService;
use Illuminate\Database\Migrations\Migration;

/**
 * Partially disbursed / partially completed loans no longer count OTC Clearance as
 * done, so their stored progress must drop below 100%. Recalculates the
 * loan_progress row only — no stage or status changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $stages = app(LoanStageService::class);

        LoanDetail::query()
            ->whereIn('status', LoanDetail::PARTIAL_STATUSES)
            ->orderBy('id')
            ->chunkById(100, fn ($loans) => $loans->each(fn (LoanDetail $loan) => $stages->recalculateProgress($loan)));
    }

    public function down(): void
    {
        // Progress is derived data; nothing to restore.
    }
};
