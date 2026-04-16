<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            $table->string('default_role')->nullable()->after('description_gu');
        });
    }

    public function down(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            $table->dropColumn('default_role');
        });
    }
};
