<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\DisbursementDetail;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stage;
use App\Models\StageAssignment;
use App\Models\User;
use App\Services\DisbursementService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Multi-tranche disbursement with per-entry OTC. Each tranche (cheque OR NEFT)
 * carries its own OTC handover (pending | cleared | skipped). The loan is
 * monotonic: active → partial_disbursed (first entry) → completed (fully
 * disbursed AND every entry OTC-settled). Over-disbursement is allowed.
 */
class DisbursementMultiEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $this->seedMainStages();
        $this->mock(NotificationService::class)->shouldIgnoreMissing();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function seedMainStages(): void
    {
        $sequence = [
            'inquiry', 'document_selection', 'document_collection',
            'parallel_processing', 'sanction_decision', 'rate_pf', 'sanction',
            'docket', 'kfs', 'esign', 'disbursement', 'otc_clearance',
        ];

        foreach ($sequence as $i => $key) {
            Stage::firstOrCreate(
                ['stage_key' => $key],
                [
                    'stage_name_en' => ucwords(str_replace('_', ' ', $key)),
                    'stage_name_gu' => $key,
                    'sequence_order' => $i + 1,
                    'is_parallel' => false,
                    'parent_stage_key' => null,
                    'stage_type' => 'sequential',
                    'is_enabled' => true,
                ]
            );
        }
    }

    private function admin(): User
    {
        $user = User::create([
            'name' => 'Admin '.uniqid(),
            'email' => uniqid().'@test',
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);
        $user->roles()->sync(Role::where('slug', 'super_admin')->pluck('id'));

        return $user->fresh('roles');
    }

    /**
     * Loan at the disbursement stage with an open disbursement assignment,
     * pending OTC assignment, loan_progress row, and a product on its bank.
     *
     * @return array{0: LoanDetail, 1: Product}
     */
    private function makeLoanAtDisbursement(User $owner, array $attrs = []): array
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'Home Loan '.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        $loan = LoanDetail::create(array_merge([
            'loan_number' => 'L-'.uniqid(),
            'customer_name' => 'Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 1000000,
            'sanctioned_amount' => 2000000,
            'status' => 'active',
            'current_stage' => 'disbursement',
            'bank_id' => $bank->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'created_by' => $owner->id,
            'assigned_advisor' => $owner->id,
        ], $attrs));

        StageAssignment::create([
            'loan_id' => $loan->id, 'stage_key' => 'disbursement',
            'assigned_to' => $owner->id, 'status' => 'in_progress', 'is_parallel_stage' => false,
        ]);
        StageAssignment::create([
            'loan_id' => $loan->id, 'stage_key' => 'otc_clearance',
            'assigned_to' => $owner->id, 'status' => 'pending', 'is_parallel_stage' => false,
        ]);

        return [$loan, $product];
    }

    /**
     * Build an entry payload with explicit method + per-entry OTC state.
     */
    private function entry(Product $product, int $amount, string $method = 'fund_transfer', string $otc = 'pending', ?string $handover = null): array
    {
        $e = [
            'disbursement_date' => now()->format('d/m/Y'),
            'method' => $method,
            'product_id' => (string) $product->id,
            'loan_account_number' => 'LA-123456',
            'amount' => (string) $amount,
            'otc_status' => $otc,
        ];

        if ($otc === 'cleared') {
            $e['otc_handover_date'] = $handover ?? now()->format('d/m/Y');
        }

        if ($method === 'cheque') {
            $e['cheque_name'] = 'CUSTOMER NAME';
            $e['cheque_number'] = '000123';
            $e['cheque_date'] = now()->format('d/m/Y');
        }

        return $e;
    }

    public function test_partial_save_marks_loan_partial_disbursed_and_keeps_stage_open(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        $this->actingAs($admin)
            ->post(route('loans.disbursement.store', $loan), [
                'entries' => [$this->entry($product, 800000)],
            ])
            ->assertRedirect(route('loans.disbursement', $loan));

        $loan->refresh();
        $this->assertSame('in_progress', $loan->stageAssignments()->where('stage_key', 'disbursement')->value('status'));
        $this->assertSame(LoanDetail::STATUS_PARTIAL_DISBURSED, $loan->status);
        $this->assertSame(800000, $loan->disbursed_amount);
        $this->assertCount(1, $loan->disbursement->entries);
    }

    public function test_reaching_target_with_pending_otc_stays_partial(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        $this->actingAs($admin)
            ->post(route('loans.disbursement.store', $loan), [
                'entries' => [$this->entry($product, 1200000), $this->entry($product, 800000)],
            ])
            ->assertRedirect(route('loans.disbursement', $loan));

        $loan->refresh();
        $this->assertSame('completed', $loan->stageAssignments()->where('stage_key', 'disbursement')->value('status'));
        $this->assertSame('in_progress', $loan->stageAssignments()->where('stage_key', 'otc_clearance')->value('status'));
        $this->assertSame(LoanDetail::STATUS_PARTIAL_DISBURSED, $loan->status);
    }

    public function test_all_settled_neft_completes_loan(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        $this->actingAs($admin)
            ->post(route('loans.disbursement.store', $loan), [
                'entries' => [
                    $this->entry($product, 1200000, 'fund_transfer', 'skipped'),
                    $this->entry($product, 800000, 'fund_transfer', 'skipped'),
                ],
            ])
            ->assertRedirect(route('loans.show', $loan));

        $loan->refresh();
        $this->assertSame('completed', $loan->stageAssignments()->where('stage_key', 'disbursement')->value('status'));
        $this->assertSame('completed', $loan->stageAssignments()->where('stage_key', 'otc_clearance')->value('status'));
        $this->assertSame(LoanDetail::STATUS_COMPLETED, $loan->status);
        $this->assertSame(2000000, $loan->disbursed_amount);
    }

    public function test_mixed_cheque_and_neft_single_save_with_inline_otc_completes(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        $this->actingAs($admin)
            ->post(route('loans.disbursement.store', $loan), [
                'entries' => [
                    $this->entry($product, 1200000, 'cheque', 'cleared'),
                    $this->entry($product, 800000, 'fund_transfer', 'skipped'),
                ],
            ])
            ->assertRedirect(route('loans.show', $loan));

        $loan->refresh();
        $this->assertSame(LoanDetail::STATUS_COMPLETED, $loan->status);

        $rows = $loan->disbursementEntries()->get();
        $this->assertSame('cleared', $rows->firstWhere('method', 'cheque')->otc_status);
        $this->assertSame('skipped', $rows->firstWhere('method', 'fund_transfer')->otc_status);
    }

    public function test_per_entry_otc_endpoint_settles_last_entry_and_completes(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        // Cheque reaching target but OTC pending → partial.
        $this->actingAs($admin)->post(route('loans.disbursement.store', $loan), [
            'entries' => [$this->entry($product, 2000000, 'cheque', 'pending')],
        ]);
        $loan->refresh();
        $this->assertSame(LoanDetail::STATUS_PARTIAL_DISBURSED, $loan->status);

        $entryId = $loan->disbursementEntries()->value('id');

        $this->actingAs($admin)
            ->from(route('loans.disbursement', $loan))
            ->post(route('loans.disbursement.entry.otc', ['loan' => $loan, 'entry' => $entryId]), [
                'otc_status' => 'cleared',
                'otc_handover_date' => now()->format('d/m/Y'),
            ])
            ->assertRedirect(route('loans.disbursement', $loan));

        $loan->refresh();
        $this->assertSame(LoanDetail::STATUS_COMPLETED, $loan->status);
        $this->assertSame('cleared', $loan->disbursementEntries()->value('otc_status'));
    }

    public function test_mark_fully_disbursed_below_target_then_skip_otc_completes(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        $this->actingAs($admin)->post(route('loans.disbursement.store', $loan), [
            'entries' => [$this->entry($product, 800000)],
        ]);

        // Mark full (below target): money done, but OTC still pending → partial.
        $this->actingAs($admin)
            ->post(route('loans.disbursement.complete', $loan))
            ->assertRedirect(route('loans.disbursement', $loan));
        $loan->refresh();
        $this->assertSame(LoanDetail::STATUS_PARTIAL_DISBURSED, $loan->status);
        $this->assertSame('full', $loan->disbursement->completion_intent);

        $entryId = $loan->disbursementEntries()->value('id');
        $this->actingAs($admin)->post(route('loans.disbursement.entry.otc', ['loan' => $loan, 'entry' => $entryId]), [
            'otc_status' => 'skipped',
        ]);

        $this->assertSame(LoanDetail::STATUS_COMPLETED, $loan->fresh()->status);
    }

    public function test_over_disbursement_is_allowed_and_flagged(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        $this->actingAs($admin)->post(route('loans.disbursement.store', $loan), [
            'entries' => [
                $this->entry($product, 1500000, 'fund_transfer', 'skipped'),
                $this->entry($product, 1000000, 'fund_transfer', 'skipped'),
            ],
        ]);

        $loan->refresh();
        $this->assertSame(LoanDetail::STATUS_COMPLETED, $loan->status);
        $this->assertSame(2500000, $loan->disbursed_amount);
        $this->assertTrue($loan->isOverDisbursed());
    }

    public function test_store_rejected_when_loan_completed(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);
        $loan->update(['status' => LoanDetail::STATUS_COMPLETED]);

        $this->actingAs($admin)
            ->post(route('loans.disbursement.store', $loan), [
                'entries' => [$this->entry($product, 500000)],
            ])
            ->assertRedirect(route('loans.stages', $loan))
            ->assertSessionHas('error');

        $this->assertNull($loan->fresh()->disbursement);
    }

    public function test_completed_loan_sync_is_a_noop_guard(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        // Complete via all-settled NEFT.
        $this->actingAs($admin)->post(route('loans.disbursement.store', $loan), [
            'entries' => [$this->entry($product, 2000000, 'fund_transfer', 'skipped')],
        ]);
        $this->assertSame(LoanDetail::STATUS_COMPLETED, $loan->fresh()->status);

        // Re-running the resolver must never downgrade a completed loan.
        app(DisbursementService::class)->syncDisbursementState($loan->fresh());
        $this->assertSame(LoanDetail::STATUS_COMPLETED, $loan->fresh()->status);
    }

    public function test_product_from_another_bank_is_rejected(): void
    {
        $admin = $this->admin();
        [$loan] = $this->makeLoanAtDisbursement($admin);

        $otherBank = Bank::create(['name' => 'Other-'.uniqid(), 'is_active' => true]);
        $foreignProduct = Product::create(['name' => 'Foreign', 'bank_id' => $otherBank->id, 'is_active' => true]);

        $this->actingAs($admin)
            ->from(route('loans.disbursement', $loan))
            ->post(route('loans.disbursement.store', $loan), [
                'entries' => [$this->entry($foreignProduct, 500000)],
            ])
            ->assertSessionHasErrors('entries.0.product_id');

        $this->assertNull($loan->fresh()->disbursement);
    }

    public function test_cheque_entry_requires_instrument_fields(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        $cheque = $this->entry($product, 500000);
        $cheque['method'] = 'cheque';

        $this->actingAs($admin)
            ->from(route('loans.disbursement', $loan))
            ->post(route('loans.disbursement.store', $loan), ['entries' => [$cheque]])
            ->assertSessionHasErrors(['entries.0.cheque_name', 'entries.0.cheque_number', 'entries.0.cheque_date']);

        $this->assertNull($loan->fresh()->disbursement);
    }

    public function test_cleared_otc_requires_handover_date(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        $entry = $this->entry($product, 500000, 'fund_transfer', 'cleared');
        unset($entry['otc_handover_date']);

        $this->actingAs($admin)
            ->from(route('loans.disbursement', $loan))
            ->post(route('loans.disbursement.store', $loan), ['entries' => [$entry]])
            ->assertSessionHasErrors('entries.0.otc_handover_date');
    }

    public function test_entry_list_falls_back_to_legacy_columns(): void
    {
        $admin = $this->admin();
        [$loan] = $this->makeLoanAtDisbursement($admin);

        $legacy = DisbursementDetail::create([
            'loan_id' => $loan->id,
            'disbursement_type' => 'cheque',
            'disbursement_date' => now()->toDateString(),
            'amount_disbursed' => 900000,
            'bank_account_number' => 'OLD-ACC',
            'cheques' => [
                ['cheque_name' => 'A', 'cheque_number' => '1', 'cheque_date' => '01/07/2026', 'cheque_amount' => 400000],
                ['cheque_name' => 'B', 'cheque_number' => '2', 'cheque_date' => '02/07/2026', 'cheque_amount' => 500000],
            ],
        ]);

        $entries = $legacy->entryList();
        $this->assertCount(2, $entries);
        $this->assertSame('cheque', $entries[0]['method']);
        $this->assertSame('OLD-ACC', $entries[0]['loan_account_number']);
        $this->assertSame(900000, $legacy->entryTotal());
        $this->assertTrue($legacy->hasChequeEntries());
    }
}
