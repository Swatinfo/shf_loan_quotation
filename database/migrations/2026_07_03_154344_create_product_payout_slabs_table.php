<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payout slabs per product: loans whose (future) disbursed amount falls in
 * [low_amount, high_amount] pay `payout_value` — a fixed ₹ amount or a
 * percentage depending on `payout_type` — to the loan creator. Storage only
 * for now; the calculation feature comes later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_payout_slabs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedBigInteger('low_amount');
            $table->unsignedBigInteger('high_amount');
            $table->string('payout_type', 10)->default('amount'); // amount | percent
            $table->decimal('payout_value', 12, 2);
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_payout_slabs');
    }
};
