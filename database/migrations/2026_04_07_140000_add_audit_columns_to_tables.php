<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that get updated_by column.
     */
    private array $updatedByTables = [
        'loan_details', 'quotations', 'banks', 'branches', 'products',
        'stage_assignments', 'loan_documents', 'valuation_details',
        'disbursement_details', 'product_stages',
    ];

    /**
     * Tables that get deleted_by column (only soft-delete tables).
     */
    private array $deletedByTables = [
        'loan_details', 'quotations', 'banks', 'branches', 'products',
    ];

    public function up(): void
    {
        foreach ($this->updatedByTables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('updated_by')->nullable()->after('updated_at')
                    ->constrained('users')->nullOnDelete();
            });
        }

        foreach ($this->deletedByTables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('deleted_by')->nullable()->after('deleted_at')
                    ->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->deletedByTables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('deleted_by');
            });
        }

        foreach ($this->updatedByTables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('updated_by');
            });
        }
    }
};
