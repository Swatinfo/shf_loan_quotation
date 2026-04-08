<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stage_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_stage_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stage_users');
    }
};
