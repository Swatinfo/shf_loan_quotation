<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loan_details', function (Blueprint $table) {
            $table->unsignedBigInteger('sanctioned_amount')->nullable()->after('loan_amount');
            $table->unsignedBigInteger('disbursed_amount')->nullable()->after('sanctioned_amount');
        });

        $this->backfill();
    }

    /**
     * Backfill the new columns from their legacy sources:
     *   sanctioned_amount ← docket stage notes (sanction stage as legacy fallback)
     *   disbursed_amount  ← disbursement_details.amount_disbursed (authoritative)
     */
    private function backfill(): void
    {
        // Disbursed: one disbursement_details row per loan (loan_id UNIQUE).
        DB::table('disbursement_details')
            ->whereNotNull('amount_disbursed')
            ->orderBy('loan_id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('loan_details')
                        ->where('id', $row->loan_id)
                        ->update(['disbursed_amount' => $row->amount_disbursed]);
                }
            });

        // Sanctioned: parse the docket (preferred) / sanction (legacy) stage notes JSON.
        DB::table('stage_assignments')
            ->whereIn('stage_key', ['docket', 'sanction'])
            ->whereNotNull('notes')
            ->orderBy('loan_id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    $notes = json_decode($row->notes ?? '', true);
                    if (! is_array($notes)) {
                        continue;
                    }
                    $amount = $notes['sanctioned_amount'] ?? null;
                    if ($amount === null || $amount === '' || ! is_numeric($amount)) {
                        continue;
                    }

                    $loan = DB::table('loan_details')->where('id', $row->loan_id)->first(['sanctioned_amount']);
                    // docket wins over sanction: only let sanction fill when docket left it empty.
                    if ($loan && $loan->sanctioned_amount !== null && $row->stage_key === 'sanction') {
                        continue;
                    }
                    DB::table('loan_details')
                        ->where('id', $row->loan_id)
                        ->update(['sanctioned_amount' => (int) $amount]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_details', function (Blueprint $table) {
            $table->dropColumn(['sanctioned_amount', 'disbursed_amount']);
        });
    }
};
