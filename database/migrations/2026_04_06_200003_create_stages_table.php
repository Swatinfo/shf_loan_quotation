<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->string('stage_key')->unique();
            $table->string('stage_name_en');
            $table->string('stage_name_gu')->nullable();
            $table->integer('sequence_order');
            $table->boolean('is_parallel')->default(false);
            $table->string('parent_stage_key')->nullable()->index();
            $table->string('stage_type')->default('sequential'); // sequential, parallel, decision
            $table->text('description_en')->nullable();
            $table->text('description_gu')->nullable();
            $table->timestamps();

            $table->index('sequence_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stages');
    }
};
