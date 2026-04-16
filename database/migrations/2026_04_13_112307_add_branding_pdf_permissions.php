<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insert([
            [
                'name' => 'Download Branded PDF',
                'slug' => 'download_pdf_branded',
                'group' => 'Quotations',
                'description' => 'Download PDF with SHF branding',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Download Plain PDF',
                'slug' => 'download_pdf_plain',
                'group' => 'Quotations',
                'description' => 'Download PDF without SHF branding',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // Grant both new permissions to all roles that currently have download_pdf
        $downloadPdfId = DB::table('permissions')->where('slug', 'download_pdf')->value('id');
        $brandedId = DB::table('permissions')->where('slug', 'download_pdf_branded')->value('id');
        $plainId = DB::table('permissions')->where('slug', 'download_pdf_plain')->value('id');

        if ($downloadPdfId && $brandedId && $plainId) {
            $roleIds = DB::table('role_permission')
                ->where('permission_id', $downloadPdfId)
                ->pluck('role_id');

            $inserts = [];
            foreach ($roleIds as $roleId) {
                $inserts[] = ['role_id' => $roleId, 'permission_id' => $brandedId];
                $inserts[] = ['role_id' => $roleId, 'permission_id' => $plainId];
            }

            if ($inserts) {
                DB::table('role_permission')->insert($inserts);
            }
        }
    }

    public function down(): void
    {
        $slugs = ['download_pdf_branded', 'download_pdf_plain'];
        $ids = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');

        DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('user_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
