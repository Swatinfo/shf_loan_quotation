<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    // Legacy migration — originally added bank_employee to users.role enum.
    // The role enum column has been removed; roles are now managed via roles table + role_user pivot.
    public function up(): void
    {
        // No-op: legacy role enum column removed
    }

    public function down(): void
    {
        // No-op
    }
};
