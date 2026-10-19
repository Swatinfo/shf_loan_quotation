<?php

namespace Tests\Feature;

use App\Http\Controllers\PayoutController;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\DisbursementDetail;
use App\Models\DisbursementEntry;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\ProductPayoutSlab;
use App\Models\ProductPayoutVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\XlsxExportService;
use App\Services\XlsxImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Bank payout reconciliation: match our disbursed entries against an uploaded
 * bank statement on loan A/c + amount + date; highlight DB-only / Excel-only;
 * total the payout for matched rows.
 */
class PayoutReconcileTest extends TestCase
{
    use RefreshDatabase;

    private function samplePath(): string
    {
        return base_path('.scratch/payout-sample.xlsx');
    }

    public function test_xlsx_reader_reads_sample_headers_and_date(): void
    {
        if (! file_exists($this->samplePath())) {
            $this->markTestSkipped('sample xlsx not present');
        }
        $rows = app(XlsxImportService::class)->readAssoc($this->samplePath());
        $this->assertGreaterThan(40, count($rows));
        $this->assertArrayHasKey('loan_acc_no', $rows[0]);
        $this->assertSame('2024-07-31', XlsxImportService::excelDate($rows[0]['disbursement_date'])->toDateString());
    }

    public function test_template_downloads_blank_format_without_params(): void
    {
        $admin = User::create(['name' => 'SA', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $admin->roles()->sync(Role::where('slug', 'super_admin')->pluck('id'));

        $resp = $this->actingAs($admin->fresh('roles'))->get(route('payouts.reconcile.template'));

        $resp->assertOk();
        $resp->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('payout-reconciliation-template.xlsx', $resp->headers->get('content-disposition'));
    }

    public function test_reconciliation_matches_and_flags_discrepancies(): void
    {
        if (! file_exists($this->samplePath())) {
            $this->markTestSkipped('sample xlsx not present');
        }

        $admin = User::create(['name' => 'SA', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $admin->roles()->sync(Role::where('slug', 'super_admin')->pluck('id'));
        $admin = $admin->fresh('roles');

        $bank = Bank::create(['name' => 'ICICI', 'is_active' => true]);
        $branch = Branch::create(['name' => 'Br', 'is_active' => true]);
        $product = Product::create(['name' => 'HL', 'bank_id' => $bank->id, 'is_active' => true]);
        $version = ProductPayoutVersion::create([
            'product_id' => $product->id, 'effective_from' => '2000-01-01',
            'is_pf_based' => false, 'payout_cycle_start_day' => 1, 'payout_cycle_end_day' => 31,
        ]);
        $product->update(['current_payout_version_id' => $version->id]);
        ProductPayoutSlab::create([
            'product_id' => $product->id, 'version_id' => $version->id,
            'low_amount' => 0, 'high_amount' => 1000000000,
            'payout_type' => 'percent', 'payout_value' => 1.0,
            'connector_payout_type' => 'percent', 'connector_payout_value' => 2.0,
        ]);

        // Two loans whose disbursement entries match the first two sample rows.
        $rows = app(XlsxImportService::class)->readAssoc($this->samplePath());
        foreach (array_slice($rows, 0, 2) as $r) {
            $loan = LoanDetail::create([
                'loan_number' => 'L-'.uniqid(), 'customer_name' => $r['customer_name'], 'customer_type' => 'salaried',
                'loan_amount' => (int) $r['loan_amount'], 'status' => 'completed', 'current_stage' => 'disbursement',
                'bank_id' => $bank->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
                'created_by' => $admin->id, 'payout_user_id' => $admin->id,
            ]);
            $disb = DisbursementDetail::create(['loan_id' => $loan->id, 'disbursement_type' => 'cheque', 'amount_disbursed' => 0]);
            $settleDate = XlsxImportService::excelDate($r['disbursement_date'])->toDateString();
            DisbursementEntry::create([
                'loan_id' => $loan->id, 'disbursement_detail_id' => $disb->id, 'method' => 'cheque',
                'loan_account_number' => $r['loan_acc_no'], 'amount' => (int) $r['loan_amount'],
                'disbursement_date' => $settleDate,
                // Settlement-date basis: reconcile matches on otc_handover_date (cleared cheque date).
                'otc_status' => 'cleared', 'otc_handover_date' => $settleDate,
                'is_active' => true,
            ]);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rc').'.xlsx';
        copy($this->samplePath(), $tmp);
        $upload = new UploadedFile($tmp, 'bank.xlsx', null, null, true);

        $resp = $this->actingAs($admin)->post(route('payouts.reconcile.run'), [
            'bank_id' => $bank->id,
            'product_ids' => [$product->id],
            'from' => '2024-07-01',
            'to' => '2024-08-31',
            'file' => $upload,
        ])->assertOk();

        $result = $resp->viewData('result');
        $this->assertCount(2, $result['matched']);          // our 2 entries matched the file
        $this->assertCount(0, $result['db_only']);          // nothing in DB missing from the file
        $this->assertSame(count($rows) - 2, $result['excel_only']->count()); // the rest are excel-only
        // Net per entry = commission (1% of amount) − 5% TDS (no PF/insurance on these rows).
        $expected = 0;
        foreach (array_slice($rows, 0, 2) as $r) {
            $commission = (int) round(((int) $r['loan_amount']) * 0.01);
            $expected += $commission - (int) round($commission * 0.05);
        }
        $this->assertSame($expected, $result['total_payout']);

        // Bottom summaries: both loans share bank ICICI / product HL / payout user.
        $this->assertCount(1, $result['by_product']);
        $this->assertSame('HL', $result['by_product'][0]['product']);
        $this->assertSame($expected, $result['by_product'][0]['total']);

        $this->assertCount(1, $result['by_user']);
        $this->assertSame($expected, $result['by_user'][0]['total']);
    }

    public function test_insurance_is_taken_from_the_uploaded_sheet_with_db_fallback(): void
    {
        [$admin, $bank, $branch, $product] = $this->scaffold();
        // Loan 1: DB insurance 1000, upload says 5000 → calc uses the uploaded 5000.
        $this->makeLoanWithEntry($bank, $branch, $product, $admin, 'ACC-1', 'APP-1', 500000, '2024-07-31', 1000);
        // Loan 2: DB insurance 2000, upload leaves the cell blank → calc falls back to 2000.
        $this->makeLoanWithEntry($bank, $branch, $product, $admin, 'ACC-2', 'APP-2', 600000, '2024-07-31', 2000);

        $upload = $this->makeUpload([
            ['ACC-1', 'Cust', '', '2024-07-31', 500000, 0, 0, 5000],
            ['ACC-2', 'Cust', '', '2024-07-31', 600000, 0, 0, ''], // blank insurance
        ]);

        $resp = $this->actingAs($admin)->post(route('payouts.reconcile.run'), [
            'bank_id' => $bank->id, 'product_ids' => [$product->id],
            'from' => '2024-07-01', 'to' => '2024-08-31', 'file' => $upload,
        ])->assertOk();

        $matched = collect($resp->viewData('result')['matched'])
            ->keyBy(fn ($m) => $m['our']['accounts'][0]);
        $this->assertSame(5000, $matched['ACC-1']['calc']['insurance']); // from upload (overrides DB 1000)
        $this->assertSame(2000, $matched['ACC-2']['calc']['insurance']); // DB fallback (blank cell)
    }

    public function test_template_includes_the_application_number_column(): void
    {
        $admin = User::create(['name' => 'SA', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $admin->roles()->sync(Role::where('slug', 'super_admin')->pluck('id'));

        $resp = $this->actingAs($admin->fresh('roles'))->get(route('payouts.reconcile.template'))->assertOk();
        $headerRow = app(XlsxImportService::class)->readGrid($resp->baseResponse->getFile()->getPathname())[0] ?? [];

        $this->assertContains('application_number', $headerRow);
        $this->assertContains('loan_acc_no', $headerRow);
    }

    public function test_matches_on_the_application_number_column(): void
    {
        [$admin, $bank, $branch, $product] = $this->scaffold();
        // Our loan: A/c = ACC-X, application number = APP-999.
        $this->makeLoanWithEntry($bank, $branch, $product, $admin, 'ACC-X', 'APP-999', 500000, '2026-08-20', 0);

        // Bank statement carries only the APPLICATION number (A/c column blank).
        $upload = $this->makeUpload([
            ['', 'Cust', '', '20-08-2026', 500000, 0, 0, 0, 'APP-999'],
        ]);
        $resp = $this->actingAs($admin)->post(route('payouts.reconcile.run'), [
            'bank_id' => $bank->id, 'product_ids' => [$product->id],
            'from' => '2026-08-16', 'to' => '2026-09-15', 'file' => $upload,
        ])->assertOk();

        $result = $resp->viewData('result');
        $this->assertCount(1, $result['matched']);          // matched via the app-number column
        $this->assertCount(0, $result['excel_only']);
        $this->assertCount(0, $result['excel_in_db']);
    }

    public function test_multi_tranche_loan_matches_on_identifier_and_flags_discrepancies(): void
    {
        [$admin, $bank, $branch, $product] = $this->scaffold();
        // One loan, 3 tranches on the same A/c, settled 2026-08-27; total disbursed 600000.
        $this->makeLoanWithTranches($bank, $branch, $product, $admin, 'ACC-7', 'APP-7', [100000, 200000, 300000], '2026-08-27');

        // Bank files ONE row for the loan: different amount, different (d-m-Y) date.
        $upload = $this->makeUpload([
            ['ACC-7', 'Cust', '', '19-08-2026', 650000, 0, 0, ''],
        ]);

        $resp = $this->actingAs($admin)->post(route('payouts.reconcile.run'), [
            'bank_id' => $bank->id, 'product_ids' => [$product->id],
            'from' => '2026-08-16', 'to' => '2026-09-15', 'file' => $upload,
        ])->assertOk();

        $result = $resp->viewData('result');
        // Identifier matched → NOT split into the two not-found buckets.
        $this->assertCount(1, $result['matched']);
        $this->assertCount(0, $result['db_only']);
        $this->assertSame(0, $result['excel_only']->count());

        $m = $result['matched'][0];
        $this->assertTrue($m['has_discrepancy']);
        $this->assertTrue($m['flags']['amount']);
        $this->assertSame(600000, $m['our']['amount']);   // Σ tranches
        $this->assertSame(650000, $m['excel']['_amount']); // bank figure
        $this->assertTrue($m['flags']['date']);
        $this->assertSame('2026-08-19', $m['excel']['_date']); // d-m-Y parsed
        $this->assertSame(['2026-08-27'], $m['our']['settlement_dates']);
        $this->assertSame(1, $result['discrepancy_count']);
    }

    public function test_window_uses_settlement_date_cheque_cleared_in_window_matches(): void
    {
        [$admin, $bank, $branch, $product] = $this->scaffold();
        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(), 'application_number' => 'APP-WIN', 'customer_name' => 'Cust', 'customer_type' => 'salaried',
            'loan_amount' => 500000, 'status' => 'completed', 'current_stage' => 'disbursement',
            'bank_id' => $bank->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'created_by' => $admin->id, 'payout_user_id' => $admin->id,
        ]);
        $disb = DisbursementDetail::create(['loan_id' => $loan->id, 'disbursement_type' => 'cheque', 'amount_disbursed' => 0, 'pf_amount' => 0, 'admin_charges' => 0, 'insurance_amount' => 0]);
        DisbursementEntry::create([
            'loan_id' => $loan->id, 'disbursement_detail_id' => $disb->id, 'method' => 'cheque',
            'loan_account_number' => 'ACC-WIN', 'amount' => 500000,
            'disbursement_date' => '2026-08-14',             // disbursed BEFORE window
            'otc_status' => 'cleared', 'otc_handover_date' => '2026-08-27', // cheque CLEARED in window
            'is_active' => true,
        ]);

        $upload = $this->makeUpload([['ACC-WIN', 'Cust', '', '19-08-2026', 500000, 0, 0, 0]]);
        $resp = $this->actingAs($admin)->post(route('payouts.reconcile.run'), [
            'bank_id' => $bank->id, 'product_ids' => [$product->id],
            'from' => '2026-08-16', 'to' => '2026-09-15', 'file' => $upload,
        ])->assertOk();

        // The cheque cleared 27/08 (settlement date), in the window → it MATCHES
        // (even though it was disbursed 14/08, before the window start).
        $result = $resp->viewData('result');
        $this->assertCount(1, $result['matched']);
        $this->assertSame(['2026-08-27'], $result['matched'][0]['our']['settlement_dates']);
        $this->assertCount(0, $result['excel_only']);
    }

    public function test_record_settled_outside_window_is_exists_in_db_not_missing(): void
    {
        [$admin, $bank, $branch, $product] = $this->scaffold();
        // Settled 10/07 — well before the 16/08–15/09 window.
        $this->makeLoanWithEntry($bank, $branch, $product, $admin, 'ACC-OUT', 'APP-OUT', 500000, '2026-07-10', 0);
        // A row the bank statement lists in this period, for that same account.
        $upload = $this->makeUpload([['ACC-OUT', 'Cust', '', '19-08-2026', 500000, 0, 0, 0]]);

        $resp = $this->actingAs($admin)->post(route('payouts.reconcile.run'), [
            'bank_id' => $bank->id, 'product_ids' => [$product->id],
            'from' => '2026-08-16', 'to' => '2026-09-15', 'file' => $upload,
        ])->assertOk();

        $result = $resp->viewData('result');
        // The record EXISTS in our DB (settled out of period) → "exists in DB, mismatch",
        // NOT "not in DB".
        $this->assertCount(0, $result['matched']);
        $this->assertCount(0, $result['excel_only']);
        $this->assertCount(1, $result['excel_in_db']);
        $this->assertSame('ACC-OUT', $result['excel_in_db'][0]['loan']['accounts'][0]);
        $this->assertSame('outside', $result['excel_in_db'][0]['category']);
    }

    public function test_pending_cheque_is_exists_in_db_with_pending_reason_not_disbursement_date(): void
    {
        [$admin, $bank, $branch, $product] = $this->scaffold();
        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(), 'application_number' => 'APP-PEND', 'customer_name' => 'Cust', 'customer_type' => 'salaried',
            'loan_amount' => 500000, 'status' => 'completed', 'current_stage' => 'disbursement',
            'bank_id' => $bank->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'created_by' => $admin->id, 'payout_user_id' => $admin->id,
        ]);
        $disb = DisbursementDetail::create(['loan_id' => $loan->id, 'disbursement_type' => 'cheque', 'amount_disbursed' => 0, 'pf_amount' => 0, 'admin_charges' => 0, 'insurance_amount' => 0]);
        DisbursementEntry::create([
            'loan_id' => $loan->id, 'disbursement_detail_id' => $disb->id, 'method' => 'cheque',
            'loan_account_number' => 'ACC-PEND', 'amount' => 500000,
            'disbursement_date' => '2026-08-29',             // in the window…
            'otc_status' => 'pending', 'otc_handover_date' => null, // …but the cheque is NOT cleared
            'is_active' => true,
        ]);

        $upload = $this->makeUpload([['ACC-PEND', 'Cust', '', '29-08-2026', 500000, 0, 0, 0]]);
        $resp = $this->actingAs($admin)->post(route('payouts.reconcile.run'), [
            'bank_id' => $bank->id, 'product_ids' => [$product->id],
            'from' => '2026-08-16', 'to' => '2026-09-15', 'file' => $upload,
        ])->assertOk();

        $result = $resp->viewData('result');
        $this->assertCount(0, $result['matched']);
        $this->assertCount(1, $result['excel_in_db']);
        $row = $result['excel_in_db'][0];
        // The DB clearance date is EMPTY (we no longer fall back to the disbursement
        // date), the loan is flagged pending, and the reason says so.
        $this->assertTrue($row['loan']['pending']);
        $this->assertSame([], $row['loan']['settlement_dates']);
        $this->assertStringContainsString('pending', strtolower($row['reason']));
        $this->assertSame('pending', $row['category']);
    }

    public function test_second_account_of_a_matched_loan_shows_as_difference(): void
    {
        [$admin, $bank, $branch, $product] = $this->scaffold();
        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(), 'application_number' => 'APP-DUP', 'customer_name' => 'Cust', 'customer_type' => 'salaried',
            'loan_amount' => 500000, 'status' => 'completed', 'current_stage' => 'disbursement',
            'bank_id' => $bank->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'created_by' => $admin->id, 'payout_user_id' => $admin->id,
        ]);
        $disb = DisbursementDetail::create(['loan_id' => $loan->id, 'disbursement_type' => 'cheque', 'amount_disbursed' => 0, 'pf_amount' => 0, 'admin_charges' => 0, 'insurance_amount' => 0]);
        // Same loan, TWO different bank account numbers, both settled in the window.
        foreach ([['ACC-A', 300000], ['ACC-B', 200000]] as [$acc, $amt]) {
            DisbursementEntry::create([
                'loan_id' => $loan->id, 'disbursement_detail_id' => $disb->id, 'method' => 'cheque',
                'loan_account_number' => $acc, 'amount' => $amt, 'disbursement_date' => '2026-08-20',
                'otc_status' => 'cleared', 'otc_handover_date' => '2026-08-20', 'is_active' => true,
            ]);
        }
        // Bank file lists one row per account.
        $upload = $this->makeUpload([
            ['ACC-A', 'Cust', '', '20-08-2026', 300000, 0, 0, 0],
            ['ACC-B', 'Cust', '', '20-08-2026', 200000, 0, 0, 0],
        ]);

        $resp = $this->actingAs($admin)->post(route('payouts.reconcile.run'), [
            'bank_id' => $bank->id, 'product_ids' => [$product->id],
            'from' => '2026-08-16', 'to' => '2026-09-15', 'file' => $upload,
        ])->assertOk();

        $result = $resp->viewData('result');
        // The loan matches once (via one account); the other account's row is shown as a difference.
        $this->assertCount(1, $result['matched']);
        $this->assertCount(1, $result['excel_in_db']);
        $this->assertSame('difference', $result['excel_in_db'][0]['category']);
    }

    public function test_on_hold_loan_is_shown_with_status_not_as_missing_or_payable(): void
    {
        [$admin, $bank, $branch, $product] = $this->scaffold();
        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(), 'application_number' => 'APP-HOLD', 'customer_name' => 'Cust', 'customer_type' => 'salaried',
            'loan_amount' => 500000, 'status' => 'on_hold', 'current_stage' => 'disbursement',
            'bank_id' => $bank->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'created_by' => $admin->id, 'payout_user_id' => $admin->id,
        ]);
        $disb = DisbursementDetail::create(['loan_id' => $loan->id, 'disbursement_type' => 'cheque', 'amount_disbursed' => 0, 'pf_amount' => 0, 'admin_charges' => 0, 'insurance_amount' => 0]);
        DisbursementEntry::create([
            'loan_id' => $loan->id, 'disbursement_detail_id' => $disb->id, 'method' => 'cheque',
            'loan_account_number' => 'ACC-HOLD', 'amount' => 500000, 'disbursement_date' => '2026-08-20',
            'otc_status' => 'cleared', 'otc_handover_date' => '2026-08-20', 'is_active' => true, // settled in-window
        ]);

