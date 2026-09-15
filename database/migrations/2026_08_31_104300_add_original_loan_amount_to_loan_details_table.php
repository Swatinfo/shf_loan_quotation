<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_details', function (Blueprint $table) {
            $table->unsignedBigInteger('original_loan_amount')->nullable()->after('loan_amount');
        });

        // Preserve every existing loan's current amount as its original.
        DB::table('loan_details')->update(['original_loan_amount' => DB::raw('loan_amount')]);
    }

    public function down(): void
    {
        Schema::table('loan_details', function (Blueprint $table) {
            $table->dropColumn('original_loan_amount');
        });
    }
};
