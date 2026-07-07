<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeLoansCommand extends Command
{
    protected $signature = 'loans:purge {--force : Skip confirmation prompts} {--with-quotations : Also purge quotation data}';

    protected $description = 'Delete all loan data and related records. Interactive with options.';

    public function handle(): int
    {
        // Non-interactive mode (--force)
        if ($this->option('force')) {
            return $this->executePurge($this->option('with-quotations'));
        }

        $this->warn('');
        $this->warn('  ⚠️  LOAN DATA PURGE TOOL');
        $this->warn('  This will permanently delete loan data.');
        $this->warn('');

        // Count current data
        $loanCount = DB::table('loan_details')->count();
        $quotationCount = DB::table('quotations')->count();
        $convertedCount = DB::table('quotations')->whereNotNull('loan_id')->count();

        $this->info('  Current data:');
        $this->info("    Loans: {$loanCount}");
        $this->info("    Quotations: {$quotationCount} ({$convertedCount} converted to loans)");
        $this->info('');

        if ($loanCount === 0) {
            $this->info('No loan data to purge.');

            return 0;
        }

        // Ask what to purge
        $choice = $this->choice('What do you want to purge?', [
            'loans_only' => 'Loans only (keep quotations, clear loan_id references)',
            'loans_and_quotations' => 'Loans AND Quotations (complete clean slate)',
            'cancel' => 'Cancel — do nothing',
        ], 'cancel');

        if ($choice === 'cancel') {
            $this->info('Cancelled. No data was deleted.');

            return 0;
        }

        $includeQuotations = $choice === 'loans_and_quotations';

        // Show what will be deleted
        $this->warn('');
        $this->warn('  The following tables will be TRUNCATED:');
        $tables = $this->getLoanTables();
        foreach ($tables as $table) {
            $count = DB::table($table)->count();
            $this->line("    {$table}: {$count} rows");
        }
        if ($includeQuotations) {
            $quotationTables = ['quotation_documents', 'quotation_emi', 'quotation_banks', 'quotations'];
            foreach ($quotationTables as $table) {
                $count = DB::table($table)->count();
                $this->line("    {$table}: {$count} rows");
            }
        } else {
            $this->line("    quotations.loan_id: {$convertedCount} back-references will be cleared");
        }
        $this->warn('');

        // Final confirmation
        if (! $this->confirm('Are you sure? This action CANNOT be undone.', false)) {
            $this->info('Cancelled.');

            return 0;
        }

        // Double confirmation
        $confirmText = $this->ask('Type "PURGE" to confirm');
        if ($confirmText !== 'PURGE') {
            $this->info('Cancelled. You must type PURGE exactly.');

            return 0;
        }

        // Execute purge
        return $this->executePurge($includeQuotations);
    }

    private function executePurge(bool $includeQuotations = false): int
    {
        $this->info('Purging loan data...');

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        }

        try {
            // Truncate tables (hard delete + reset auto-increment)
            foreach ($this->getLoanTables() as $table) {
                $count = DB::table($table)->count();
                $this->truncateTable($table, $driver);
                $this->line("  ✓ {$table}: {$count} rows truncated");
            }

            if ($includeQuotations) {
                foreach (['quotation_documents', 'quotation_emi', 'quotation_banks', 'quotations'] as $table) {
                    $count = DB::table($table)->count();
                    $this->truncateTable($table, $driver);
                    $this->line("  ✓ {$table}: {$count} rows truncated");
                }
            } else {
                // Clear loan_id back-references on quotations
                $cleared = DB::table('quotations')->whereNotNull('loan_id')->update(['loan_id' => null]);
                $this->line("  ✓ quotations.loan_id: {$cleared} references cleared");
            }

            // Clear loan-related activity logs (filtered delete, not truncate)
            $logCount = DB::table('activity_logs')
                ->where('subject_type', 'App\\Models\\LoanDetail')
                ->count();
            DB::table('activity_logs')
                ->where('subject_type', 'App\\Models\\LoanDetail')
                ->delete();
            $this->line("  ✓ activity_logs (loan-related): {$logCount} rows deleted");

            if ($includeQuotations) {
                $qLogCount = DB::table('activity_logs')
                    ->where('subject_type', 'App\\Models\\Quotation')
                    ->count();
                DB::table('activity_logs')
                    ->where('subject_type', 'App\\Models\\Quotation')
                    ->delete();
                $this->line("  ✓ activity_logs (quotation-related): {$qLogCount} rows deleted");
            }
        } catch (\Exception $e) {
            $this->error('Purge failed: '.$e->getMessage());

            return 1;
        } finally {
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            }
        }

        $this->info('✓ Loan data purged successfully.');

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

    /**
     * Loan-related tables in reverse dependency order (children first).
     */
    private function getLoanTables(): array
    {
        return [
            'query_responses',
            'stage_queries',
            'stage_transfers',
            'disbursement_details',
            'valuation_details',
            'remarks',
            'shf_notifications',
            'loan_documents',
            'loan_progress',
            'stage_assignments',
            'loan_details',
        ];
    }
}
