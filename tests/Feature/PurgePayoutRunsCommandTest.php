<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\DisbursementDetail;
use App\Models\DisbursementEntry;
use App\Models\LoanDetail;
use App\Models\PayoutRun;
use App\Models\Product;
use App\Models\ProductPayoutSlab;
use App\Models\ProductPayoutVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\DatabaseBackupService;
use App\Services\PayoutRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * `payout:purge-runs` — backs up the database first (mocked here: tests run on
 * SQLite), then deletes every payout run and clears all paid markers so every
 * settled tranche is payable again. Refuses to delete when the backup fails.
 */
class PurgePayoutRunsCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A finalized run covering one loan (tranche + PF/admin + insurance stamped).
     *
     * @return array{0: LoanDetail, 1: PayoutRun}
     */
    private function finalizedRun(): array
    {
        $user = User::create(['name' => 'U', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $user->roles()->sync(Role::where('slug', 'super_admin')->pluck('id'));

        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Br', 'is_active' => true]);
        $product = Product::create(['name' => 'P-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true, 'is_pf_based' => false]);
        $version = ProductPayoutVersion::create([
            'product_id' => $product->id, 'effective_from' => '2000-01-01', 'is_pf_based' => false,
            'payout_cycle_start_day' => 1, 'payout_cycle_end_day' => 31,
        ]);
        ProductPayoutSlab::create([
            'product_id' => $product->id, 'version_id' => $version->id, 'low_amount' => 0, 'high_amount' => 100000000,
            'payout_type' => 'percent', 'payout_value' => 1.0, 'connector_payout_type' => 'percent', 'connector_payout_value' => 1.0,
        ]);

        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(), 'customer_name' => 'C', 'customer_type' => 'salaried',
            'loan_amount' => 1000000, 'status' => 'partial_completed', 'current_stage' => 'otc_clearance',
            'bank_id' => $bank->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'created_by' => $user->id, 'payout_user_id' => $user->id,
        ]);
        $detail = DisbursementDetail::create([
            'loan_id' => $loan->id, 'disbursement_type' => 'fund_transfer', 'amount_disbursed' => 0,
            'pf_amount' => 11800, 'admin_charges' => 2360, 'insurance_amount' => 5000,
        ]);
        DisbursementEntry::create([
            'loan_id' => $loan->id, 'disbursement_detail_id' => $detail->id, 'method' => 'fund_transfer',
            'amount' => 500000, 'disbursement_date' => '2026-10-10', 'transfer_date' => '2026-10-10',
            'otc_status' => 'skipped', 'otc_handover_date' => '2026-10-10', 'is_active' => true,
        ]);

        $run = app(PayoutRunService::class)->finalizeRun('2026-10-01', '2026-10-31', $user);

        return [$loan, $run];
    }

    private function mockBackup(?string $path = '/backups/payout-purge-backup-db-2026-10-28_15-42-07.sql.gz', bool $fail = false): void
    {
        $this->mock(DatabaseBackupService::class, function ($mock) use ($path, $fail) {
            $expectation = $mock->shouldReceive('backup')->with('payout-purge-backup', 'mysqldump');
            $fail
                ? $expectation->once()->andThrow(new RuntimeException('mysqldump: command not found'))
                : $expectation->once()->andReturn($path);
        });
    }

    public function test_dry_run_changes_nothing_and_skips_backup(): void
    {
        [$loan, $run] = $this->finalizedRun();
        $this->mock(DatabaseBackupService::class)->shouldNotReceive('backup');

        $this->artisan('payout:purge-runs', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertDatabaseHas('payout_runs', ['id' => $run->id]);
        $this->assertSame($run->id, $loan->disbursementEntries()->first()->payout_run_id);
    }

    public function test_purge_backs_up_then_deletes_runs_and_clears_every_paid_marker(): void
    {
        [$loan, $run] = $this->finalizedRun();
        $detail = $loan->disbursement->fresh();
        $this->assertSame($run->id, $detail->charges_payout_run_id);
        $this->assertSame($run->id, $detail->insurance_payout_run_id);
        $this->mockBackup();

        $this->artisan('payout:purge-runs')
            ->expectsQuestion('Type PURGE to back up the database and delete all payouts', 'PURGE')
            ->expectsOutputToContain('payout-purge-backup-db-2026-10-28_15-42-07.sql.gz')
            ->assertSuccessful();

        $this->assertDatabaseCount('payout_runs', 0);
        $this->assertDatabaseCount('payout_run_products', 0);
        $this->assertDatabaseCount('payout_run_lines', 0);
        $this->assertDatabaseCount('payout_run_users', 0);
        $entry = $loan->disbursementEntries()->first();
        $this->assertNull($entry->payout_run_id);
        $this->assertNull($entry->paid_amount_counted);
        $detail->refresh();
        $this->assertNull($detail->pf_payout_run_id);
        $this->assertNull($detail->insurance_payout_run_id);
        $this->assertNull($detail->charges_payout_run_id);
        $this->assertDatabaseHas('activity_log', ['description' => 'purge_payout_runs']);

        // The tranche (with its PF + admin) is payable again.
        $preview = app(PayoutRunService::class)->previewRun('2026-10-01', '2026-10-31');
        $this->assertTrue($preview['can_finalize']);
        $this->assertSame(514160, $preview['products'][0]['aggregate_base']);
    }

    public function test_failed_backup_deletes_nothing(): void
    {
        [$loan, $run] = $this->finalizedRun();
        $this->mockBackup(fail: true);

        $this->artisan('payout:purge-runs', ['--force' => true])
            ->expectsOutputToContain('Backup failed — nothing was deleted')
            ->assertFailed();

        $this->assertDatabaseHas('payout_runs', ['id' => $run->id]);
        $this->assertSame($run->id, $loan->disbursementEntries()->first()->payout_run_id);
    }

    public function test_wrong_confirmation_cancels_without_backup(): void
    {
        [, $run] = $this->finalizedRun();
        $this->mock(DatabaseBackupService::class)->shouldNotReceive('backup');

        $this->artisan('payout:purge-runs')
            ->expectsQuestion('Type PURGE to back up the database and delete all payouts', 'yes')
            ->expectsOutputToContain('Cancelled')
            ->assertSuccessful();

        $this->assertDatabaseHas('payout_runs', ['id' => $run->id]);
    }

    public function test_backup_service_refuses_non_mysql_connections(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MySQL/MariaDB');

        app(DatabaseBackupService::class)->backup('payout-purge-backup'); // tests run on SQLite
    }
}
