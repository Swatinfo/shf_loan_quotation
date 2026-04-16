<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_location', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['bank_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_location');
    }
};
