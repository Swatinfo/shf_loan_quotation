<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerKycDetail;
use App\Models\LoanDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time backfill for the customer-identity-by-PAN + KYC model.
 *
 *  - Builds a customer_kyc_details snapshot for every loan from its inline
 *    customer fields and links the loan to it.
 *  - Dedupes master customers by normalized PAN: confidently-same duplicates
 *    (sharing mobile OR date_of_birth) are merged into the earliest row and
 *    soft-deleted; loans are repointed to the surviving master.
 *  - Same-PAN rows that look like DIFFERENT people (no shared mobile/DOB) are
 *    NOT merged — they're reported as conflicts for manual resolution (the
 *    unique PAN index migration is intentionally blocked until they're fixed).
 *
 * Run with --dry-run first to review the plan without touching data.
 */
class BackfillCustomerKyc extends Command
{
    protected $signature = 'customers:backfill-kyc {--dry-run : Report what would change without writing}';

    protected $description = 'Backfill customer_kyc_details for loans and dedupe master customers by PAN';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $this->info($dry ? 'DRY RUN — no data will be changed.' : 'Applying backfill...');

        [$mergeMap, $mergedIds, $conflicts] = $this->planDedupe();

        $loansToProcess = LoanDetail::whereNull('customer_kyc_details_id')->count();

        if (! $dry) {
            DB::transaction(function () use ($mergeMap, $mergedIds) {
                $this->processLoans($mergeMap);
                if ($mergedIds) {
                    // Free the PAN on merged duplicates (preserved in their KYC
                    // snapshot) so the unique index holds on MySQL, where a plain
                    // unique still counts soft-deleted rows; then soft-delete them.
                    Customer::whereIn('id', $mergedIds)->update(['pan_number' => null]);
                    Customer::whereIn('id', $mergedIds)->delete();
                }
            });
        }

        // ----- Report -----
        $this->newLine();
        $this->line('Loans needing a KYC snapshot: '.$loansToProcess);
        $this->line('Confident duplicate masters merged: '.count($mergedIds));
        $this->line('PAN conflicts (different people, NOT merged): '.count($conflicts));

        if ($conflicts) {
            $this->newLine();
            $this->warn('Resolve these PAN conflicts manually before adding the unique index:');
            foreach ($conflicts as $pan => $ids) {
                $this->line("  PAN {$pan} → customer ids ".implode(', ', $ids));
            }
        }

        $this->newLine();
        $this->info($dry ? 'Dry run complete. Re-run without --dry-run to apply.' : 'Backfill complete.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: array<int,int>, 1: array<int,int>, 2: array<string,array<int,int>>}
     */
    private function planDedupe(): array
    {
        $mergeMap = [];   // duplicate customer id => surviving master id
        $mergedIds = [];  // duplicate customer ids to soft-delete
        $conflicts = [];  // normalized pan => [customer ids]

        $groups = Customer::whereNotNull('pan_number')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Customer $c) => strtoupper(trim((string) $c->pan_number)));

        foreach ($groups as $pan => $custs) {
            if ($custs->count() < 2) {
                continue;
            }
            $master = $custs->first();
            $conflictIds = [];

            foreach ($custs->slice(1) as $dup) {
                $sameMobile = $dup->mobile && $master->mobile && $dup->mobile === $master->mobile;
                $sameDob = $dup->date_of_birth && $master->date_of_birth
                    && $dup->date_of_birth->eq($master->date_of_birth);

                if ($sameMobile || $sameDob) {
                    $mergeMap[$dup->id] = $master->id;
                    $mergedIds[] = $dup->id;
                } else {
                    $conflictIds[] = $dup->id;
                }
            }

            if ($conflictIds) {
                $conflicts[$pan] = array_merge([$master->id], $conflictIds);
            }
        }

        return [$mergeMap, $mergedIds, $conflicts];
    }

    /**
     * @param  array<int,int>  $mergeMap
     */
    private function processLoans(array $mergeMap): void
    {
        LoanDetail::whereNull('customer_kyc_details_id')->chunkById(200, function ($loans) use ($mergeMap) {
            foreach ($loans as $loan) {
                $masterId = $loan->customer_id
                    ? ($mergeMap[$loan->customer_id] ?? $loan->customer_id)
                    : $this->resolveMasterIdForDirectLoan($loan);

                $kyc = CustomerKycDetail::create([
                    'customer_id' => $masterId,
                    'loan_id' => $loan->id,
                    'customer_name' => $loan->customer_name,
                    'mobile' => $loan->customer_phone,
                    'email' => $loan->customer_email,
                    'date_of_birth' => $loan->date_of_birth,
                    'pan_number' => $loan->pan_number ? strtoupper(trim($loan->pan_number)) : null,
                    'source' => 'cleanup',
                ]);

                $loan->update([
                    'customer_id' => $masterId,
                    'customer_kyc_details_id' => $kyc->id,
                ]);
            }
        });
    }

    private function resolveMasterIdForDirectLoan(LoanDetail $loan): int
    {
        $pan = $loan->pan_number ? strtoupper(trim($loan->pan_number)) : null;

        $master = $pan
            ? Customer::whereRaw('UPPER(pan_number) = ?', [$pan])->first()
            : null;

        $master ??= Customer::create([
            'customer_name' => $loan->customer_name,
            'mobile' => $loan->customer_phone,
            'email' => $loan->customer_email,
            'date_of_birth' => $loan->date_of_birth,
            'pan_number' => $pan,
            'created_by' => $loan->created_by,
        ]);

        return $master->id;
    }
}
