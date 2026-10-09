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
use App\Models\StageQuery;
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

        // Cheques carry a real OTC handover — the loan stays partial until cleared.
        $this->actingAs($admin)
            ->post(route('loans.disbursement.store', $loan), [
                'entries' => [
                    $this->entry($product, 1200000, 'cheque', 'pending'),
                    $this->entry($product, 800000, 'cheque', 'pending'),
                ],
            ])
            ->assertRedirect(route('loans.disbursement', $loan));

        $loan->refresh();
        $this->assertSame('completed', $loan->stageAssignments()->where('stage_key', 'disbursement')->value('status'));
        $this->assertSame('in_progress', $loan->stageAssignments()->where('stage_key', 'otc_clearance')->value('status'));
        $this->assertSame(LoanDetail::STATUS_PARTIAL_DISBURSED, $loan->status);
    }

    public function test_neft_entry_does_not_require_otc_and_does_not_block_completion(): void
    {
        // A fund transfer has no physical instrument — its OTC auto-settles ("skipped")
        // and never blocks the loan. Clearing the cheque alone must complete a
        // cheque+NEFT fully-disbursed loan, even when the NEFT was posted as "pending".
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin); // target 2,000,000

        $this->actingAs($admin)->post(route('loans.disbursement.store', $loan), [
            'entries' => [
                $this->entry($product, 1000000, 'cheque', 'pending'),
                $this->entry($product, 1000000, 'fund_transfer', 'pending'),
            ],
        ]);

        $loan->refresh();
        $neft = $loan->disbursementEntries()->where('method', 'fund_transfer')->first();
        $this->assertSame('skipped', $neft->otc_status, 'NEFT OTC must auto-settle');
        $this->assertTrue($neft->isOtcSettled());
        $this->assertSame(LoanDetail::STATUS_PARTIAL_DISBURSED, $loan->status); // cheque still pending

        // Clearing the cheque alone completes the loan — the NEFT does not block it.
        $cheque = $loan->disbursementEntries()->where('method', 'cheque')->first();
        $this->actingAs($admin)->post(route('loans.disbursement.entry.otc', ['loan' => $loan, 'entry' => $cheque->id]), [
            'otc_status' => 'cleared', 'otc_handover_date' => now()->format('d/m/Y'),
        ]);
        $this->assertSame(LoanDetail::STATUS_COMPLETED, $loan->fresh()->status);
    }

    public function test_neft_transfer_date_defaults_to_the_entry_date(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        // No transfer_date sent → defaults to the entry's disbursement date, OTC skipped.
        $this->actingAs($admin)->post(route('loans.disbursement.store', $loan), [
            'entries' => [$this->entry($product, 500000, 'fund_transfer')],
        ])->assertRedirect();

        $row = $loan->disbursementEntries()->where('method', 'fund_transfer')->first();
        $this->assertSame(now()->toDateString(), optional($row->transfer_date)->toDateString());
        $this->assertSame('skipped', $row->otc_status);
        // NEFT settlement date = transfer date (drives payout + reports).
        $this->assertSame(now()->toDateString(), optional($row->otc_handover_date)->toDateString());
    }

    public function test_neft_explicit_transfer_date_is_stored(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);
        $e = $this->entry($product, 500000, 'fund_transfer');
        $e['transfer_date'] = '15/03/2026';

        $this->actingAs($admin)->post(route('loans.disbursement.store', $loan), ['entries' => [$e]])->assertRedirect();

        $row = $loan->disbursementEntries()->where('method', 'fund_transfer')->first();
        $this->assertSame('2026-03-15', optional($row->transfer_date)->toDateString());
    }

    public function test_cheque_entry_has_no_transfer_date(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);
        $e = $this->entry($product, 500000, 'cheque', 'cleared');
        $e['transfer_date'] = '15/03/2026'; // posted, but ignored for cheques

        $this->actingAs($admin)->post(route('loans.disbursement.store', $loan), ['entries' => [$e]])->assertRedirect();

        $row = $loan->disbursementEntries()->where('method', 'cheque')->first();
        $this->assertNull($row->transfer_date);
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

        // A cheque carries a real OTC handover (NEFT would auto-settle and complete).
        $this->actingAs($admin)->post(route('loans.disbursement.store', $loan), [
            'entries' => [$this->entry($product, 800000, 'cheque', 'pending')],
        ]);

        // Mark full (below target): money done, but cheque OTC still pending → partial.
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

    public function test_pf_and_admin_charges_add_to_gross_and_complete_at_target(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin); // sanctioned 2,000,000

        // Net 1,800,000 + PF 150,000 + admin 50,000 = gross 2,000,000 (= target).
        // Insurance 100,000 is recorded but excluded from the gross. PF / Admin /
        // Insurance are ONE-TIME (loan-level) — posted as top-level fields.
        $entry = $this->entry($product, 1800000, 'fund_transfer', 'skipped');

        $this->actingAs($admin)->post(route('loans.disbursement.store', $loan), [
            'entries' => [$entry],
            'pf_amount' => '150000',
            'admin_charges' => '50000',
            'insurance_amount' => '100000',
        ])->assertRedirect(route('loans.show', $loan));

        $loan->refresh();
        $this->assertSame(LoanDetail::STATUS_COMPLETED, $loan->status);
        // disbursed_amount mirrors the GROSS (net + PF + admin), not the net transfer.
        $this->assertSame(2000000, $loan->disbursed_amount);

        $disb = $loan->disbursement;
        $this->assertSame(2000000, $disb->grossTotal());
        $this->assertSame(1800000, $disb->entryTotal());
        $this->assertSame(150000, $disb->pfTotal());
        $this->assertSame(50000, $disb->adminTotal());
        $this->assertSame(100000, $disb->insuranceTotal());

        $row = $loan->disbursementEntries()->first();
        $this->assertSame(1800000, $row->amount);
    }

    public function test_insurance_is_excluded_from_gross_and_keeps_loan_partial(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin); // sanctioned 2,000,000

        // Net 1,800,000 + insurance 500,000, no PF/admin → gross 1,800,000 < target.
        $entry = $this->entry($product, 1800000, 'fund_transfer', 'skipped');

        $this->actingAs($admin)->post(route('loans.disbursement.store', $loan), [
            'entries' => [$entry],
            'insurance_amount' => '500000',
        ]);

        $loan->refresh();
        $this->assertSame(LoanDetail::STATUS_PARTIAL_DISBURSED, $loan->status);
        $this->assertSame(1800000, $loan->disbursed_amount);     // insurance excluded
        $this->assertSame(500000, $loan->disbursement->insuranceTotal());
    }

    public function test_pending_cheque_does_not_lock_charges(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        app(DisbursementService::class)->processDisbursement($loan->fresh(), [
            'entries' => [$this->entry($product, 800000, 'cheque', 'pending')],
            'pf_amount' => '150000', 'admin_charges' => '50000', 'insurance_amount' => '100000',
        ]);

        // A cheque whose OTC is still pending is not settled → charges stay editable.
        $this->assertFalse($loan->fresh()->disbursement->chargesLocked());
    }

    public function test_charges_lock_after_otc_settled_and_are_retained_on_resave(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin); // sanctioned 2,000,000
        $service = app(DisbursementService::class);

        // First disbursement: a NEFT tranche (auto-skipped = OTC settled) + one-time charges.
        $service->processDisbursement($loan->fresh(), [
            'entries' => [$this->entry($product, 800000, 'fund_transfer', 'skipped')],
            'pf_amount' => '150000', 'admin_charges' => '50000', 'insurance_amount' => '100000',
        ]);
        $this->assertTrue($loan->fresh()->disbursement->chargesLocked());

        // Re-save adding a tranche with DIFFERENT charges — they must be ignored.
        $service->processDisbursement($loan->fresh(), [
            'entries' => [
                $this->entry($product, 800000, 'fund_transfer', 'skipped'),
                $this->entry($product, 250000, 'fund_transfer', 'skipped'),
            ],
            'pf_amount' => '999999', 'admin_charges' => '888888', 'insurance_amount' => '777777',
        ]);
        $disb = $loan->fresh()->disbursement;
        $this->assertSame(150000, $disb->pfTotal());        // retained
        $this->assertSame(50000, $disb->adminTotal());      // retained
        $this->assertSame(100000, $disb->insuranceTotal()); // retained

        // Super-admin correction path ($allowReopen) bypasses the lock.
        $service->processDisbursement($loan->fresh(), [
            'entries' => [
                $this->entry($product, 800000, 'fund_transfer', 'skipped'),
                $this->entry($product, 250000, 'fund_transfer', 'skipped'),
            ],
            'pf_amount' => '111111', 'admin_charges' => '22222', 'insurance_amount' => '33333',
        ], allowReopen: true);
        $disb = $loan->fresh()->disbursement;
        $this->assertSame(111111, $disb->pfTotal());
        $this->assertSame(22222, $disb->adminTotal());
        $this->assertSame(33333, $disb->insuranceTotal());
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

    public function test_open_query_on_disbursement_blocks_loan_completion(): void
    {
        // A full disbursement (money done + all OTC settled) must NOT complete the
        // loan while an open query is blocking the disbursement stage — otherwise the
        // loan would be marked completed with the disbursement stage still in_progress.
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin); // sanctioned 2,000,000

        $disb = $loan->stageAssignments()->where('stage_key', 'disbursement')->first();
        StageQuery::create([
            'stage_assignment_id' => $disb->id, 'loan_id' => $loan->id, 'stage_key' => 'disbursement',
            'query_text' => 'Hold disbursement', 'raised_by' => $admin->id,
            'assigned_to_user_id' => $admin->id, 'status' => 'pending',
        ]);

        // Full NEFT disbursement (gross 2,000,000 = target, OTC skipped) with the query open.
        app(DisbursementService::class)->processDisbursement($loan, [
            'entries' => [$this->entry($product, 2000000, 'fund_transfer', 'skipped')],
        ]);

        $loan->refresh();
        $this->assertSame(LoanDetail::STATUS_PARTIAL_DISBURSED, $loan->status, 'loan must not complete while a query blocks disbursement');
        $this->assertSame('in_progress', $loan->stageAssignments()->where('stage_key', 'disbursement')->first()->status);
        $this->assertNotSame('completed', $loan->stageAssignments()->where('stage_key', 'otc_clearance')->first()->status);

        // Resolving the query and re-syncing completes the loan.
        StageQuery::where('loan_id', $loan->id)->update(['status' => 'resolved']);
        app(DisbursementService::class)->syncDisbursementState($loan->fresh());
        $this->assertSame(LoanDetail::STATUS_COMPLETED, $loan->fresh()->status);
    }
}
