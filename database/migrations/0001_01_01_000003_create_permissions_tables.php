<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('group');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Legacy role_permissions table removed — replaced by role_permission pivot
        // (kept as no-op so migration history stays consistent)

        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['grant', 'deny']);
            $table->timestamps();
            $table->unique(['user_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
    }
};
