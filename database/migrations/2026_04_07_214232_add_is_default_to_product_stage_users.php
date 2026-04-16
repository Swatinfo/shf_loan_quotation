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
        Schema::table('product_stage_users', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('user_id');
        });

        // Mark existing single assignments as default
        \DB::table('product_stage_users')->update(['is_default' => true]);
    }

    public function down(): void
    {
        Schema::table('product_stage_users', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
