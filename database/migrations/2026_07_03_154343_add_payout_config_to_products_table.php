<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payout configuration for the future "payout to loan creator" feature
 * (storage only — no calculation yet). `is_pf_based` marks how the payout
 * slabs will be interpreted later; `max_payout_amount` is an optional cap.
 * The slabs themselves live in `product_payout_slabs`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_pf_based')->default(false)->after('code');
            $table->unsignedBigInteger('max_payout_amount')->nullable()->after('is_pf_based');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_pf_based', 'max_payout_amount']);
        });
    }
};
