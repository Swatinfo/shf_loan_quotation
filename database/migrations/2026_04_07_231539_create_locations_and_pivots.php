<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Locations table (states and cities)
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('name');
            $table->enum('type', ['state', 'city'])->default('city');
            $table->string('code', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['name', 'parent_id']);
        });

        // User ↔ Location pivot
        Schema::create('location_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['location_id', 'user_id']);
        });

        // Product ↔ Location pivot
        Schema::create('location_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['location_id', 'product_id']);
        });

        // Add location_id to branches
        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('manager_id')->constrained('locations')->nullOnDelete();
        });

        // Add location_id to product_stage_users
        Schema::table('product_stage_users', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('branch_id')->constrained('locations')->nullOnDelete();
        });

        // Make branch_id nullable in product_stage_users (was required, now either branch_id or location_id)
        Schema::table('product_stage_users', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_stage_users', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });

        Schema::dropIfExists('location_product');
        Schema::dropIfExists('location_user');
        Schema::dropIfExists('locations');
    }
};
