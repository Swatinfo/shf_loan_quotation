<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loan_details')->cascadeOnDelete();
            $table->string('stage_key')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('remark');
            $table->timestamps();

            $table->index('loan_id');
            $table->index('stage_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remarks');
    }
};
