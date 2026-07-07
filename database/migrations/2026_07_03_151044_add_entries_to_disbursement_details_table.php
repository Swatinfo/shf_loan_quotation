<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Disbursement becomes a list of tranches stored in `entries` (json).
 * Entry shape: { disbursement_date (Y-m-d), method (fund_transfer|cheque),
 *   product_id, product_name, loan_account_number, amount,
 *   cheque_name?, cheque_number?, cheque_date? (d/m/Y, as typed) }.
 *
 * Legacy columns (`amount_disbursed`, `disbursement_type`, `disbursement_date`,
 * `bank_account_number`, `cheques`) are kept and derived from entries at write
 * time so existing read sites (OTC-skip logic, timeline, listings) keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disbursement_details', function (Blueprint $table) {
            $table->json('entries')->nullable()->after('cheques');
        });

        $this->backfillEntries();
    }

    public function down(): void
    {
        Schema::table('disbursement_details', function (Blueprint $table) {
            $table->dropColumn('entries');
        });
    }

    private function backfillEntries(): void
    {
        DB::table('disbursement_details')
            ->whereNull('entries')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $loan = DB::table('loan_details')->where('id', $row->loan_id)->first();
                    $productId = $loan->product_id ?? null;
                    $productName = $productId
                        ? DB::table('products')->where('id', $productId)->value('name')
                        : null;

                    $base = [
                        'disbursement_date' => $row->disbursement_date,
                        'product_id' => $productId,
                        'product_name' => $productName,
                        'loan_account_number' => $row->bank_account_number,
                    ];

                    $cheques = $row->cheques ? json_decode($row->cheques, true) : [];

                    if ($row->disbursement_type === 'cheque' && ! empty($cheques)) {
                        $entries = array_map(fn (array $chq): array => $base + [
                            'method' => 'cheque',
                            'amount' => (int) ($chq['cheque_amount'] ?? 0),
                            'cheque_name' => $chq['cheque_name'] ?? null,
                            'cheque_number' => $chq['cheque_number'] ?? null,
                            'cheque_date' => $chq['cheque_date'] ?? null,
                        ], $cheques);
                    } elseif ($row->amount_disbursed) {
                        $entries = [$base + [
                            'method' => $row->disbursement_type ?: 'fund_transfer',
                            'amount' => (int) $row->amount_disbursed,
                        ]];
                    } else {
                        continue;
                    }

                    DB::table('disbursement_details')
                        ->where('id', $row->id)
                        ->update(['entries' => json_encode(array_values($entries))]);
                }
            });
    }
};
