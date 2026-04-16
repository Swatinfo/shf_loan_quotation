<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('disbursement_details', function (Blueprint $table) {
            $table->json('cheques')->nullable()->after('cheque_date');
        });
    }

    public function down(): void
    {
        Schema::table('disbursement_details', function (Blueprint $table) {
            $table->dropColumn('cheques');
        });
    }
};
