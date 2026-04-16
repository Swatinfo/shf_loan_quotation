<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeQuotationsCommand extends Command
{
    protected $signature = 'quotations:purge {--force : Skip confirmation prompts}';

    protected $description = 'Delete all quotation data and related records (documents, banks, EMI entries).';

    public function handle(): int
    {
        $quotationCount = DB::table('quotations')->count();
        $convertedCount = DB::table('quotations')->whereNotNull('loan_id')->count();

        if (! $this->option('force')) {
            $this->warn('');
            $this->warn('  ⚠️  QUOTATION DATA PURGE TOOL');
            $this->warn('  This will permanently delete all quotation data.');
            $this->warn('');

            $this->info("  Quotations: {$quotationCount} ({$convertedCount} converted to loans)");

            if ($quotationCount === 0) {
                $this->info('No quotation data to purge.');

                return 0;
            }

            if ($convertedCount > 0) {
                $this->warn("  {$convertedCount} quotation(s) are linked to loans — loan_details.quotation_id will be cleared.");
            }

            $this->warn('');
            $this->warn('  Tables to be purged:');
            foreach ($this->getQuotationTables() as $table) {
                $count = DB::table($table)->count();
                $this->line("    {$table}: {$count} rows");
            }
            $this->warn('');

            if (! $this->confirm('Are you sure? This action CANNOT be undone.', false)) {
                $this->info('Cancelled.');

                return 0;
            }

            $confirmText = $this->ask('Type "PURGE" to confirm');
            if ($confirmText !== 'PURGE') {
                $this->info('Cancelled. You must type PURGE exactly.');

                return 0;
            }
        } else {
            if ($quotationCount === 0) {
                $this->info('No quotation data to purge.');

                return 0;
            }
        }

        return $this->executePurge();
    }

    private function executePurge(): int
    {
        $this->info('Purging quotation data...');

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        }

        try {
            // Clear loan_details.quotation_id back-references
            $loanRefs = DB::table('loan_details')->whereNotNull('quotation_id')->count();
            if ($loanRefs > 0) {
                DB::table('loan_details')->whereNotNull('quotation_id')->update(['quotation_id' => null]);
                $this->line("  ✓ loan_details.quotation_id: {$loanRefs} references cleared");
            }

            // Truncate tables (hard delete + reset auto-increment)
            foreach ($this->getQuotationTables() as $table) {
                $count = DB::table($table)->count();
                $this->truncateTable($table, $driver);
                $this->line("  ✓ {$table}: {$count} rows truncated");
            }

            // Clear quotation-related activity logs (filtered delete, not truncate)
            $logCount = DB::table('activity_logs')
                ->where('subject_type', 'App\\Models\\Quotation')
                ->count();
            if ($logCount > 0) {
                DB::table('activity_logs')
                    ->where('subject_type', 'App\\Models\\Quotation')
                    ->delete();
                $this->line("  ✓ activity_logs (quotation-related): {$logCount} rows deleted");
            }

            // Delete stored PDF files
            $pdfDir = storage_path('app/pdfs');
            if (is_dir($pdfDir)) {
                $files = glob($pdfDir . '/*.pdf');
                $fileCount = count($files);
                foreach ($files as $file) {
                    @unlink($file);
                }
                if ($fileCount > 0) {
                    $this->line("  ✓ PDF files: {$fileCount} files deleted");
                }
            }

        } catch (\Exception $e) {
            $this->error('Purge failed: ' . $e->getMessage());

            return 1;
        } finally {
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            }
        }

        $this->info('✓ Quotation data purged successfully.');

        return 0;
    }

    private function truncateTable(string $table, string $driver): void
    {
        if ($driver === 'sqlite') {
            DB::table($table)->delete();
            DB::statement("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
        } else {
            DB::statement("TRUNCATE TABLE `{$table}`");
        }
    }

    private function getQuotationTables(): array
    {
        return [
            'quotation_documents',
            'quotation_emi',
            'quotation_banks',
            'quotations',
        ];
    }
}
