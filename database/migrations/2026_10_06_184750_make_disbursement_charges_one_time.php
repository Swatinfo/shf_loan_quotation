<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PF / Admin / Insurance become ONE-TIME per disbursement (loan-level) instead of
 * per tranche. `disbursement_details.{pf_amount,admin_charges,insurance_amount}`
 * are now authoritative (they already held the per-entry sums). Consolidate any
 * drift, then drop the now-unused per-tranche columns on disbursement_entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Safety consolidation: header := Σ active per-entry charges (already the
        // case via processDisbursement, but guard against any drift before drop).
        foreach (['pf_amount', 'admin_charges', 'insurance_amount'] as $col) {
            // Portable correlated UPDATE (no table alias — SQLite rejects aliases here).
            DB::statement(
                "UPDATE disbursement_details SET {$col} = COALESCE((
                    SELECT SUM(e.{$col}) FROM disbursement_entries e
                    WHERE e.disbursement_detail_id = disbursement_details.id AND e.deleted_at IS NULL
                ), {$col})"
            );
        }

        Schema::table('disbursement_entries', function (Blueprint $table) {
            $table->dropColumn(['pf_amount', 'admin_charges', 'insurance_amount']);
        });
    }

    public function down(): void
    {
        Schema::table('disbursement_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('pf_amount')->default(0)->after('amount');
            $table->unsignedBigInteger('admin_charges')->default(0)->after('pf_amount');
            $table->unsignedBigInteger('insurance_amount')->default(0)->after('admin_charges');
        });

        // Best-effort restore: put the whole header charge on the first tranche.
        DB::table('disbursement_details')->orderBy('id')->each(function ($d) {
            $first = DB::table('disbursement_entries')
                ->where('disbursement_detail_id', $d->id)->whereNull('deleted_at')
                ->orderBy('id')->first();
            if ($first) {
                DB::table('disbursement_entries')->where('id', $first->id)->update([
                    'pf_amount' => $d->pf_amount ?? 0,
                    'admin_charges' => $d->admin_charges ?? 0,
                    'insurance_amount' => $d->insurance_amount ?? 0,
                ]);
            }
        });
    }
};
