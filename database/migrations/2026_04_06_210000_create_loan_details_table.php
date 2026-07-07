<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_details', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number')->unique();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_type');
            $table->string('customer_phone', 20)->nullable();
            $table->string('customer_email')->nullable();
            $table->unsignedBigInteger('loan_amount');
            $table->string('status')->default('active');
            $table->string('current_stage')->default('inquiry');
            $table->string('bank_name')->nullable();
            $table->decimal('roi_min', 5, 2)->nullable();
            $table->decimal('roi_max', 5, 2)->nullable();
            $table->string('total_charges')->nullable();
            $table->string('application_number')->nullable();
            $table->foreignId('assigned_bank_employee')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejected_stage')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_advisor')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('current_stage');
            $table->index('customer_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_details');
    }
};
