<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add missing columns to quotations table
        Schema::table('quotations', function (Blueprint $table) {
            if (!Schema::hasColumn('quotations', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->index('user_id');
            }
            if (!Schema::hasColumn('quotations', 'prepared_by_name')) {
                $table->string('prepared_by_name')->nullable()->after('additional_notes');
            }
            if (!Schema::hasColumn('quotations', 'prepared_by_mobile')) {
                $table->string('prepared_by_mobile')->nullable()->after('prepared_by_name');
            }
            if (!Schema::hasColumn('quotations', 'selected_tenures')) {
                $table->json('selected_tenures')->nullable()->after('prepared_by_mobile');
            }
            if (!Schema::hasColumn('quotations', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        // Ensure created_at index exists
        try {
            Schema::table('quotations', function (Blueprint $table) {
                $table->index('created_at');
            });
        } catch (\Exception $e) {
            // Index may already exist
        }

        // Add timestamps to quotation_banks
        Schema::table('quotation_banks', function (Blueprint $table) {
            if (!Schema::hasColumn('quotation_banks', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('quotation_banks', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        // Add timestamps to quotation_emi
        Schema::table('quotation_emi', function (Blueprint $table) {
            if (!Schema::hasColumn('quotation_emi', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('quotation_emi', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        // Add timestamps to quotation_documents
        Schema::table('quotation_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('quotation_documents', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('quotation_documents', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'prepared_by_name', 'prepared_by_mobile', 'selected_tenures', 'updated_at']);
        });

        Schema::table('quotation_banks', function (Blueprint $table) {
            $table->dropColumn(['created_at', 'updated_at']);
        });

        Schema::table('quotation_emi', function (Blueprint $table) {
            $table->dropColumn(['created_at', 'updated_at']);
        });

        Schema::table('quotation_documents', function (Blueprint $table) {
            $table->dropColumn(['created_at', 'updated_at']);
        });
    }
};
