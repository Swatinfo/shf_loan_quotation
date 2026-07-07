<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('query_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_query_id')->constrained('stage_queries')->cascadeOnDelete();
            $table->text('response_text');
            $table->foreignId('responded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_responses');
    }
};
