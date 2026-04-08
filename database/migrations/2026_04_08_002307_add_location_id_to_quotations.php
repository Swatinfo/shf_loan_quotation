<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('loan_id')->constrained('locations')->nullOnDelete();
        });

        // Also add location_id to loan_details if not already there
        if (! Schema::hasColumn('loan_details', 'location_id')) {
            Schema::table('loan_details', function (Blueprint $table) {
                $table->foreignId('location_id')->nullable()->after('branch_id')->constrained('locations')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });

        if (Schema::hasColumn('loan_details', 'location_id')) {
            Schema::table('loan_details', function (Blueprint $table) {
                $table->dropForeign(['location_id']);
                $table->dropColumn('location_id');
            });
        }
    }
};
