<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NEFT/RTGS transfer date — the date the fund transfer was done, parallel to
 * cheque_date for cheque tranches. Defaults to the entry's disbursement date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disbursement_entries', function (Blueprint $table) {
            $table->date('transfer_date')->nullable()->after('cheque_date');
        });
    }

    public function down(): void
    {
        Schema::table('disbursement_entries', function (Blueprint $table) {
            $table->dropColumn('transfer_date');
        });
    }
};
