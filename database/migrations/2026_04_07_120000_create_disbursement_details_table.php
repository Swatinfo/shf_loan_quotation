<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursement_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->unique()->constrained('loan_details')->cascadeOnDelete();
            $table->string('disbursement_type'); // fund_transfer, cheque, demand_draft
            $table->date('disbursement_date')->nullable();
            $table->unsignedBigInteger('amount_disbursed')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('cheque_number')->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('dd_number')->nullable();
            $table->date('dd_date')->nullable();
            $table->boolean('is_otc')->default(false);
            $table->string('otc_branch')->nullable();
            $table->boolean('otc_cleared')->default(false);
            $table->date('otc_cleared_date')->nullable();
            $table->foreignId('otc_cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursement_details');
    }
};
