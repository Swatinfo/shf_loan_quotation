<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One-off: delete ALL past payouts so they can be recalculated under the current
 * payout rules. Removes every payout run (+ its products / lines / user totals)
 * and the older per-loan `loan_payouts` records, and clears every "already paid"
 * marker on tranches and disbursements — so every settled tranche shows as unpaid
 * and appears in the next payout run. Payout settings (rates, slabs, product
 * versions) are not touched.
 *
 * Always takes a full database backup first (storage/app/backups) and refuses to
 * delete anything if that backup fails.
 */
class PurgePayoutRunsCommand extends Command
{
    protected $signature = 'payout:purge-runs
        {--dry-run : Show what would be deleted, change nothing}
        {--force : Skip the typed PURGE confirmation (the backup still runs)}
        {--mysqldump=mysqldump : Path to the mysqldump binary}';

    protected $description = 'Back up the database, then delete all past payout runs and clear every paid marker.';

    public function handle(DatabaseBackupService $backups): int
    {
        $counts = $this->counts();

        $this->warn('');
        $this->warn('  ⚠  PAYOUT PURGE — deletes all past payouts (cannot be undone)');
        $this->warn('');
        $this->table(['What', 'Rows'], collect($counts)->map(fn ($n, $k) => [$k, $n])->values()->all());

        if (array_sum($counts) === 0) {
            $this->info('Nothing to purge — no payout runs or paid markers exist.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run — nothing was changed.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $answer = $this->ask('Type PURGE to back up the database and delete all payouts');
            if ($answer !== 'PURGE') {
                $this->info('Cancelled — nothing was changed.');

                return self::SUCCESS;
            }
        }

        $this->info('Backing up the database…');
        try {
            $backupFile = $backups->backup('payout-purge-backup', (string) $this->option('mysqldump'));
        } catch (Throwable $e) {
            $this->error('Backup failed — nothing was deleted. '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info('Backup written: '.$backupFile);

        DB::transaction(function () use ($counts, $backupFile) {
            DB::table('disbursement_entries')
                ->where(fn ($q) => $q->whereNotNull('payout_run_id')->orWhereNotNull('paid_amount_counted')->orWhereNotNull('loan_payout_id'))
                ->update(['payout_run_id' => null, 'paid_amount_counted' => null, 'loan_payout_id' => null]);

            DB::table('disbursement_details')
                ->where(fn ($q) => $q->whereNotNull('pf_payout_run_id')->orWhereNotNull('insurance_payout_run_id')->orWhereNotNull('charges_payout_run_id'))
                ->update(['pf_payout_run_id' => null, 'insurance_payout_run_id' => null, 'charges_payout_run_id' => null]);

            DB::table('payout_run_lines')->delete();
            DB::table('payout_run_users')->delete();
            DB::table('payout_run_products')->delete();
            DB::table('payout_runs')->delete();
            DB::table('loan_payouts')->delete();

            ActivityLog::log('purge_payout_runs', null, $counts + ['backup_file' => basename($backupFile)]);
        });

        $this->info('');
        $this->info('Done. All past payouts deleted; every settled tranche is unpaid again.');
        $this->info('Backup: '.$backupFile);

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'payout_runs' => DB::table('payout_runs')->count(),
            'payout_run_products' => DB::table('payout_run_products')->count(),
            'payout_run_lines' => DB::table('payout_run_lines')->count(),
            'payout_run_users' => DB::table('payout_run_users')->count(),
            'loan_payouts (old engine)' => DB::table('loan_payouts')->count(),
            'tranches marked paid' => DB::table('disbursement_entries')
                ->where(fn ($q) => $q->whereNotNull('payout_run_id')->orWhereNotNull('paid_amount_counted')->orWhereNotNull('loan_payout_id'))
                ->count(),
            'disbursements with PF/insurance/charges marked paid' => DB::table('disbursement_details')
                ->where(fn ($q) => $q->whereNotNull('pf_payout_run_id')->orWhereNotNull('insurance_payout_run_id')->orWhereNotNull('charges_payout_run_id'))
                ->count(),
        ];
    }
}
