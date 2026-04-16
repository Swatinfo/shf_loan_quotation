<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->foreignId('default_employee_id')->nullable()->after('is_active')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_employee_id');
        });
    }
};