        $upload = $this->makeUpload([['ACC-HOLD', 'Cust', '', '20-08-2026', 500000, 0, 0, 0]]);
        $resp = $this->actingAs($admin)->post(route('payouts.reconcile.run'), [
            'bank_id' => $bank->id, 'product_ids' => [$product->id],
            'from' => '2026-08-16', 'to' => '2026-09-15', 'file' => $upload,
        ])->assertOk();

        $result = $resp->viewData('result');
        // On-hold loan is NOT paid (excluded from matching), and NOT "not in DB" — it's
        // surfaced with its status.
        $this->assertCount(0, $result['matched']);
        $this->assertCount(0, $result['excel_only']);
        $this->assertCount(1, $result['excel_in_db']);
        $this->assertSame('status', $result['excel_in_db'][0]['category']);
        $this->assertSame('on_hold', $result['excel_in_db'][0]['loan']['status']);
        $this->assertStringContainsString('hold', strtolower($result['excel_in_db'][0]['reason']));
    }

    public function test_truly_unknown_account_is_not_in_db(): void
    {
        [$admin, $bank, $branch, $product] = $this->scaffold();
        $this->makeLoanWithEntry($bank, $branch, $product, $admin, 'ACC-KNOWN', 'APP-K', 500000, '2026-08-20', 0);
        // Bank row for an account that exists nowhere in our DB.
        $upload = $this->makeUpload([['ACC-GHOST', 'Nobody', '', '19-08-2026', 777000, 0, 0, 0]]);

        $resp = $this->actingAs($admin)->post(route('payouts.reconcile.run'), [
            'bank_id' => $bank->id, 'product_ids' => [$product->id],
            'from' => '2026-08-16', 'to' => '2026-09-15', 'file' => $upload,
        ])->assertOk();

        $result = $resp->viewData('result');
        $this->assertCount(1, $result['excel_only']);   // genuinely missing
        $this->assertCount(0, $result['excel_in_db']);
    }

    public function test_dates_parse_across_formats_and_bad_dates_are_flagged(): void
    {
        $parse = new \ReflectionMethod(PayoutController::class, 'parseFlexibleDate');
        $parse->setAccessible(true);
        $ctrl = new PayoutController;

        foreach (['19-08-2026', '19/08/2026', '19.08.2026', '19,08,2026', '9-8-2026', '2026-08-19'] as $in) {
            [$date, $err] = $parse->invoke($ctrl, $in);
            $this->assertFalse($err, $in.' should parse');
            $this->assertNotNull($date, $in.' should parse');
        }
        // Single-digit day/month example resolves to the right day.
        [$d9] = $parse->invoke($ctrl, '9-8-2026');
        $this->assertSame('2026-08-09', $d9->toDateString());

        // Blank → no date, no error. Garbage → flagged.
        $this->assertSame([null, false], $parse->invoke($ctrl, ''));
        [$bad, $badErr] = $parse->invoke($ctrl, 'not-a-date');
        $this->assertNull($bad);
        $this->assertTrue($badErr);
    }

    public function test_row_identified_by_application_number_still_matches(): void
    {
        [$admin, $bank, $branch, $product] = $this->scaffold();
        $this->makeLoanWithEntry($bank, $branch, $product, $admin, 'ACC-999', 'APP-123', 500000, '2024-07-31', 0);

        // Bank put our APPLICATION number in the identifier column, not the A/c number.
        $upload = $this->makeUpload([
            ['APP-123', 'Cust', '', '2024-07-31', 500000, 0, 0, 0],
        ]);

        $resp = $this->actingAs($admin)->post(route('payouts.reconcile.run'), [
            'bank_id' => $bank->id, 'product_ids' => [$product->id],
            'from' => '2024-07-01', 'to' => '2024-08-31', 'file' => $upload,
        ])->assertOk();

        $result = $resp->viewData('result');
        $this->assertCount(1, $result['matched']);
        $this->assertCount(0, $result['db_only']);
        $this->assertSame(0, $result['excel_only']->count());
    }

    /**
     * Bank + branch + a non-PF product with a 1% slab, and a super-admin actor.
     *
     * @return array{0: User, 1: Bank, 2: Branch, 3: Product}
     */
    private function scaffold(): array
    {
        $admin = User::create(['name' => 'SA', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $admin->roles()->sync(Role::where('slug', 'super_admin')->pluck('id'));

        $bank = Bank::create(['name' => 'ICICI', 'is_active' => true]);
        $branch = Branch::create(['name' => 'Br', 'is_active' => true]);
        $product = Product::create(['name' => 'HL-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);
        $version = ProductPayoutVersion::create([
            'product_id' => $product->id, 'effective_from' => '2000-01-01',
            'is_pf_based' => false, 'payout_cycle_start_day' => 1, 'payout_cycle_end_day' => 31,
        ]);
        $product->update(['current_payout_version_id' => $version->id]);
        ProductPayoutSlab::create([
            'product_id' => $product->id, 'version_id' => $version->id,
            'low_amount' => 0, 'high_amount' => 1000000000,
            'payout_type' => 'percent', 'payout_value' => 1.0,
            'connector_payout_type' => 'percent', 'connector_payout_value' => 2.0,
        ]);

        return [$admin->fresh('roles'), $bank, $branch, $product];
    }

    private function makeLoanWithEntry(Bank $bank, Branch $branch, Product $product, User $admin, string $acc, string $appNo, int $amount, string $date, int $insuranceDb): LoanDetail
    {
        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(), 'application_number' => $appNo, 'customer_name' => 'Cust', 'customer_type' => 'salaried',
            'loan_amount' => $amount, 'status' => 'completed', 'current_stage' => 'disbursement',
            'bank_id' => $bank->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'created_by' => $admin->id, 'payout_user_id' => $admin->id,
        ]);
        $disb = DisbursementDetail::create([
            'loan_id' => $loan->id, 'disbursement_type' => 'cheque', 'amount_disbursed' => 0,
            'pf_amount' => 0, 'admin_charges' => 0, 'insurance_amount' => $insuranceDb,
        ]);
        DisbursementEntry::create([
            'loan_id' => $loan->id, 'disbursement_detail_id' => $disb->id, 'method' => 'cheque',
            'loan_account_number' => $acc, 'amount' => $amount, 'disbursement_date' => $date,
            'otc_status' => 'cleared', 'otc_handover_date' => $date, 'is_active' => true,
        ]);

        return $loan;
    }

    /**
     * A loan with several disbursement tranches sharing one A/c number + settlement date.
     *
     * @param  array<int, int>  $amounts
     */
    private function makeLoanWithTranches(Bank $bank, Branch $branch, Product $product, User $admin, string $acc, string $appNo, array $amounts, string $date): LoanDetail
    {
        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(), 'application_number' => $appNo, 'customer_name' => 'Cust', 'customer_type' => 'salaried',
            'loan_amount' => array_sum($amounts), 'status' => 'completed', 'current_stage' => 'disbursement',
            'bank_id' => $bank->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'created_by' => $admin->id, 'payout_user_id' => $admin->id,
        ]);
        $disb = DisbursementDetail::create([
            'loan_id' => $loan->id, 'disbursement_type' => 'cheque', 'amount_disbursed' => 0,
            'pf_amount' => 0, 'admin_charges' => 0, 'insurance_amount' => 0,
        ]);
        foreach ($amounts as $amt) {
            DisbursementEntry::create([
                'loan_id' => $loan->id, 'disbursement_detail_id' => $disb->id, 'method' => 'cheque',
                'loan_account_number' => $acc, 'amount' => $amt, 'disbursement_date' => $date,
                'otc_status' => 'cleared', 'otc_handover_date' => $date, 'is_active' => true,
            ]);
        }

        return $loan;
    }

    /**
     * Build a reconciliation upload with the standard column headers and the
     * given data rows (each row indexed to match COLUMNS order).
     *
     * @param  array<int, array<int, mixed>>  $rows
     */
    /**
     * Build an upload. Rows are given in the convenient order
     * [acc, customer, cheque, date, loan_amount, pf, admin, insurance, application?]
     * and are mapped here to the actual column order (application_number is first).
     *
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function makeUpload(array $rows): UploadedFile
    {
        $headers = ['application_number', 'loan_acc_no', 'customer_name', 'cheque_number', 'disbursement_date', 'loan_amount', 'pf_amount', 'admin_charges', 'insurance_amount'];
        $mapped = array_map(function (array $r) {
            [$acc, $cust, $cheque, $date, $amt, $pf, $admin, $ins] = array_pad(array_slice($r, 0, 8), 8, '');

            return [$r[8] ?? '', $acc, $cust, $cheque, $date, $amt, $pf, $admin, $ins];
        }, $rows);

        $resp = app(XlsxExportService::class)->download('u.xlsx', $headers, $mapped);
        $tmp = tempnam(sys_get_temp_dir(), 'rcu').'.xlsx';
        copy($resp->getFile()->getPathname(), $tmp);

        return new UploadedFile($tmp, 'bank.xlsx', null, null, true);
    }
}
