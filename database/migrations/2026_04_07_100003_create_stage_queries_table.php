<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained('loan_details')->cascadeOnDelete();
            $table->string('stage_key');
            $table->text('query_text');
            $table->foreignId('raised_by')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['stage_assignment_id', 'status']);
            $table->index('loan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_queries');
    }
};
