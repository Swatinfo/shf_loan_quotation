<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('valuation_details', function (Blueprint $table) {
            // Rename property_area to land_area
            $table->renameColumn('property_area', 'land_area');
        });

        Schema::table('valuation_details', function (Blueprint $table) {
            // Add new columns
            $table->decimal('land_rate', 12, 2)->nullable()->after('land_area');
            $table->unsignedBigInteger('land_valuation')->nullable()->after('land_rate');
            $table->string('construction_area')->nullable()->after('land_valuation');
            $table->decimal('construction_rate', 12, 2)->nullable()->after('construction_area');
            $table->unsignedBigInteger('construction_valuation')->nullable()->after('construction_rate');
            $table->unsignedBigInteger('final_valuation')->nullable()->after('construction_valuation');
            $table->string('latitude', 50)->nullable()->after('property_address');
            $table->string('longitude', 50)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('valuation_details', function (Blueprint $table) {
            $table->dropColumn([
                'land_rate', 'land_valuation',
                'construction_area', 'construction_rate', 'construction_valuation',
                'final_valuation', 'latitude', 'longitude',
            ]);
        });

        Schema::table('valuation_details', function (Blueprint $table) {
            $table->renameColumn('land_area', 'property_area');
        });
    }
};
