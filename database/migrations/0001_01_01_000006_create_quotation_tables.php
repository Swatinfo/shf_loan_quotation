<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('quotations')) {
            Schema::create('quotations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('customer_name');
                $table->string('customer_type'); // proprietor, partnership_llp, pvt_ltd, all
                $table->unsignedBigInteger('loan_amount');
                $table->string('pdf_filename')->nullable();
                $table->string('pdf_path')->nullable();
                $table->text('additional_notes')->nullable();
                $table->string('prepared_by_name')->nullable();
                $table->string('prepared_by_mobile')->nullable();
                $table->json('selected_tenures')->nullable();
                $table->timestamps();

                $table->index('user_id');
                $table->index('created_at');
            });
        }

        if (!Schema::hasTable('quotation_banks')) {
            Schema::create('quotation_banks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
                $table->string('bank_name');
                $table->decimal('roi_min', 5, 2)->default(0);
                $table->decimal('roi_max', 5, 2)->default(0);
                $table->decimal('pf_charge', 5, 2)->default(0);
                $table->unsignedBigInteger('admin_charge')->default(0);
                $table->unsignedBigInteger('stamp_duty')->default(0);
                $table->unsignedBigInteger('notary_charge')->default(0);
                $table->unsignedBigInteger('advocate_fees')->default(0);
                $table->unsignedBigInteger('iom_charge')->default(0);
                $table->unsignedBigInteger('tc_report')->default(0);
                $table->string('extra1_name')->nullable();
                $table->unsignedBigInteger('extra1_amount')->default(0);
                $table->string('extra2_name')->nullable();
                $table->unsignedBigInteger('extra2_amount')->default(0);
                $table->unsignedBigInteger('total_charges')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('quotation_emi')) {
            Schema::create('quotation_emi', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quotation_bank_id')->constrained('quotation_banks')->cascadeOnDelete();
                $table->integer('tenure_years');
                $table->unsignedBigInteger('monthly_emi')->default(0);
                $table->unsignedBigInteger('total_interest')->default(0);
                $table->unsignedBigInteger('total_payment')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('quotation_documents')) {
            Schema::create('quotation_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
                $table->string('document_name_en');
                $table->string('document_name_gu')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_documents');
        Schema::dropIfExists('quotation_emi');
        Schema::dropIfExists('quotation_banks');
        Schema::dropIfExists('quotations');
    }
};
