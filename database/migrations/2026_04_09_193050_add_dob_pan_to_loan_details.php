<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_details', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('customer_email');
            $table->string('pan_number', 10)->nullable()->after('date_of_birth');
        });
    }

    public function down(): void
    {
        Schema::table('loan_details', function (Blueprint $table) {
            $table->dropColumn(['date_of_birth', 'pan_number']);
        });
    }
};
