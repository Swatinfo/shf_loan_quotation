<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
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
use App\Services\PermissionService;
use App\Services\XlsxExportService;
use App\Services\XlsxImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bank reconciliation product check: the Excel `product` column is compared with
 * the loan's product (name / code / bank aliases), a loan logged under the wrong
 * product can be moved with one click, and the page re-runs on the kept upload.
 */
class PayoutReconcileProductTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Bank $bank;

    private Branch $branch;

    private Product $homeLoan;

    private Product $lap;

    protected function setUp(): void
    {
        parent::setUp();
        // Reconcile keeps each upload for re-runs — keep test uploads off the real disk.
        Storage::fake('local');
        app(PermissionService::class)->clearAllCaches();

        $this->admin = User::create(['name' => 'SA', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $this->admin->roles()->sync(Role::where('slug', 'super_admin')->pluck('id'));
        $this->admin = $this->admin->fresh('roles');

        $this->bank = Bank::create(['name' => 'ICICI', 'is_active' => true]);
        $this->branch = Branch::create(['name' => 'Br', 'is_active' => true]);
        $this->homeLoan = $this->product('HOME LOAN', 'HL', ['Housing Loan']);
        $this->lap = $this->product('LAP', null, []);
    }

    /** @param  array<int, string>  $aliases */
    private function product(string $name, ?string $code, array $aliases): Product
    {
        $product = Product::create([
            'name' => $name, 'code' => $code, 'bank_aliases' => $aliases ?: null,
            'bank_id' => $this->bank->id, 'is_active' => true,
        ]);
        $version = ProductPayoutVersion::create([
            'product_id' => $product->id, 'effective_from' => '2000-01-01',
            'is_pf_based' => false, 'payout_cycle_start_day' => 1, 'payout_cycle_end_day' => 31,
        ]);
        $product->update(['current_payout_version_id' => $version->id]);
        ProductPayoutSlab::create([
            'product_id' => $product->id, 'version_id' => $version->id, 'low_amount' => 0, 'high_amount' => 1000000000,
            'payout_type' => 'percent', 'payout_value' => 1.0, 'connector_payout_type' => 'percent', 'connector_payout_value' => 1.0,
        ]);

        return $product;
    }

    private function loan(Product $product, string $acc, int $amount = 500000): LoanDetail
    {
        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(), 'application_number' => 'APP-'.$acc, 'customer_name' => 'Cust '.$acc, 'customer_type' => 'salaried',
            'loan_amount' => $amount, 'status' => 'completed', 'current_stage' => 'disbursement',
            'bank_id' => $this->bank->id, 'branch_id' => $this->branch->id, 'product_id' => $product->id,
            'created_by' => $this->admin->id, 'payout_user_id' => $this->admin->id,
        ]);
        $disb = DisbursementDetail::create([
            'loan_id' => $loan->id, 'disbursement_type' => 'fund_transfer', 'amount_disbursed' => $amount,
            'pf_amount' => 0, 'admin_charges' => 0, 'insurance_amount' => 0,
            'entries' => [['row_id' => 'r1', 'product_id' => $product->id, 'product_name' => $product->name, 'amount' => $amount]],
        ]);
        DisbursementEntry::create([
            'loan_id' => $loan->id, 'disbursement_detail_id' => $disb->id, 'method' => 'fund_transfer',
            'product_id' => $product->id, 'product_name' => $product->name,
            'loan_account_number' => $acc, 'amount' => $amount, 'disbursement_date' => '2026-10-10', 'transfer_date' => '2026-10-10',
            'otc_status' => 'skipped', 'otc_handover_date' => '2026-10-10', 'is_active' => true,
        ]);

        return $loan;
    }

    /**
     * Rows: [acc, product, disbursed_amount].
     *
     * @param  array<int, array{0:string, 1:string, 2:int}>  $rows
     */
    private function upload(array $rows): UploadedFile
    {
        $headers = ['application_number', 'loan_acc_no', 'customer_name', 'product', 'cheque_number', 'disbursement_date', 'disbursed_amount', 'pf_amount', 'insurance_amount'];
        $mapped = array_map(fn (array $r) => ['', $r[0], '', $r[1], '', '10-10-2026', $r[2], 0, ''], $rows);

        $resp = app(XlsxExportService::class)->download('u.xlsx', $headers, $mapped);
        $tmp = tempnam(sys_get_temp_dir(), 'rcp').'.xlsx';
        copy($resp->getFile()->getPathname(), $tmp);

        return new UploadedFile($tmp, 'bank.xlsx', null, null, true);
    }

    /** @param  array<int, int>  $productIds */
    private function reconcile(array $productIds, UploadedFile $file): array
    {
        return $this->actingAs($this->admin)->post(route('payouts.reconcile.run'), [
            'bank_id' => $this->bank->id, 'product_ids' => $productIds,
            'from' => '2026-10-01', 'to' => '2026-10-31', 'file' => $file,
        ])->assertOk()->viewData('result');
    }

    public function test_template_has_a_product_column(): void
    {
        $resp = $this->actingAs($this->admin)->get(route('payouts.reconcile.template'))->assertOk();
        $headerRow = app(XlsxImportService::class)->readGrid($resp->baseResponse->getFile()->getPathname())[0] ?? [];

        $this->assertContains('product', $headerRow);
    }

    public function test_product_is_checked_by_name_code_and_alias(): void
    {
        $this->loan($this->homeLoan, 'ACC-1');
        $this->loan($this->homeLoan, 'ACC-2');
        $this->loan($this->homeLoan, 'ACC-3');
        $this->loan($this->homeLoan, 'ACC-4');
        $this->loan($this->homeLoan, 'ACC-5');
        $this->loan($this->homeLoan, 'ACC-6');

        $result = $this->reconcile([$this->homeLoan->id], $this->upload([
            ['ACC-1', 'home-loan', 500000],     // name, case/punctuation ignored
            ['ACC-2', 'HL', 500000],            // code
            ['ACC-3', 'Housing  Loan', 500000], // alias
            ['ACC-4', 'LAP', 500000],           // another product of the bank
            ['ACC-5', 'Gold Loan', 500000],     // not a product of the bank
            ['ACC-6', '', 500000],              // blank → not checked
        ]));

        $byAcc = collect($result['matched'])->keyBy(fn ($m) => $m['our']['accounts'][0]);
        $this->assertSame('ok', $byAcc['ACC-1']['product']['status']);
        $this->assertSame('ok', $byAcc['ACC-2']['product']['status']);
        $this->assertSame('ok', $byAcc['ACC-3']['product']['status']);
        $this->assertSame('mismatch', $byAcc['ACC-4']['product']['status']);
        $this->assertSame($this->lap->id, $byAcc['ACC-4']['product']['target_id']);
        $this->assertTrue($byAcc['ACC-4']['has_discrepancy']);
        $this->assertSame('unknown', $byAcc['ACC-5']['product']['status']);
        $this->assertTrue($byAcc['ACC-5']['has_discrepancy']);
        $this->assertSame('blank', $byAcc['ACC-6']['product']['status']);
        $this->assertFalse($byAcc['ACC-6']['has_discrepancy']);
        $this->assertSame(2, $result['product_issue_count']);
    }

    public function test_loan_logged_under_an_unselected_product_is_found_not_missing(): void
    {
        // Logged as LAP, but the bank says HOME LOAN and only HOME LOAN is reconciled.
        $loan = $this->loan($this->lap, 'ACC-W');

        $result = $this->reconcile([$this->homeLoan->id], $this->upload([['ACC-W', 'HOME LOAN', 500000]]));

        $this->assertCount(0, $result['excel_only']);
        $this->assertCount(1, $result['excel_in_db']);
        $row = $result['excel_in_db'][0];
        $this->assertSame($loan->id, $row['loan']['loan_id']);
        $this->assertSame('other_product', $row['category']);
        $this->assertSame('mismatch', $row['product']['status']);
        $this->assertSame($this->homeLoan->id, $row['product']['target_id']);
    }

    public function test_change_product_moves_the_loan_and_its_entries(): void
    {
        $loan = $this->loan($this->lap, 'ACC-C');

        $this->actingAs($this->admin)
            ->postJson(route('payouts.reconcile.change-product', $loan), ['product_id' => $this->homeLoan->id])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame($this->homeLoan->id, (int) $loan->fresh()->product_id);
        $entry = DisbursementEntry::where('loan_id', $loan->id)->first();
        $this->assertSame($this->homeLoan->id, (int) $entry->product_id);
        $this->assertSame('HOME LOAN', $entry->product_name);
        $json = $loan->fresh()->disbursement->entries[0];
        $this->assertSame($this->homeLoan->id, $json['product_id']);
        $this->assertSame('HOME LOAN', $json['product_name']);

        $log = ActivityLog::where('description', 'change_loan_product')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('reconcile', $log->properties['source']);
        $this->assertSame('LAP', $log->properties['old_product']);

        // Re-run on the same bank file: the loan now matches under HOME LOAN.
        $result = $this->reconcile([$this->homeLoan->id], $this->upload([['ACC-C', 'HOME LOAN', 500000]]));
        $this->assertCount(1, $result['matched']);
        $this->assertSame('ok', $result['matched'][0]['product']['status']);
    }

    public function test_account_numbers_link_to_the_loan_stages(): void
    {
        $matched = $this->loan($this->homeLoan, 'ACC-L1');
        $dbOnly = $this->loan($this->homeLoan, 'ACC-L2');
        $otherProduct = $this->loan($this->lap, 'ACC-L3');

        $html = $this->actingAs($this->admin)->post(route('payouts.reconcile.run'), [
            'bank_id' => $this->bank->id, 'product_ids' => [$this->homeLoan->id],
            'from' => '2026-10-01', 'to' => '2026-10-31',
            'file' => $this->upload([['ACC-L1', 'HOME LOAN', 500000], ['ACC-L3', 'LAP', 500000]]),
        ])->assertOk()->getContent();

        foreach ([$matched, $dbOnly, $otherProduct] as $loan) {
            $this->assertStringContainsString('href="'.route('loans.stages', $loan).'"', $html);
        }
        // Loan numbers render in the brand orange.
        $this->assertStringContainsString('class="po-loan-no"', $html);
    }

    public function test_entryless_mismatch_row_links_bank_identifier_to_the_loan_stages(): void
    {
        // On-hold loan with NO disbursement entries — found by application number only.
        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(), 'application_number' => 'APP-HOLD-1', 'customer_name' => 'Hold Cust', 'customer_type' => 'salaried',
            'loan_amount' => 500000, 'status' => 'on_hold', 'current_stage' => 'docket',
            'bank_id' => $this->bank->id, 'branch_id' => $this->branch->id, 'product_id' => $this->homeLoan->id,
            'created_by' => $this->admin->id,
        ]);
        $headers = ['application_number', 'loan_acc_no', 'customer_name', 'product', 'cheque_number', 'disbursement_date', 'disbursed_amount', 'pf_amount', 'insurance_amount'];
        $resp = app(XlsxExportService::class)->download('u.xlsx', $headers, [['APP-HOLD-1', '', '', 'HOME LOAN', '', '10-10-2026', 500000, 0, '']]);
        $tmp = tempnam(sys_get_temp_dir(), 'rch').'.xlsx';
        copy($resp->getFile()->getPathname(), $tmp);

        $response = $this->actingAs($this->admin)->post(route('payouts.reconcile.run'), [
            'bank_id' => $this->bank->id, 'product_ids' => [$this->homeLoan->id],
            'from' => '2026-10-01', 'to' => '2026-10-31', 'file' => new UploadedFile($tmp, 'bank.xlsx', null, null, true),
        ])->assertOk();

        $this->assertCount(1, $response->viewData('result')['excel_in_db']);
        $this->assertStringContainsString('href="'.route('loans.stages', $loan).'"', $response->getContent());
    }

    public function test_change_product_refuses_another_banks_product(): void
    {
        $loan = $this->loan($this->lap, 'ACC-B');
        $otherBank = Bank::create(['name' => 'HDFC', 'is_active' => true]);
        $foreign = Product::create(['name' => 'HOME LOAN', 'bank_id' => $otherBank->id, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->postJson(route('payouts.reconcile.change-product', $loan), ['product_id' => $foreign->id])
            ->assertStatus(422);

        $this->assertSame($this->lap->id, (int) $loan->fresh()->product_id);
    }

    public function test_change_product_refuses_a_loan_already_in_a_payout_run(): void
    {
        $loan = $this->loan($this->lap, 'ACC-P');
        DisbursementEntry::where('loan_id', $loan->id)->update(['payout_run_id' => 99]);

        $this->actingAs($this->admin)
            ->postJson(route('payouts.reconcile.change-product', $loan), ['product_id' => $this->homeLoan->id])
            ->assertStatus(422)->assertJson(['success' => false]);

        $this->assertSame($this->lap->id, (int) $loan->fresh()->product_id);
    }

    public function test_change_product_needs_the_finalize_payout_permission(): void
    {
        $loan = $this->loan($this->lap, 'ACC-N');
        $viewer = User::create(['name' => 'V', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $viewer->roles()->sync(Role::where('slug', 'loan_advisor')->pluck('id'));

        $this->actingAs($viewer->fresh('roles'))
            ->postJson(route('payouts.reconcile.change-product', $loan), ['product_id' => $this->homeLoan->id])
            ->assertForbidden();

        $this->assertSame($this->lap->id, (int) $loan->fresh()->product_id);
    }

    public function test_rerun_uses_the_kept_upload_and_an_unknown_token_asks_to_upload_again(): void
    {
        $this->loan($this->homeLoan, 'ACC-R');
        $first = $this->reconcile([$this->homeLoan->id], $this->upload([['ACC-R', 'HOME LOAN', 500000]]));
        $this->assertNotEmpty($first['upload_token']);

        $params = ['bank_id' => $this->bank->id, 'product_ids' => [$this->homeLoan->id], 'from' => '2026-10-01', 'to' => '2026-10-31'];
        $again = $this->actingAs($this->admin)->post(route('payouts.reconcile.run'), $params + ['upload_token' => $first['upload_token']])
            ->assertOk()->viewData('result');
        $this->assertCount(1, $again['matched']);

        $this->actingAs($this->admin)->post(route('payouts.reconcile.run'), $params + ['upload_token' => '00000000-0000-4000-8000-000000000000'])
            ->assertRedirect(route('payouts.reconcile'));
    }

    public function test_settings_saves_bank_aliases_and_rejects_a_clash(): void
    {
        $this->actingAs($this->admin)->post(route('loan-settings.products.store'), [
            'id' => $this->lap->id, 'bank_id' => $this->bank->id, 'name' => 'LAP',
            'bank_aliases' => 'Loan Against Property,  LAP-Prime , loan against property',
        ])->assertRedirect();
        $this->assertSame(['Loan Against Property', 'LAP-Prime'], $this->lap->fresh()->bank_aliases);

        // "HL" is HOME LOAN's code — can't also be a LAP alias.
        $this->actingAs($this->admin)->post(route('loan-settings.products.store'), [
            'id' => $this->lap->id, 'bank_id' => $this->bank->id, 'name' => 'LAP', 'bank_aliases' => 'HL',
        ])->assertSessionHas('error');
        $this->assertSame(['Loan Against Property', 'LAP-Prime'], $this->lap->fresh()->bank_aliases);
    }
}
