<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-entry OTC (handover) clearance. Each disbursement tranche — cheque AND
 * fund transfer — now carries its own handover lifecycle instead of a single
 * loan-level otc_clearance event:
 *   - otc_status: pending (default) | cleared | skipped
 *   - otc_handover_date / otc_cleared_by / otc_cleared_at / otc_remarks
 * A tranche is "settled" once otc_status is cleared or skipped. The loan only
 * completes when it is fully disbursed AND every active tranche is settled.
 *
 * String column (not enum) for MariaDB/SQLite portability, matching the
 * existing `method` varchar on this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disbursement_entries', function (Blueprint $table) {
            $table->string('otc_status', 20)->default('pending')->after('cheque_date');
            $table->date('otc_handover_date')->nullable()->after('otc_status');
            $table->foreignId('otc_cleared_by')->nullable()->after('otc_handover_date')->constrained('users')->nullOnDelete();
            $table->timestamp('otc_cleared_at')->nullable()->after('otc_cleared_by');
            $table->text('otc_remarks')->nullable()->after('otc_cleared_at');

            $table->index('otc_status');
        });
    }

    public function down(): void
    {
        Schema::table('disbursement_entries', function (Blueprint $table) {
            $table->dropIndex(['otc_status']);
            $table->dropConstrainedForeignId('otc_cleared_by');
            $table->dropColumn(['otc_status', 'otc_handover_date', 'otc_cleared_at', 'otc_remarks']);
        });
    }
};
