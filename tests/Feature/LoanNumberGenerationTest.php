<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * generateLoanNumber() must compare the monthly counter NUMERICALLY. String
 * ordering ranks '...-9999' above '...-10000', which would regenerate a
 * colliding number and break all loan creation for the rest of the month.
 */
class LoanNumberGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function seedLoan(string $loanNumber): void
    {
        $user = User::create([
            'name' => 'U'.uniqid(),
            'email' => uniqid().'@test',
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);

        LoanDetail::create([
            'loan_number' => $loanNumber,
            'customer_name' => 'Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 1000000,
            'status' => 'active',
            'current_stage' => 'inquiry',
            'bank_id' => $bank->id,
            'bank_name' => $bank->name,
            'branch_id' => $branch->id,
            'created_by' => $user->id,
            'assigned_advisor' => $user->id,
        ]);
    }

    public function test_first_number_of_the_month_starts_at_0001(): void
    {
        $prefix = 'SHF-'.now()->format('Ym').'-';

        $this->assertSame($prefix.'0001', LoanDetail::generateLoanNumber());
    }

    public function test_counter_increments_numerically_past_9999(): void
    {
        $prefix = 'SHF-'.now()->format('Ym').'-';

        $this->seedLoan($prefix.'9999');
        $this->assertSame($prefix.'10000', LoanDetail::generateLoanNumber());

        $this->seedLoan($prefix.'10000');
        $this->assertSame($prefix.'10001', LoanDetail::generateLoanNumber());
    }
}
