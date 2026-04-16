<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convert existing single role values to JSON arrays
        $stages = \App\Models\Stage::all();
        foreach ($stages as $stage) {
            if ($stage->default_role && !str_starts_with($stage->default_role, '[')) {
                $stage->default_role = json_encode([$stage->default_role]);
                $stage->saveQuietly();
            }
        }

        // Add default_user_id to product_stages for specific user assignment
        Schema::table('product_stages', function (Blueprint $table) {
            $table->foreignId('default_user_id')->nullable()->after('default_assignee_role')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_stages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_user_id');
        });
    }
};
