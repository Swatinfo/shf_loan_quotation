<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerKycDetail;
use App\Models\LoanDetail;
use App\Models\Role;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Customer identity by PAN + per-loan KYC snapshots.
 * Master is created once per PAN and never updated; each loan carries its own
 * customer_kyc_details snapshot.
 */
class CustomerKycTest extends TestCase
{
    use RefreshDatabase;

    private CustomerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $this->service = app(CustomerService::class);
    }

    private function makeUser(string $role = 'super_admin'): User
    {
        $user = User::create([
            'name' => 'U'.uniqid(), 'email' => uniqid().'@test', 'password' => bcrypt('x'), 'is_active' => true,
        ]);
        $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

        return $user->fresh('roles');
    }

    private function makeLoan(): LoanDetail
    {
        return LoanDetail::create([
            'loan_number' => 'L-'.uniqid(),
            'customer_name' => 'Seed',
            'customer_type' => 'salaried',
            'loan_amount' => 1000000,
            'status' => 'active',
            'current_stage' => 'inquiry',
            'created_by' => $this->makeUser()->id,
        ]);
    }

    private function kyc(array $over = []): array
    {
        return array_merge([
            'customer_name' => 'Ramesh', 'mobile' => '9999999999',
            'email' => 'r@x.com', 'date_of_birth' => '1990-01-01', 'pan_number' => 'ABCDE1234F',
        ], $over);
    }

    public function test_master_created_once_per_pan_and_never_updated(): void
    {
        $this->actingAs($this->makeUser());

        $m1 = $this->service->resolveMasterByPan($this->kyc());
        // Same PAN (lowercase) but different name → reuse master, do NOT update it.
        $m2 = $this->service->resolveMasterByPan($this->kyc(['pan_number' => 'abcde1234f', 'customer_name' => 'Different']));

        $this->assertSame($m1->id, $m2->id);
        $this->assertSame('Ramesh', $m2->customer_name);
        $this->assertSame(1, Customer::count());
    }

    public function test_record_kyc_snapshots_entered_values(): void
    {
        $this->actingAs($this->makeUser());
        $master = $this->service->resolveMasterByPan($this->kyc());
        $loan = $this->makeLoan();

        $row = $this->service->recordKyc($master, $this->kyc(['mobile' => '8888888888']), ['loan_id' => $loan->id, 'source' => 'conversion']);

        $this->assertSame($master->id, $row->customer_id);
        $this->assertSame('8888888888', $row->mobile);
        $this->assertSame('conversion', $row->source);
    }

    public function test_edit_updates_kyc_in_place_when_pan_unchanged(): void
    {
        $this->actingAs($this->makeUser());
        $loan = $this->makeLoan();
        $first = $this->service->captureForLoan($this->kyc(), ['loan_id' => $loan->id, 'source' => 'conversion']);
        $loan->update(['customer_id' => $first->customer_id, 'customer_kyc_details_id' => $first->id]);

        $synced = $this->service->syncLoanKyc($loan->fresh(), $this->kyc(['mobile' => '7777777777']));

        $this->assertSame($first->id, $synced->id);               // same snapshot row
        $this->assertSame('7777777777', $synced->mobile);          // updated in place
        $this->assertSame(1, CustomerKycDetail::count());          // no new row
    }

    public function test_edit_creates_new_master_and_kyc_when_pan_changes(): void
    {
        $this->actingAs($this->makeUser());
        $loan = $this->makeLoan();
        $first = $this->service->captureForLoan($this->kyc(), ['loan_id' => $loan->id, 'source' => 'conversion']);
        $loan->update(['customer_id' => $first->customer_id, 'customer_kyc_details_id' => $first->id]);

        $synced = $this->service->syncLoanKyc($loan->fresh(), $this->kyc(['pan_number' => 'ZZZZZ9999Z']));

        $this->assertNotSame($first->id, $synced->id);             // new snapshot
        $this->assertSame(2, Customer::count());                   // new master for new PAN
        $this->assertSame($synced->customer_id, $loan->fresh()->customer_id);
    }

    public function test_backfill_links_loans_to_master_and_kyc(): void
    {
        $this->actingAs($this->makeUser());

        // Two legacy loans for the same PAN, neither linked to a customer/kyc.
        $loanA = $this->makeLoan();
        $loanA->update(['customer_phone' => '9000000001', 'pan_number' => 'ABCDE1234F', 'customer_id' => null]);
        $loanB = $this->makeLoan();
        $loanB->update(['customer_phone' => '9000000002', 'pan_number' => 'ABCDE1234F', 'customer_id' => null]);

        $this->artisan('customers:backfill-kyc')->assertExitCode(0);

        $loanA->refresh();
        $loanB->refresh();
        $this->assertNotNull($loanA->customer_kyc_details_id);
        $this->assertNotNull($loanB->customer_kyc_details_id);
        // Same PAN → one shared master (no duplicate), two distinct KYC snapshots.
        $this->assertSame($loanA->customer_id, $loanB->customer_id);
        $this->assertSame(1, Customer::whereRaw('UPPER(pan_number) = ?', ['ABCDE1234F'])->count());
        $this->assertSame(2, CustomerKycDetail::where('customer_id', $loanA->customer_id)->count());
        $this->assertSame('9000000002', $loanB->customerKycDetails->mobile);
    }

    public function test_pan_lookup_returns_latest_details(): void
    {
        $admin = $this->makeUser('super_admin');
        $this->actingAs($admin);
        $this->service->captureForLoan($this->kyc(['mobile' => '1112223334']), ['source' => 'conversion']);

        $this->getJson(route('customers.lookup', ['pan' => 'abcde1234f']))
            ->assertOk()
            ->assertJson(['found' => true, 'customer' => ['mobile' => '1112223334']]);

        $this->getJson(route('customers.lookup', ['pan' => 'NOTAPAN']))
            ->assertOk()
            ->assertJson(['found' => false]);
    }

    public function test_lookup_returns_active_and_on_hold_loans_only(): void
    {
        $admin = $this->makeUser('super_admin');
        $this->actingAs($admin);

        $master = $this->service->resolveMasterByPan($this->kyc());
        foreach (['active' => 'L-A', 'on_hold' => 'L-H', 'completed' => 'L-C'] as $status => $num) {
            LoanDetail::create([
                'loan_number' => $num.uniqid(),
                'customer_id' => $master->id,
                'customer_name' => 'X', 'customer_type' => 'salaried', 'loan_amount' => 100,
                'status' => $status, 'current_stage' => 'inquiry', 'created_by' => $admin->id,
            ]);
        }

        $loans = $this->getJson(route('customers.lookup', ['pan' => 'ABCDE1234F']))
            ->assertOk()->json('loans');

        $numbers = collect($loans)->pluck('loan_number')->implode(' ');
        $this->assertCount(2, $loans);                 // active + on_hold, not completed
        $this->assertStringContainsString('L-A', $numbers);
        $this->assertStringContainsString('L-H', $numbers);
        $this->assertStringNotContainsString('L-C', $numbers);
    }
}
