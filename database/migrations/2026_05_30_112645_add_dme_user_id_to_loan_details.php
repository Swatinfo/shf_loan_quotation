<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loan_details', function (Blueprint $table) {
            $table->foreignId('dme_user_id')->nullable()->after('assigned_advisor')->constrained('users')->nullOnDelete();
        });

        // Backfill existing loans whose Application Number sub-stage is already
        // complete: default the DME to the assigned advisor (fallback creator),
        // matching the default used for new loans, so old data stays consistent.
        DB::table('loan_details')
            ->whereNull('dme_user_id')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('stage_assignments')
                    ->whereColumn('stage_assignments.loan_id', 'loan_details.id')
                    ->where('stage_key', 'app_number')
                    ->where('status', 'completed');
            })
            ->update(['dme_user_id' => DB::raw('COALESCE(assigned_advisor, created_by)')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_details', function (Blueprint $table) {
            $table->dropColumn('dme_user_id');
        });
    }
};
