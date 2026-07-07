<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stage_queries', function (Blueprint $table) {
            $table->foreignId('assigned_to_user_id')
                ->nullable()
                ->after('raised_by')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['assigned_to_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('stage_queries', function (Blueprint $table) {
            $table->dropIndex(['assigned_to_user_id', 'status']);
            $table->dropConstrainedForeignId('assigned_to_user_id');
        });
    }
};
