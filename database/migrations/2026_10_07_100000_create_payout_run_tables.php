<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregate payout engine. A payout RUN finalizes payouts for a date range:
 * amount-based products pay on the product-wide disbursed volume tier, PF-based
 * products pay PF (ex-GST) × the PF slab rate, insurance pays per loan, TDS on
 * the per-user total. Each run snapshots the rates/slabs used so historical
 * reports stay stable when config later changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_runs', function (Blueprint $table) {
            $table->id();
            $table->date('from_date');
            $table->date('to_date');
            $table->string('status', 20)->default('finalized'); // draft | finalized
            $table->text('notes')->nullable();
            // Snapshot of the global rates used (decimals, e.g. 0.0200, 0.0500, 0.1800).
            $table->decimal('insurance_rate', 8, 4)->default(0);
            $table->decimal('tds_rate', 8, 4)->default(0);
            $table->decimal('gst_rate', 8, 4)->default(0);
            // Paid totals for the run (whole rupees).
            $table->unsignedBigInteger('total_gross')->default(0);
            $table->unsignedBigInteger('total_tds')->default(0);
            $table->unsignedBigInteger('total_net')->default(0);
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // One row per product that had activity in the run — the tier context (snapshot).
        Schema::create('payout_run_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('payout_runs')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('product_name');
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->boolean('is_pf_based')->default(false);
            // Volume (amount-based) or Σ PF ex-GST (pf-based) that picked the tier.
            $table->unsignedBigInteger('aggregate_base')->default(0);
            $table->unsignedBigInteger('product_payout_version_id')->nullable();
            $table->unsignedBigInteger('slab_id')->nullable();
            $table->unsignedBigInteger('slab_low')->nullable();
            $table->unsignedBigInteger('slab_high')->nullable();
            $table->string('tier_rate_type', 10)->default('percent'); // percent | amount
            $table->decimal('tier_rate', 12, 4)->default(0);
            $table->decimal('connector_tier_rate', 12, 4)->nullable();
            $table->bigInteger('max_payout')->nullable(); // -1 = uncapped
            $table->timestamps();
        });

        // One row per (user × product) — the breakdown.
        Schema::create('payout_run_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('payout_runs')->cascadeOnDelete();
            $table->foreignId('payout_run_product_id')->constrained('payout_run_products')->cascadeOnDelete();
            $table->foreignId('payout_user_id')->constrained('users');
            $table->string('role_context', 20)->default('standard'); // standard | connector
            $table->unsignedBigInteger('base_amount')->default(0); // user disbursed (amount) OR user PF base
            $table->decimal('rate_applied', 12, 4)->default(0);
            $table->unsignedBigInteger('commission')->default(0);  // amount-based, capped
            $table->unsignedBigInteger('pf_base')->default(0);
            $table->unsignedBigInteger('pf_payout')->default(0);   // pf-based, capped
            $table->unsignedBigInteger('insurance_base')->default(0);
            $table->decimal('insurance_rate', 8, 4)->default(0);
            $table->unsignedBigInteger('insurance_payout')->default(0);
            $table->unsignedBigInteger('line_total')->default(0);
            $table->timestamps();
        });

        // One row per user — the payable (per-user roll-up with TDS).
        Schema::create('payout_run_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('payout_runs')->cascadeOnDelete();
            $table->foreignId('payout_user_id')->constrained('users');
            $table->string('role_context', 20)->default('standard');
            $table->unsignedBigInteger('total_commission')->default(0);
            $table->unsignedBigInteger('total_pf_payout')->default(0);
            $table->unsignedBigInteger('total_insurance_payout')->default(0);
            $table->unsignedBigInteger('total_payout')->default(0);
            $table->decimal('tds_rate', 8, 4)->default(0);
            $table->unsignedBigInteger('tds_amount')->default(0);
            $table->unsignedBigInteger('net_payout')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_run_users');
        Schema::dropIfExists('payout_run_lines');
        Schema::dropIfExists('payout_run_products');
        Schema::dropIfExists('payout_runs');
    }
};
