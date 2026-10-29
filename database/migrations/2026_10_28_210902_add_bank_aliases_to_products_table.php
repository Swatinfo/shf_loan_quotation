<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Other names the bank uses for a product on its statements (e.g. "HL",
 * "Housing Loan" for our "HOME LOAN"). Bank reconciliation checks the uploaded
 * `product` column against the product name, code and these aliases.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('bank_aliases')->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('bank_aliases');
        });
    }
};
