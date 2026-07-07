<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalized mirror of `disbursement_details.entries` (json) — one row per
 * tranche. The JSON stays the form's source of truth; rows here are updated
 * in place on edit, soft-deleted (deleted_by + deleted_at) when an entry is
 * removed, and `is_active` follows the loan status (0 while the loan is
 * cancelled / rejected / on_hold, 1 for active / completed).
 * Each JSON entry stores its mirror row's PK as `row_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursement_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loan_details')->cascadeOnDelete();
            $table->foreignId('disbursement_detail_id')->constrained('disbursement_details')->cascadeOnDelete();
            $table->date('disbursement_date')->nullable();
            $table->string('method', 20)->default('fund_transfer'); // fund_transfer | cheque
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name')->nullable();
            $table->string('loan_account_number', 50)->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('cheque_name', 100)->nullable();
            $table->string('cheque_number', 50)->nullable();
            $table->string('cheque_date', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('loan_id');
            $table->index('disbursement_detail_id');
        });

        $this->backfillFromJsonEntries();
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursement_entries');
    }

    private function backfillFromJsonEntries(): void
    {
        $inactiveStatuses = ['cancelled', 'rejected', 'on_hold'];

        DB::table('disbursement_details')
            ->whereNotNull('entries')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($inactiveStatuses) {
                foreach ($rows as $row) {
                    $entries = json_decode($row->entries, true);
                    if (empty($entries)) {
                        continue;
                    }

                    $loanStatus = DB::table('loan_details')->where('id', $row->loan_id)->value('status');
                    $isActive = ! in_array($loanStatus, $inactiveStatuses);
                    $changed = false;

                    foreach ($entries as $i => $entry) {
                        if (! empty($entry['row_id'])) {
                            continue;
                        }

                        $rowId = DB::table('disbursement_entries')->insertGetId([
                            'loan_id' => $row->loan_id,
                            'disbursement_detail_id' => $row->id,
                            'disbursement_date' => $entry['disbursement_date'] ?? null,
                            'method' => $entry['method'] ?? 'fund_transfer',
                            'product_id' => $entry['product_id'] ?? null,
                            'product_name' => $entry['product_name'] ?? null,
                            'loan_account_number' => $entry['loan_account_number'] ?? null,
                            'amount' => (int) ($entry['amount'] ?? 0),
                            'cheque_name' => $entry['cheque_name'] ?? null,
                            'cheque_number' => $entry['cheque_number'] ?? null,
                            'cheque_date' => $entry['cheque_date'] ?? null,
                            'is_active' => $isActive,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $entries[$i]['row_id'] = $rowId;
                        $changed = true;
                    }

                    if ($changed) {
                        DB::table('disbursement_details')
                            ->where('id', $row->id)
                            ->update(['entries' => json_encode(array_values($entries))]);
                    }
                }
            });
    }
};
