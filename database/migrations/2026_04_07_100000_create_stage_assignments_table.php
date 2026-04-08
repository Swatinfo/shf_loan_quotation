<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loan_details')->cascadeOnDelete();
            $table->string('stage_key');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->string('priority')->default('normal');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_parallel_stage')->default(false);
            $table->string('parent_stage_key')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'stage_key']);
            $table->index('stage_key');
            $table->index('assigned_to');
            $table->index('status');
            $table->index('parent_stage_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_assignments');
    }
};
