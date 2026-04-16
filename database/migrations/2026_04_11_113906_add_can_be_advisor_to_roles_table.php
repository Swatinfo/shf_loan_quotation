<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('can_be_advisor')->default(false)->after('description');
            $table->boolean('is_system')->default(false)->after('can_be_advisor');
        });

        // Mark existing advisor-eligible roles
        DB::table('roles')
            ->whereIn('slug', ['branch_manager', 'bdh', 'loan_advisor'])
            ->update(['can_be_advisor' => true]);

        // Mark system roles (cannot be deleted/edited via UI)
        DB::table('roles')
            ->whereIn('slug', ['super_admin', 'admin'])
            ->update(['is_system' => true]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['can_be_advisor', 'is_system']);
        });
    }
};
