<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\DisbursementEntry;
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
 * `disbursement_entries` mirrors the `disbursement_details.entries` json:
 * rows update in place via row_id, removed entries soft-delete with
 * deleted_by/deleted_at, and is_active follows the loan status
 * (cancelled/rejected/on_hold → 0, active/completed → 1).
 */
class DisbursementEntrySyncTest extends TestCase
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

    /** @return array{0: LoanDetail, 1: Product} */
    private function makeLoanAtDisbursement(User $owner): array
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'Home Loan '.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        $loan = LoanDetail::create([
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
        ]);

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

    private function entry(Product $product, int $amount, array $extra = []): array
    {
        return array_merge([
            'disbursement_date' => now()->format('d/m/Y'),
            'method' => 'fund_transfer',
            'product_id' => (string) $product->id,
            'loan_account_number' => 'LA-123456',
            'amount' => (string) $amount,
        ], $extra);
    }

    public function test_first_save_creates_mirror_rows_and_stores_row_ids_in_json(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        $cheque = $this->entry($product, 300000, [
            'method' => 'cheque', 'cheque_name' => 'NAME', 'cheque_number' => '007', 'cheque_date' => '01/07/2026',
        ]);

        $this->actingAs($admin)->post(route('loans.disbursement.store', $loan), [
            'entries' => [$this->entry($product, 500000), $cheque],
        ]);

        $rows = DisbursementEntry::where('loan_id', $loan->id)->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertTrue($rows->every(fn ($r) => $r->is_active));
        $this->assertSame('007', $rows[1]->cheque_number);
        $this->assertSame($product->name, $rows[0]->product_name);

        $jsonEntries = $loan->disbursement->fresh()->entries;
        $this->assertSame($rows[0]->id, $jsonEntries[0]['row_id']);
        $this->assertSame($rows[1]->id, $jsonEntries[1]['row_id']);
    }

    public function test_edit_updates_in_place_soft_deletes_removed_and_creates_new(): void
    {
        $admin = $this->admin();
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        $this->actingAs($admin)->post(route('loans.disbursement.store', $loan), [
            'entries' => [$this->entry($product, 500000), $this->entry($product, 300000)],
        ]);

        [$keepId, $removeId] = DisbursementEntry::where('loan_id', $loan->id)->orderBy('id')->pluck('id');

        // Edit: keep first (changed amount + account), drop second, add a new one.
        $this->actingAs($admin)->post(route('loans.disbursement.store', $loan), [
            'entries' => [
                $this->entry($product, 600000, ['row_id' => $keepId, 'loan_account_number' => 'LA-CHANGED']),
                $this->entry($product, 200000),
            ],
        ]);

        $kept = DisbursementEntry::find($keepId);
        $this->assertSame(600000, $kept->amount);
        $this->assertSame('LA-CHANGED', $kept->loan_account_number);
        $this->assertSame($admin->id, $kept->updated_by);

        $removed = DisbursementEntry::withTrashed()->find($removeId);
        $this->assertNotNull($removed->deleted_at);
        $this->assertSame($admin->id, $removed->deleted_by);

        $live = DisbursementEntry::where('loan_id', $loan->id)->get();
        $this->assertCount(2, $live);

        $jsonEntries = $loan->disbursement->fresh()->entries;
        $this->assertSame($keepId, $jsonEntries[0]['row_id']);
        $this->assertNotContains($removeId, array_column($jsonEntries, 'row_id'));
    }

    public function test_foreign_row_id_is_treated_as_new_not_hijacked(): void
    {
        $admin = $this->admin();
        [$loanA, $productA] = $this->makeLoanAtDisbursement($admin);
        [$loanB, $productB] = $this->makeLoanAtDisbursement($admin);

        $this->actingAs($admin)->post(route('loans.disbursement.store', $loanB), [
            'entries' => [$this->entry($productB, 400000)],
        ]);
        $foreignId = DisbursementEntry::where('loan_id', $loanB->id)->value('id');

        $this->actingAs($admin)->post(route('loans.disbursement.store', $loanA), [
            'entries' => [$this->entry($productA, 500000, ['row_id' => $foreignId])],
        ]);

        // Loan B's row is untouched; loan A got its own new row.
        $this->assertSame(400000, DisbursementEntry::find($foreignId)->amount);
        $this->assertSame($loanB->id, DisbursementEntry::find($foreignId)->loan_id);
        $aRow = DisbursementEntry::where('loan_id', $loanA->id)->firstOrFail();
        $this->assertNotSame($foreignId, $aRow->id);
        $this->assertSame(500000, $aRow->amount);
    }

    public function test_loan_status_changes_toggle_is_active(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        $this->post(route('loans.disbursement.store', $loan), [
            'entries' => [$this->entry($product, 500000), $this->entry($product, 300000)],
        ]);

        $active = fn () => DisbursementEntry::where('loan_id', $loan->id)->where('is_active', true)->count();
        $this->assertSame(2, $active());

        foreach (['cancelled', 'rejected', 'on_hold'] as $status) {
            $loan->refresh()->update(['status' => $status]);
            $this->assertSame(0, $active(), "entries should deactivate when loan is {$status}");

            $loan->refresh()->update(['status' => 'active']);
            $this->assertSame(2, $active(), "entries should reactivate after {$status} → active");
        }
    }

    public function test_soft_deleted_rows_stay_deleted_through_status_toggles(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        [$loan, $product] = $this->makeLoanAtDisbursement($admin);

        $this->post(route('loans.disbursement.store', $loan), [
            'entries' => [$this->entry($product, 500000), $this->entry($product, 300000)],
        ]);
        $removeId = DisbursementEntry::where('loan_id', $loan->id)->orderByDesc('id')->value('id');
        $keepId = DisbursementEntry::where('loan_id', $loan->id)->orderBy('id')->value('id');

        // Drop the second entry, then bounce the loan status.
        $this->post(route('loans.disbursement.store', $loan), [
            'entries' => [$this->entry($product, 500000, ['row_id' => $keepId])],
        ]);
        $loan->refresh()->update(['status' => 'cancelled']);
        $loan->refresh()->update(['status' => 'active']);

        $removed = DisbursementEntry::withTrashed()->find($removeId);
        $this->assertNotNull($removed->deleted_at, 'soft-deleted row must not be revived by status toggles');
        $this->assertTrue(DisbursementEntry::find($keepId)->is_active);
    }
}
