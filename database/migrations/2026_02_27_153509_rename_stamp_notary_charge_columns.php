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
        Schema::table('bank_charges', function (Blueprint $table) {
            $table->renameColumn('stamp', 'stamp_notary');
            $table->renameColumn('notary', 'registration_fee');
        });

        Schema::table('quotation_banks', function (Blueprint $table) {
            $table->renameColumn('stamp_duty', 'stamp_notary');
            $table->renameColumn('notary_charge', 'registration_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_charges', function (Blueprint $table) {
            $table->renameColumn('stamp_notary', 'stamp');
            $table->renameColumn('registration_fee', 'notary');
        });

        Schema::table('quotation_banks', function (Blueprint $table) {
            $table->renameColumn('stamp_notary', 'stamp_duty');
            $table->renameColumn('registration_fee', 'notary_charge');
        });
    }
};
