<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payout engine (per-entry, incremental):
 *  - product_payout_slabs gets a connector payout (type + value) alongside the
 *    internal payout — connectors earn the connector rate, everyone else the
 *    standard `payout_value`.
 *  - products get a payout cycle (start_day/end_day, 1-31) used to bucket
 *    finalized payouts into bank cycles (e.g. 16→16).
 *  - disbursement_entries get `loan_payout_id` + `payout_finalized_at`: the
 *    per-tranche paid flag, so a finalize only counts entries not yet paid.
 *  - loan_payouts is the user-wise ledger (one row per finalize event).
 *  - new `finalize_payout` permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loan_details')->cascadeOnDelete();
            $table->foreignId('payout_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role_context', 40)->nullable(); // payout user's role at finalize time
            $table->unsignedBigInteger('basis_amount'); // disbursed increment the payout was computed on
            $table->foreignId('slab_id')->nullable()->constrained('product_payout_slabs')->nullOnDelete();
            $table->string('payout_type', 10)->default('percent'); // amount | percent
            $table->decimal('payout_value', 12, 2)->default(0);
            $table->unsignedBigInteger('payout_amount'); // computed payout
            $table->date('cycle_start')->nullable();
            $table->date('cycle_end')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('manual'); // manual | reconciliation
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'finalized_at']);
            $table->index(['payout_user_id', 'cycle_start']);
        });

        Schema::table('product_payout_slabs', function (Blueprint $table) {
            $table->string('connector_payout_type', 10)->default('percent')->after('payout_value');
            $table->decimal('connector_payout_value', 12, 2)->default(0)->after('connector_payout_type');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedTinyInteger('payout_cycle_start_day')->default(1)->after('max_payout_amount');
            $table->unsignedTinyInteger('payout_cycle_end_day')->default(31)->after('payout_cycle_start_day');
        });

        Schema::table('disbursement_entries', function (Blueprint $table) {
            $table->foreignId('loan_payout_id')->nullable()->after('otc_remarks')->constrained('loan_payouts')->nullOnDelete();
            $table->timestamp('payout_finalized_at')->nullable()->after('loan_payout_id');
        });

        // finalize_payout permission → admin / branch_manager / bdh.
        $permId = DB::table('permissions')->where('slug', 'finalize_payout')->value('id');
        if (! $permId) {
            $permId = DB::table('permissions')->insertGetId([
                'name' => 'Finalize Payout',
                'slug' => 'finalize_payout',
                'group' => 'Loans',
                'description' => 'Finalize the disbursement payout for a loan (marks tranches paid)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        foreach (DB::table('roles')->whereIn('slug', ['admin', 'branch_manager', 'bdh'])->pluck('id') as $rid) {
            DB::table('role_permission')->insertOrIgnore(['role_id' => $rid, 'permission_id' => $permId]);
        }
    }

    public function down(): void
    {
        Schema::table('disbursement_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_payout_id');
            $table->dropColumn('payout_finalized_at');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['payout_cycle_start_day', 'payout_cycle_end_day']);
        });
        Schema::table('product_payout_slabs', function (Blueprint $table) {
            $table->dropColumn(['connector_payout_type', 'connector_payout_value']);
        });
        Schema::dropIfExists('loan_payouts');

        $permId = DB::table('permissions')->where('slug', 'finalize_payout')->value('id');
        if ($permId) {
            DB::table('role_permission')->where('permission_id', $permId)->delete();
            DB::table('user_permissions')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }
    }
};
