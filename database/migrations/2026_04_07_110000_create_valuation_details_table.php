<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valuation_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loan_details')->cascadeOnDelete();
            $table->string('valuation_type')->default('property'); // property, vehicle, business
            $table->text('property_address')->nullable();
            $table->string('property_type')->nullable();
            $table->string('property_area')->nullable();
            $table->unsignedBigInteger('market_value')->nullable();
            $table->unsignedBigInteger('government_value')->nullable();
            $table->date('valuation_date')->nullable();
            $table->string('valuator_name')->nullable();
            $table->string('valuator_report_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('loan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valuation_details');
    }
};
