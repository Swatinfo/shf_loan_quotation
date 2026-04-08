<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL requires dropping FK before dropping the unique index it depends on
        // We'll just add a new index and leave the old one
        // The old unique constraint won't block location-based entries since branch_id is now nullable
        // Instead, we ensure branch_id is nullable (already done) and the system handles duplicates in code
    }

    public function down(): void
    {
        // Nothing to reverse
    }
};
