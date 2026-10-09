<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\DisbursementEntry;
use App\Models\LoanDetail;
use App\Models\LoanPayout;
use App\Models\PayoutRun;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stage;
use App\Models\StageAssignment;
use App\Models\User;
use App\Services\DisbursementDataService;
use App\Services\DisbursementService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Super-admin disbursement/OTC correction tool: export is gated to super_admin,
 * and an import rewrites the tranche + JSON + amounts and re-resolves status.
 */
class DisbursementDataToolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        Role::firstOrCreate(['slug' => 'loan_advisor'], ['name' => 'Loan Advisor']);
        foreach (['inquiry', 'disbursement', 'otc_clearance'] as $i => $key) {
            Stage::firstOrCreate(['stage_key' => $key], [
                'stage_name_en' => $key, 'stage_name_gu' => $key, 'sequence_order' => $i + 1,
                'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'is_enabled' => true,
            ]);
        }
        $this->mock(NotificationService::class)->shouldIgnoreMissing();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        $u = User::create(['name' => $role, 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->roles()->sync(Role::where('slug', $role)->pluck('id'));

        return $u->fresh('roles');
    }

    /**
     * @param  array<int,array<string,mixed>>  $tranches  each: [amount, otc('pending'|'skipped'|'cleared')]
     * @return array{0: LoanDetail, 1: User}
     */
    private function disbursingLoan(array $tranches = [[1000000, 'pending']]): array
    {
        $owner = $this->user('loan_advisor');
        $bank = Bank::create(['name' => 'ICICI', 'is_active' => true]);
        $branch = Branch::create(['name' => 'Br', 'is_active' => true]);
        $product = Product::create(['name' => 'Home Loan', 'bank_id' => $bank->id, 'is_active' => true]);

        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(), 'application_number' => 'APP-'.uniqid(), 'customer_name' => 'C',
            'customer_type' => 'salaried', 'loan_amount' => 2000000, 'sanctioned_amount' => 2000000,
            'status' => 'active', 'current_stage' => 'disbursement',
            'bank_id' => $bank->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'created_by' => $owner->id, 'assigned_advisor' => $owner->id,
        ]);
        StageAssignment::create(['loan_id' => $loan->id, 'stage_key' => 'disbursement', 'assigned_to' => $owner->id, 'status' => 'in_progress', 'is_parallel_stage' => false]);
        StageAssignment::create(['loan_id' => $loan->id, 'stage_key' => 'otc_clearance', 'assigned_to' => $owner->id, 'status' => 'pending', 'is_parallel_stage' => false]);

        // Build the tranches via the service directly (the HTTP route needs
        // manage_loan_stages, which isn't granted under RefreshDatabase).
        $entries = [];
        foreach ($tranches as $i => [$amount, $otc]) {
            $entries[] = [
                'disbursement_date' => now()->toDateString(), 'method' => 'fund_transfer',
                'product_id' => $product->id, 'product_name' => $product->name,
                'loan_account_number' => 'LA-'.($i + 1), 'amount' => $amount, 'otc_status' => $otc,
                'otc_handover_date' => $otc === 'cleared' ? now()->toDateString() : null,
            ];
        }
        app(DisbursementService::class)->processDisbursement($loan, ['entries' => $entries, 'notes' => null]);

        return [$loan->fresh(), $owner];
    }

    public function test_tool_is_super_admin_only(): void
    {
        $advisor = $this->user('loan_advisor');
        $this->actingAs($advisor)->get(route('loans.disbursement-data'))->assertForbidden();
        $this->actingAs($advisor)->get(route('loans.disbursement-data.export'))->assertForbidden();
    }

    public function test_export_downloads_xlsx_for_super_admin(): void
    {
        [$loan] = $this->disbursingLoan();
        $sa = $this->user('super_admin');

        $this->actingAs($sa)->get(route('loans.disbursement-data.export'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_import_rewrites_tranche_json_amount_and_status(): void
    {
        [$loan, $owner] = $this->disbursingLoan();
        $sa = $this->user('super_admin');
        $this->assertSame(LoanDetail::STATUS_PARTIAL_DISBURSED, $loan->status);

        $entry = $loan->disbursementEntries()->where('is_active', true)->first();

        // Correct the tranche: amount up to the full 2,000,000, OTC cleared with a date.
        $rows = [[
            'Loan ID' => (string) $loan->id,
            'Entry ID' => (string) $entry->id,
            'Bank' => 'ICICI',
            'Product' => 'Home Loan',
            'Sanctioned Amount' => '2000000',
            // A cheque carries a real OTC handover (a fund transfer auto-skips OTC).
            'Method' => 'cheque',
            'Amount' => '2000000',
            'Cheque No' => '551234',
            'Disbursement Date' => '2026-05-10',
            'OTC Clearance' => 'Yes',
            'OTC Clearance Date' => '2026-05-12',
        ]];

        $result = app(DisbursementDataService::class)->apply($rows, $sa);

        $this->assertSame(1, $result['updated']);
        $this->assertEmpty($result['errors']);

        $entry->refresh();
        $this->assertSame(2000000, $entry->amount);
        $this->assertSame('cleared', $entry->otc_status);
        $this->assertSame('2026-05-12', $entry->otc_handover_date->toDateString());
        $this->assertSame('2026-05-10', $entry->disbursement_date->toDateString());

        $loan->refresh();
        // JSON entries + denormalized amount updated.
        $this->assertSame(2000000, (int) $loan->disbursement->entries[0]['amount']);
        $this->assertSame(2000000, $loan->disbursed_amount);
        // Gross reached target (2,000,000) + OTC cleared → loan completes.
        $this->assertSame(LoanDetail::STATUS_COMPLETED, $loan->status);
    }

    public function test_import_skips_unknown_entry_and_reports(): void
    {
        [$loan] = $this->disbursingLoan();
        $sa = $this->user('super_admin');

        $rows = [[
            'Loan ID' => (string) $loan->id, 'Entry ID' => '999999',
            'Method' => 'fund_transfer', 'Amount' => '500000', 'OTC Clearance' => 'No',
        ]];
        $result = app(DisbursementDataService::class)->apply($rows, $sa);

        $this->assertNotEmpty($result['errors']);
        $this->assertSame(1000000, (int) $loan->fresh()->disbursed_amount); // unchanged
    }

    /** Build a full sheet row for an entry with overrides. */
    private function row(LoanDetail $loan, DisbursementEntry $e, array $over = []): array
    {
        return array_merge([
            'Loan ID' => (string) $loan->id, 'Entry ID' => (string) $e->id,
            // Loan Advisor / Payout User are loan-level; blank = keep (no change).
            'Loan Advisor' => '', 'Payout User' => '',
            'Bank' => $loan->bank->name, 'Product' => 'Home Loan', 'Sanctioned Amount' => (string) $loan->sanctioned_amount,
            'Method' => $e->method, 'Amount' => (string) $e->amount,
            // PF / Admin / Insurance are loan-level (one-time) now.
            'PF Amount' => (string) ($loan->disbursement?->pf_amount ?? 0),
            'Admin Charges' => (string) ($loan->disbursement?->admin_charges ?? 0),
            'Insurance Amount' => (string) ($loan->disbursement?->insurance_amount ?? 0),
            'Cheque Name' => $e->cheque_name, 'Cheque No' => $e->cheque_number,
            'Disbursement Date' => optional($e->disbursement_date)->toDateString(),
            'OTC Clearance' => 'No', 'OTC Clearance Date' => '', 'Payout Run' => '', 'Action' => 'Keep',
        ], $over);
    }

    public function test_preview_shows_before_after_with_changed_flags(): void
    {
        [$loan] = $this->disbursingLoan(); // amount 1,000,000
        $e = $loan->disbursementEntries()->first();
        // Use a cheque so the OTC Yes→cleared transition is valid (NEFT is always skipped).
        $e->update(['method' => 'cheque', 'cheque_name' => 'X', 'cheque_number' => '123', 'otc_status' => 'pending', 'otc_handover_date' => null]);

        $rows = [$this->row($loan, $e, [
            'Method' => 'cheque', 'Cheque Name' => 'X', 'Cheque No' => '123',
            'Amount' => '1500000', 'OTC Clearance' => 'Yes', 'OTC Clearance Date' => '2026-05-01',
        ])];
        $preview = app(DisbursementDataService::class)->preview($rows);

        $pe = $preview['loans'][$loan->id]['preview_entries'][0];
        $byLabel = collect($pe['fields'])->keyBy('label');

        $this->assertTrue($byLabel['Amount']['changed']);
        $this->assertSame('₹ 1,000,000', $byLabel['Amount']['old']);
        $this->assertSame('₹ 1,500,000', $byLabel['Amount']['new']);
        $this->assertTrue($byLabel['OTC']['changed']);      // pending → cleared
        $this->assertFalse($byLabel['Method']['changed']);  // untouched (cheque → cheque)
    }

    public function test_import_updates_charges_and_recomputes_gross_status(): void
    {
        [$loan] = $this->disbursingLoan(); // net 1,000,000, pending
        $sa = $this->user('super_admin');
        $e = $loan->disbursementEntries()->first();

        // Net 1.8M + PF 150k + admin 50k = gross 2.0M (= target), OTC cleared → completed.
        $rows = [$this->row($loan, $e, [
            'Amount' => '1800000', 'PF Amount' => '150000', 'Admin Charges' => '50000', 'Insurance Amount' => '100000',
            'OTC Clearance' => 'Yes', 'OTC Clearance Date' => '2026-05-12',
        ])];
        app(DisbursementDataService::class)->apply($rows, $sa);

        $loan->refresh();
        // One-time charges land on the disbursement header now.
        $this->assertSame(150000, $loan->disbursement->pfTotal());
        $this->assertSame(50000, $loan->disbursement->adminTotal());
        $this->assertSame(100000, $loan->disbursement->insuranceTotal());
        $this->assertSame(2000000, $loan->disbursed_amount);          // gross
        $this->assertSame(LoanDetail::STATUS_COMPLETED, $loan->status);
    }

    public function test_delete_action_removes_a_tranche(): void
    {
        [$loan] = $this->disbursingLoan([[600000, 'pending'], [400000, 'pending']]);
        $sa = $this->user('super_admin');
        $entries = $loan->disbursementEntries()->orderBy('id')->get();

        $rows = [
            $this->row($loan, $entries[0]),                        // keep
            $this->row($loan, $entries[1], ['Action' => 'Delete']), // delete
        ];
        app(DisbursementDataService::class)->apply($rows, $sa);

        $loan->refresh();
        $this->assertSame(1, $loan->disbursementEntries()->where('is_active', true)->count());
        $this->assertSame(600000, $loan->disbursed_amount);
    }

    public function test_delete_blocked_when_tranche_is_payout_finalized(): void
    {
        [$loan] = $this->disbursingLoan([[600000, 'pending'], [400000, 'pending']]);
        $sa = $this->user('super_admin');
        $entries = $loan->disbursementEntries()->orderBy('id')->get();
        // Pretend entry[1] was paid out via the legacy per-loan ledger.
        $payout = LoanPayout::create([
            'loan_id' => $loan->id, 'payout_user_id' => $sa->id, 'basis_amount' => 400000,
            'payout_amount' => 4000, 'net_payout_amount' => 3800, 'source' => 'manual', 'finalized_at' => now(),
        ]);
        $entries[1]->update(['loan_payout_id' => $payout->id]);

        $rows = [
            $this->row($loan, $entries[0]),
            $this->row($loan, $entries[1], ['Action' => 'Delete']),
        ];
        $result = app(DisbursementDataService::class)->apply($rows, $sa);

        $this->assertNotEmpty($result['errors']);
        $this->assertSame(2, $loan->fresh()->disbursementEntries()->where('is_active', true)->count()); // not deleted
    }

    public function test_delete_blocked_when_tranche_is_in_a_finalized_run(): void
    {
        [$loan] = $this->disbursingLoan([[600000, 'pending'], [400000, 'pending']]);
        $sa = $this->user('super_admin');
        $entries = $loan->disbursementEntries()->orderBy('id')->get();
        // Entry[1] was covered by a finalized aggregate payout run (new system of record).
        $run = PayoutRun::create([
            'from_date' => '2026-10-01', 'to_date' => '2026-10-31',
            'status' => PayoutRun::STATUS_FINALIZED, 'finalized_by' => $sa->id, 'finalized_at' => now(), 'created_by' => $sa->id,
        ]);
        $entries[1]->update(['payout_run_id' => $run->id]);

        $rows = [
            $this->row($loan, $entries[0]),
            $this->row($loan, $entries[1], ['Action' => 'Delete']),
        ];
        $result = app(DisbursementDataService::class)->apply($rows, $sa);

        $this->assertNotEmpty($result['errors']);
        $this->assertSame(2, $loan->fresh()->disbursementEntries()->where('is_active', true)->count()); // not deleted
    }

    public function test_import_updates_loan_advisor_and_payout_user(): void
    {
        [$loan] = $this->disbursingLoan();
        $sa = $this->user('super_admin');
        $e = $loan->disbursementEntries()->first();

        $newAdvisor = User::create(['name' => 'Priya Advisor', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $newAdvisor->roles()->sync(Role::where('slug', 'loan_advisor')->pluck('id'));
        $payoutPerson = User::create(['name' => 'Rohan Payout', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);

        $rows = [$this->row($loan, $e, ['Loan Advisor' => 'Priya Advisor', 'Payout User' => 'Rohan Payout'])];
        app(DisbursementDataService::class)->apply($rows, $sa);

        $loan->refresh();
        $this->assertSame($newAdvisor->id, $loan->assigned_advisor);
        $this->assertSame($payoutPerson->id, $loan->payout_user_id);
    }

    public function test_neft_import_sets_transfer_date_and_handover_from_the_column(): void
    {
        [$loan] = $this->disbursingLoan(); // NEFT entry
        $sa = $this->user('super_admin');
        $e = $loan->disbursementEntries()->first();

        $rows = [$this->row($loan, $e, ['Transfer Date' => '15-03-2026'])];
        app(DisbursementDataService::class)->apply($rows, $sa);

        $e->refresh();
        $this->assertSame('2026-03-15', optional($e->transfer_date)->toDateString());
        $this->assertSame('skipped', $e->otc_status);
        // NEFT settlement date = transfer date.
        $this->assertSame('2026-03-15', optional($e->otc_handover_date)->toDateString());
    }

    public function test_neft_import_defaults_transfer_date_to_disbursement_date(): void
    {
        [$loan] = $this->disbursingLoan();
        $sa = $this->user('super_admin');
        $e = $loan->disbursementEntries()->first();
        $disb = optional($e->disbursement_date)->toDateString();

        $rows = [$this->row($loan, $e)]; // no Transfer Date column value
        app(DisbursementDataService::class)->apply($rows, $sa);

        $e->refresh();
        $this->assertSame($disb, optional($e->transfer_date)->toDateString());
        $this->assertSame($disb, optional($e->otc_handover_date)->toDateString());
    }

    public function test_payout_user_resolving_to_an_ineligible_role_is_rejected(): void
    {
        [$loan] = $this->disbursingLoan();
        $sa = $this->user('super_admin');
        $e = $loan->disbursementEntries()->first();

        Role::firstOrCreate(['slug' => 'bank_employee'], ['name' => 'Bank Employee']);
        $bankEmp = User::create(['name' => 'Bank Person', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $bankEmp->roles()->sync(Role::where('slug', 'bank_employee')->pluck('id'));

        $rows = [$this->row($loan, $e, ['Payout User' => 'Bank Person'])];
        $result = app(DisbursementDataService::class)->apply($rows, $sa);

        $this->assertNotEmpty($result['errors']);
        $this->assertNull($loan->fresh()->payout_user_id); // not assigned
    }

    public function test_unknown_advisor_name_is_kept_with_error(): void
    {
        [$loan, $owner] = $this->disbursingLoan();
        $sa = $this->user('super_admin');
        $e = $loan->disbursementEntries()->first();

        $rows = [$this->row($loan, $e, ['Loan Advisor' => 'Nobody McMissing'])];
        $result = app(DisbursementDataService::class)->apply($rows, $sa);

        $this->assertNotEmpty($result['errors']);
        $this->assertSame($owner->id, $loan->fresh()->assigned_advisor); // unchanged
    }

    public function test_completed_loan_downgrades_when_corrected_below_target(): void
    {
        // Fully disbursed + OTC skipped → completed (gross 2M = target).
        [$loan] = $this->disbursingLoan([[2000000, 'skipped']]);
        $this->assertSame(LoanDetail::STATUS_COMPLETED, $loan->status);
        $sa = $this->user('super_admin');
        $e = $loan->disbursementEntries()->first();

        // Correct the amount down to 1M → gross < target → must downgrade.
        $rows = [$this->row($loan, $e, ['Amount' => '1000000', 'OTC Clearance' => 'Skip'])];
        app(DisbursementDataService::class)->apply($rows, $sa);

        $loan->refresh();
        $this->assertSame(LoanDetail::STATUS_PARTIAL_DISBURSED, $loan->status);
        $this->assertSame('in_progress', $loan->stageAssignments()->where('stage_key', 'disbursement')->value('status'));
    }
}
