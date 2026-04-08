<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->string('default_assignee_role')->nullable();
            $table->boolean('auto_skip')->default(false);
            $table->integer('sort_order')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stages');
    }
};
