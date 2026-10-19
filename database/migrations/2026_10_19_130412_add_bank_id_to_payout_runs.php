<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payout_runs', function (Blueprint $table) {
            // A payout run is scoped to one bank (nullable for legacy all-bank runs).
            $table->unsignedBigInteger('bank_id')->nullable()->after('to_date');
        });
    }

    public function down(): void
    {
        Schema::table('payout_runs', function (Blueprint $table) {
            $table->dropColumn('bank_id');
        });
    }
};
