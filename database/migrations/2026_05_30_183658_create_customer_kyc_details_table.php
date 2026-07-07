<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-loan KYC snapshot. The `customers` row is the identity anchor (keyed
     * by PAN, never updated); each loan carries its own KYC details here and is
     * displayed from this row, so differing details across deals are preserved.
     */
    public function up(): void
    {
        Schema::create('customer_kyc_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained('loan_details')->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->string('customer_name');
            $table->string('mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('pan_number', 10)->nullable();
            $table->string('source', 20)->default('conversion'); // conversion | direct | edit | cleanup
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('pan_number');
        });

        Schema::table('loan_details', function (Blueprint $table) {
            $table->foreignId('customer_kyc_details_id')->nullable()->after('customer_id')
                ->constrained('customer_kyc_details')->nullOnDelete();
        });

        // Backfill per-loan KYC snapshots (and dedupe master customers by PAN)
        // for any pre-existing loans, so this table is populated as soon as it
        // exists. No-op on a fresh install where no loans exist yet. Runs before
        // the unique-PAN-index migration so the dedupe happens first. The same
        // logic is also runnable on demand via `php artisan customers:backfill-kyc`.
        if (DB::table('loan_details')->exists()) {
            Artisan::call('customers:backfill-kyc');
        }
    }

    public function down(): void
    {
        Schema::table('loan_details', function (Blueprint $table) {
            $table->dropColumn('customer_kyc_details_id');
        });
        Schema::dropIfExists('customer_kyc_details');
    }
};
