<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\StageAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The _stage-readonly-detail partial lets non-assignee viewers see the same
 * financial figures the assignee sees, but it must NEVER render an action
 * control — stage-action endpoints are gated only by the manage_loan_stages
 * permission, so a leaked form/button would let a permitted non-assignee act.
 */
class StageReadonlyDetailTest extends TestCase
{
    use RefreshDatabase;

    private function makeLoan(): LoanDetail
    {
        Role::firstOrCreate(['slug' => 'loan_advisor'], ['name' => 'Loan Advisor']);
        $user = User::create(['name' => 'U', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $bank = Bank::create(['name' => 'B-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Br-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'P-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);

        return LoanDetail::create([
            'loan_number' => 'L-'.uniqid(),
            'customer_name' => 'Customer',
            'customer_type' => 'salaried',
            'loan_amount' => 1000000,
            'status' => 'active',
            'current_stage' => 'rate_pf',
            'bank_id' => $bank->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'created_by' => $user->id,
            'assigned_advisor' => $user->id,
        ]);
    }

    private function render(LoanDetail $loan, StageAssignment $assignment): string
    {
        return view('newtheme.loans._stage-readonly-detail', ['assignment' => $assignment, 'loan' => $loan])->render();
    }

    private function assertNoActionControls(string $html): void
    {
        foreach (['<form', '<input', '<textarea', '<select', '<button', 'id="edit-', 'onclick', 'shf-stage-action'] as $needle) {
            $this->assertStringNotContainsString($needle, $html, "read-only partial must not contain {$needle}");
        }
    }

    public function test_rate_pf_shows_figures_and_no_controls(): void
    {
        $loan = $this->makeLoan();
        $assignment = StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'rate_pf',
            'status' => 'completed',
            'notes' => json_encode([
                'interest_rate' => '9.25',
                'processing_fee_amount' => 15000,
                'total_pf' => 17700,
                'special_conditions' => 'SECRET-CONDITION-TEXT',
                'stageRemarks' => 'RATE-REMARK-TEXT',
            ]),
        ]);

        $html = $this->render($loan, $assignment);

        $this->assertStringContainsString('9.25', $html);
        $this->assertStringContainsString('15,000', $html);
        $this->assertStringContainsString('SECRET-CONDITION-TEXT', $html);
        $this->assertStringContainsString('RATE-REMARK-TEXT', $html);
        $this->assertNoActionControls($html);
    }

    public function test_sanction_shows_date_and_no_controls(): void
    {
        $loan = $this->makeLoan();
        $assignment = StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'sanction',
            'status' => 'completed',
            'notes' => json_encode(['sanction_date' => '01/08/2026', 'conditions' => 'SANCTION-COND-TEXT']),
        ]);

        $html = $this->render($loan, $assignment);

        $this->assertStringContainsString('01/08/2026', $html);
        $this->assertStringContainsString('SANCTION-COND-TEXT', $html);
        $this->assertNoActionControls($html);
    }

    public function test_docket_shows_financials_and_no_controls(): void
    {
        $loan = $this->makeLoan();
        $assignment = StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'docket',
            'status' => 'completed',
            'notes' => json_encode(['login_date' => '05/08/2026', 'sanctioned_amount' => '980000', 'emi_amount' => '8500', 'tenure_months' => '240']),
        ]);

        $html = $this->render($loan, $assignment);

        $this->assertStringContainsString('980000', $html);
        $this->assertStringContainsString('8500', $html);
        $this->assertStringContainsString('240', $html);
        $this->assertNoActionControls($html);
    }

    public function test_unknown_stage_renders_nothing(): void
    {
        $loan = $this->makeLoan();
        $assignment = StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => 'kfs',
            'status' => 'completed',
            'notes' => json_encode([]),
        ]);

        $html = trim($this->render($loan, $assignment));

        $this->assertSame('', $html);
    }
}
