<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['bank_id', 'user_id']);
        });

        // Migrate existing task_bank_id assignments to new pivot table (skip on fresh DB)
        if (Schema::hasColumn('users', 'task_role')) {
            $users = \App\Models\User::whereNotNull('task_bank_id')->where('task_role', 'bank_employee')->get();
            foreach ($users as $user) {
                \DB::table('bank_employees')->insert([
                    'bank_id' => $user->task_bank_id,
                    'user_id' => $user->id,
                    'is_default' => $user->id === (\App\Models\Bank::find($user->task_bank_id)?->default_employee_id),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_employees');
    }
};
