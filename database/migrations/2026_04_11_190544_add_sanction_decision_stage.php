<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add is_sanctioned to loan_details
        Schema::table('loan_details', function (Blueprint $table) {
            $table->boolean('is_sanctioned')->default(false)->after('status');
        });

        // sanction_decision stage is now seeded by DefaultDataSeeder
    }

    public function down(): void
    {
        Schema::table('loan_details', function (Blueprint $table) {
            $table->dropColumn('is_sanctioned');
        });
    }
};
