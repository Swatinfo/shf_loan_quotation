<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `max_payout_amount` was unsignedBigInteger; the payout cap must support
 * paise, so it becomes decimal(14,2). Plain (signed) decimal on purpose —
 * MySQL 8 deprecates UNSIGNED DECIMAL; non-negativity is enforced by the
 * `min:0` validation rule in WorkflowConfigController::storeProduct().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('max_payout_amount', 14, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('max_payout_amount')->nullable()->change();
        });
    }
};
