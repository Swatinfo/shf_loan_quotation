<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $removedStageKeys = [
        'cibil_check', 'vehicle_valuation', 'business_valuation',
        'title_search', 'financial_analysis', 'site_visit',
        'approval_committee', 'credit_committee', 'insurance', 'mortgage',
    ];

    public function up(): void
    {
        // Remove stage assignments for these stages
        DB::table('stage_assignments')->whereIn('stage_key', $this->removedStageKeys)->delete();

        // Remove product stage configs
        $stageIds = DB::table('stages')->whereIn('stage_key', $this->removedStageKeys)->pluck('id');
        DB::table('product_stages')->whereIn('stage_id', $stageIds)->delete();

        // Remove the stages themselves
        DB::table('stages')->whereIn('stage_key', $this->removedStageKeys)->delete();
    }

    public function down(): void
    {
        // Not reversible — stages would need to be re-seeded
    }
};
