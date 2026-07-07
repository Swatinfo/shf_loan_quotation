<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->unique()->constrained('loan_details')->cascadeOnDelete();
            $table->integer('total_stages')->default(10);
            $table->integer('completed_stages')->default(0);
            $table->decimal('overall_percentage', 5, 2)->default(0);
            $table->date('estimated_completion')->nullable();
            $table->text('workflow_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_progress');
    }
};
