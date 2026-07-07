<?php

namespace Tests\Feature;

use App\Http\Controllers\LoanStageController;
use App\Models\LoanDetail;
use App\Models\Role;
use App\Models\StageAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Escalation-ladder guards for sanction_decision + technical_valuation.
 * Level comes from escalation_history; escalate_to_bm only at base level,
 * escalate_to_bdh only by a BM at bm level, reject only at escalated levels.
 * These tests exercise the early guard returns (no stage-progression infra).
 */
class StageEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'branch_manager', 'bdh', 'office_employee', 'loan_advisor'] as $s) {
            Role::firstOrCreate(['slug' => $s], ['name' => ucwords(str_replace('_', ' ', $s))]);
        }
    }

    private function makeUser(string $role): User
    {
        $u = User::create(['name' => 'U'.uniqid(), 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->roles()->sync(Role::where('slug', $role)->pluck('id'));

        return $u->fresh('roles');
    }

    private function makeLoanWithAssignment(string $stageKey, string $level = 'base'): LoanDetail
    {
        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(), 'customer_name' => 'C', 'customer_type' => 'salaried',
            'loan_amount' => 100000, 'status' => 'active', 'current_stage' => 'parallel_processing',
            'created_by' => $this->makeUser('loan_advisor')->id,
        ]);

        $notes = [];
        if ($level === 'bm') {
            $notes['escalation_history'] = [['to_role' => 'branch_manager', 'from_user_name' => 'x', 'date' => '2026-05-31']];
        } elseif ($level === 'bdh') {
            $notes['escalation_history'] = [['to_role' => 'bdh', 'from_user_name' => 'x', 'date' => '2026-05-31']];
        }

        StageAssignment::create([
            'loan_id' => $loan->id, 'stage_key' => $stageKey, 'parent_stage_key' => 'parallel_processing',
            'assigned_to' => null, 'status' => 'in_progress', 'is_parallel_stage' => true,
            'notes' => $notes ? json_encode($notes) : null,
        ]);

        return $loan;
    }

    private function decide(User $actor, LoanDetail $loan, string $stageKey, array $data)
    {
        $this->actingAs($actor);
        $controller = app(LoanStageController::class);

        return $controller->decisionAction(Request::create('/x', 'POST', $data), $loan, $stageKey);
    }

    public function test_technical_valuation_has_no_approve_action(): void
    {
        $loan = $this->makeLoanWithAssignment('technical_valuation');
        $this->expectException(ValidationException::class);
        $this->decide($this->makeUser('office_employee'), $loan, 'technical_valuation', ['action' => 'approve']);
    }

    public function test_escalate_to_bm_blocked_when_already_escalated(): void
    {
        $loan = $this->makeLoanWithAssignment('sanction_decision', 'bm');
        $res = $this->decide($this->makeUser('branch_manager'), $loan, 'sanction_decision', ['action' => 'escalate_to_bm', 'decision_remarks' => 'x']);
        $this->assertSame(422, $res->getStatusCode());
    }

    public function test_escalate_to_bdh_blocked_at_base_level(): void
    {
        $loan = $this->makeLoanWithAssignment('sanction_decision', 'base');
        $res = $this->decide($this->makeUser('branch_manager'), $loan, 'sanction_decision', ['action' => 'escalate_to_bdh', 'decision_remarks' => 'x']);
        $this->assertSame(403, $res->getStatusCode());
    }

    public function test_escalate_to_bdh_blocked_for_non_bm_at_bm_level(): void
    {
        $loan = $this->makeLoanWithAssignment('sanction_decision', 'bm');
        $res = $this->decide($this->makeUser('office_employee'), $loan, 'sanction_decision', ['action' => 'escalate_to_bdh', 'decision_remarks' => 'x']);
        $this->assertSame(403, $res->getStatusCode());
    }

    public function test_reject_blocked_at_base_for_non_admin(): void
    {
        $loan = $this->makeLoanWithAssignment('technical_valuation', 'base');
        $res = $this->decide($this->makeUser('office_employee'), $loan, 'technical_valuation', ['action' => 'reject', 'rejection_reason' => 'not valuable enough to proceed']);
        $this->assertSame(403, $res->getStatusCode());
    }

    public function test_admin_bypasses_level_guard_for_escalate_to_bdh(): void
    {
        // Admin at base level should NOT be blocked by the level/role guard.
        // It proceeds past the guard; with no BM in branch, transfer is skipped
        // and it still returns success.
        $loan = $this->makeLoanWithAssignment('sanction_decision', 'base');
        $res = $this->decide($this->makeUser('super_admin'), $loan, 'sanction_decision', ['action' => 'escalate_to_bdh', 'decision_remarks' => 'x']);
        $this->assertSame(200, $res->getStatusCode());
        $this->assertTrue($res->getData()->success);
    }
}
