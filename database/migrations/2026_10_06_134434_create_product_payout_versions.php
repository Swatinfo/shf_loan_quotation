<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Effective-dated history for per-product payout config. Each version snapshots
 * the product-level payout fields (PF flag, cap, cycle days) and owns its slab
 * rows (product_payout_slabs.version_id). The config in force for a date D is
 * the latest version with effective_from <= D. `products.*` + the current slabs
 * stay as the denormalized "current" mirror.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_payout_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->date('effective_from');
            $table->boolean('is_pf_based')->default(false);
            $table->decimal('max_payout_amount', 14, 2)->nullable();
            $table->unsignedTinyInteger('payout_cycle_start_day')->default(1);
            $table->unsignedTinyInteger('payout_cycle_end_day')->default(31);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'effective_from']);
            $table->index(['product_id', 'effective_from']);
        });

        Schema::table('product_payout_slabs', function (Blueprint $table) {
            $table->foreignId('version_id')->nullable()->after('product_id')
                ->constrained('product_payout_versions')->cascadeOnDelete();
        });

        // Pointer to the version that is "current" today — the legacy
        // Product::payoutSlabs relation resolves through this (portable, no
        // correlated-subquery LIMIT that MySQL rejects).
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('current_payout_version_id')->nullable()->after('payout_cycle_end_day')
                ->constrained('product_payout_versions')->nullOnDelete();
        });

        // Backfill: one seed version per product carrying its current fields,
        // point the product's existing slabs at that version, and set the
        // current-version pointer. Seed all effective from 2026-04-01 (lookups
        // before this date fall back to the earliest version).
        DB::table('products')->orderBy('id')->each(function ($product) {
            $versionId = DB::table('product_payout_versions')->insertGetId([
                'product_id' => $product->id,
                'effective_from' => '2026-04-01',
                'is_pf_based' => $product->is_pf_based ?? false,
                'max_payout_amount' => $product->max_payout_amount,
                'payout_cycle_start_day' => $product->payout_cycle_start_day ?? 1,
                'payout_cycle_end_day' => $product->payout_cycle_end_day ?? 31,
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('product_payout_slabs')
                ->where('product_id', $product->id)
                ->whereNull('version_id')
                ->update(['version_id' => $versionId]);

            DB::table('products')->where('id', $product->id)->update(['current_payout_version_id' => $versionId]);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_payout_version_id');
        });
        Schema::table('product_payout_slabs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('version_id');
        });
        Schema::dropIfExists('product_payout_versions');
    }
};
