<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_documents', function (Blueprint $table) {
            $table->boolean('is_excluded')->default(false)->after('document_name_gu');
            $table->unsignedInteger('sequence')->default(0)->after('is_excluded');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_documents', function (Blueprint $table) {
            $table->dropColumn(['is_excluded', 'sequence']);
        });
    }
};
