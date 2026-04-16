<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained('loan_details')->cascadeOnDelete();
            $table->string('stage_key');
            $table->foreignId('transferred_from')->constrained('users')->cascadeOnDelete();
            $table->foreignId('transferred_to')->constrained('users')->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->string('transfer_type')->default('manual');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_transfers');
    }
};
