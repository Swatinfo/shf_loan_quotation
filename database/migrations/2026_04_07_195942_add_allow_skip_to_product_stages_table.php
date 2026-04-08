<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_stages', function (Blueprint $table) {
            $table->boolean('allow_skip')->default(true)->after('auto_skip');
        });
    }

    public function down(): void
    {
        Schema::table('product_stages', function (Blueprint $table) {
            $table->dropColumn('allow_skip');
        });
    }
};
