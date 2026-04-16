<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_documents', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('notes');
            $table->string('file_name')->nullable()->after('file_path');
            $table->unsignedBigInteger('file_size')->nullable()->after('file_name');
            $table->string('file_mime', 100)->nullable()->after('file_size');
            $table->foreignId('uploaded_by')->nullable()->after('file_mime')->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable()->after('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::table('loan_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by');
            $table->dropColumn(['file_path', 'file_name', 'file_size', 'file_mime', 'uploaded_at']);
        });
    }
};
