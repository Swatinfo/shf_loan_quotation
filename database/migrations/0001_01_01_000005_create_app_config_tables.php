<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // app_config may exist from legacy app — drop and recreate
        if (Schema::hasTable('app_config')) {
            // Preserve existing config data
            $existingConfig = null;
            try {
                $existingConfig = \DB::table('app_config')->where('config_key', 'main')->value('config_json');
            } catch (\Exception $e) {}

            Schema::drop('app_config');
            Schema::create('app_config', function (Blueprint $table) {
                $table->id();
                $table->string('config_key')->unique();
                $table->longText('config_json')->nullable();
                $table->timestamps();
            });

            // Restore existing config
            if ($existingConfig) {
                \DB::table('app_config')->insert([
                    'config_key' => 'main',
                    'config_json' => $existingConfig,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } else {
            Schema::create('app_config', function (Blueprint $table) {
                $table->id();
                $table->string('config_key')->unique();
                $table->longText('config_json')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table) {
                $table->string('setting_key')->primary();
                $table->text('setting_value')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('bank_charges')) {
            Schema::create('bank_charges', function (Blueprint $table) {
                $table->id();
                $table->string('bank_name');
                $table->decimal('pf', 5, 2)->default(0);
                $table->unsignedBigInteger('admin')->default(0);
                $table->unsignedBigInteger('stamp')->default(0);
                $table->unsignedBigInteger('notary')->default(0);
                $table->unsignedBigInteger('advocate')->default(0);
                $table->unsignedBigInteger('tc')->default(0);
                $table->string('extra1_name')->nullable();
                $table->unsignedBigInteger('extra1_amt')->default(0);
                $table->string('extra2_name')->nullable();
                $table->unsignedBigInteger('extra2_amt')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_charges');
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('app_config');
    }
};
