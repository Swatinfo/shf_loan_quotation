<?php

namespace Tests\Feature;

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
}
