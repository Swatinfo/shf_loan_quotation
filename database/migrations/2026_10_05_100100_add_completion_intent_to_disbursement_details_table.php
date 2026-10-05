<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `completion_intent` latches whether the operator has declared the
 * disbursement finished:
 *   - open (default): more tranches may still be added; the loan is
 *     considered money-done only once the cumulative total reaches the target.
 *   - full: operator clicked "Mark Full Disbursement" (intentional close,
 *     possibly below target) — the loan is money-done regardless of total.
 * Either way the loan completes only once every active tranche is OTC-settled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disbursement_details', function (Blueprint $table) {
            $table->string('completion_intent', 10)->default('open')->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('disbursement_details', function (Blueprint $table) {
            $table->dropColumn('completion_intent');
        });
    }
};
