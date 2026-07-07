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
        Schema::table('user_branches', function (Blueprint $table) {
            $table->boolean('is_default_office_employee')->default(false)->after('branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_branches', function (Blueprint $table) {
            $table->dropColumn('is_default_office_employee');
        });
    }
};
