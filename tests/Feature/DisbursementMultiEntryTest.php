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
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Multi-entry (tranche) disbursement: entries carry per-tranche date, method,
 * product (of the loan's bank), loan account number and amount. The stage
 * auto-completes when the entry total reaches the sanctioned amount; any
 * cheque entry routes to OTC, all-NEFT completes the loan.
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
     * pending OTC assignment, and a product on its bank.
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

    private function neftEntry(Product $product, int $amount): array
    {
        return [
            'disbursement_date' => now()->format('d/m/Y'),
            'method' => 'fund_transfer',
            'product_id' => (string) $product->id,
            'loan_account_number' => 'LA-123456',
            'amount' => (string) $amount,
        ];
    }

    public function test_partial_save_keeps_stage_open_and_mirrors_total(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        $this->actingAs($admin)
            ->post(route('loans.disbursement.store', $loan), [
                'entries' => [$this->neftEntry($product, 800000)],
            ])
            ->assertRedirect(route('loans.disbursement', $loan));

        $loan->refresh();
        $this->assertSame('in_progress', $loan->stageAssignments()->where('stage_key', 'disbursement')->value('status'));
        $this->assertSame(800000, $loan->disbursed_amount);
        $this->assertSame('active', $loan->status);

        $entries = $loan->disbursement->entries;
        $this->assertCount(1, $entries);
        $this->assertSame($product->name, $entries[0]['product_name']);
        $this->assertSame('LA-123456', $entries[0]['loan_account_number']);
    }

    public function test_auto_completes_at_sanctioned_amount_and_neft_only_completes_loan(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        $this->actingAs($admin)
            ->post(route('loans.disbursement.store', $loan), [
                'entries' => [
                    $this->neftEntry($product, 1200000),
                    $this->neftEntry($product, 800000),
                ],
            ])
            ->assertRedirect(route('loans.show', $loan));

        $loan->refresh();
        $this->assertSame('completed', $loan->stageAssignments()->where('stage_key', 'disbursement')->value('status'));
        $this->assertSame('skipped', $loan->stageAssignments()->where('stage_key', 'otc_clearance')->value('status'));
        $this->assertSame(LoanDetail::STATUS_COMPLETED, $loan->status);
        $this->assertSame(2000000, $loan->disbursed_amount);
        $this->assertSame('fund_transfer', $loan->disbursement->disbursement_type);
    }

    public function test_cheque_entry_routes_to_otc_on_completion(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        $cheque = $this->neftEntry($product, 800000);
        $cheque['method'] = 'cheque';
        $cheque['cheque_name'] = 'CUSTOMER NAME';
        $cheque['cheque_number'] = '000123';
        $cheque['cheque_date'] = now()->format('d/m/Y');

        $this->actingAs($admin)
            ->post(route('loans.disbursement.store', $loan), [
                'entries' => [$this->neftEntry($product, 1200000), $cheque],
            ])
            ->assertRedirect(route('loans.show', $loan));

        $loan->refresh();
        $this->assertSame('completed', $loan->stageAssignments()->where('stage_key', 'disbursement')->value('status'));
        $this->assertSame('in_progress', $loan->stageAssignments()->where('stage_key', 'otc_clearance')->value('status'));
        $this->assertSame('active', $loan->status);
        $this->assertSame('cheque', $loan->disbursement->disbursement_type);
        $this->assertTrue($loan->disbursement->hasChequeEntries());
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
                'entries' => [$this->neftEntry($foreignProduct, 500000)],
            ])
            ->assertSessionHasErrors('entries.0.product_id');

        $this->assertNull($loan->fresh()->disbursement);
    }

    public function test_cheque_entry_requires_instrument_fields(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        $cheque = $this->neftEntry($product, 500000);
        $cheque['method'] = 'cheque';

        $this->actingAs($admin)
            ->from(route('loans.disbursement', $loan))
            ->post(route('loans.disbursement.store', $loan), ['entries' => [$cheque]])
            ->assertSessionHasErrors(['entries.0.cheque_name', 'entries.0.cheque_number', 'entries.0.cheque_date']);

        $this->assertNull($loan->fresh()->disbursement);
    }

    public function test_mark_fully_disbursed_completes_below_target(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        $this->actingAs($admin)->post(route('loans.disbursement.store', $loan), [
            'entries' => [$this->neftEntry($product, 800000)],
        ]);

        $this->actingAs($admin)
            ->post(route('loans.disbursement.complete', $loan))
            ->assertRedirect(route('loans.show', $loan));

        $loan->refresh();
        $this->assertSame('completed', $loan->stageAssignments()->where('stage_key', 'disbursement')->value('status'));
        $this->assertSame(LoanDetail::STATUS_COMPLETED, $loan->status);
        $this->assertSame(800000, $loan->disbursed_amount);
    }

    public function test_store_is_rejected_after_stage_completion(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);
        $loan->stageAssignments()->where('stage_key', 'disbursement')->update(['status' => 'completed']);

        $this->actingAs($admin)
            ->post(route('loans.disbursement.store', $loan), [
                'entries' => [$this->neftEntry($product, 500000)],
            ])
            ->assertRedirect(route('loans.stages', $loan))
            ->assertSessionHas('error');

        $this->assertNull($loan->fresh()->disbursement);
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
