<?php

namespace Tests\Feature;

use App\Http\Controllers\LoanStageController;
use App\Models\StageAssignment;
use ReflectionClass;
use Tests\TestCase;

/**
 * Asserts the field split between the sanction stage and the docket login stage.
 *
 * Sanction phase 3 now captures only `sanction_date`. The loan financials
 * (sanctioned_amount, sanctioned_rate, tenure_months, emi_amount) moved to
 * docket phase 2, where they are filled by the office employee.
 */
class SanctionDocketFieldMoveTest extends TestCase
{
    private LoanStageController $controller;

    private ReflectionClass $reflector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = app(LoanStageController::class);
        $this->reflector = new ReflectionClass($this->controller);
    }

    private function invoke(string $method, array $args): mixed
    {
        $m = $this->reflector->getMethod($method);
        $m->setAccessible(true);

        return $m->invoke($this->controller, ...$args);
    }

    /* ── Required fields ── */

    public function test_sanction_required_fields_returns_only_sanction_date(): void
    {
        $errors = $this->invoke('getFieldErrors', ['sanction', ['sanction_phase' => '3', 'sanction_date' => '']]);
        $this->assertArrayHasKey('sanction_date', $errors);
        $this->assertArrayNotHasKey('sanctioned_amount', $errors);
        $this->assertArrayNotHasKey('sanctioned_rate', $errors);
        $this->assertArrayNotHasKey('tenure_months', $errors);
        $this->assertArrayNotHasKey('emi_amount', $errors);
    }

    public function test_sanction_with_date_alone_has_no_missing_field_errors(): void
    {
        $errors = $this->invoke('getFieldErrors', ['sanction', [
            'sanction_phase' => '3',
            'sanction_date' => '01/01/2026',
        ]]);
        $this->assertSame([], $errors);
    }

    public function test_docket_phase_2_requires_all_five_financial_fields(): void
    {
        $errors = $this->invoke('getFieldErrors', ['docket', ['docket_phase' => '2']]);
        $this->assertArrayHasKey('login_date', $errors);
        $this->assertArrayHasKey('sanctioned_amount', $errors);
        $this->assertArrayHasKey('sanctioned_rate', $errors);
        $this->assertArrayHasKey('tenure_months', $errors);
        $this->assertArrayHasKey('emi_amount', $errors);
    }

    public function test_docket_phase_1_has_no_required_fields(): void
    {
        $errors = $this->invoke('getFieldErrors', ['docket', ['docket_phase' => '1']]);
        $this->assertSame([], $errors);
    }

    public function test_docket_phase_2_with_all_fields_has_no_errors(): void
    {
        $errors = $this->invoke('getFieldErrors', ['docket', [
            'docket_phase' => '2',
            'login_date' => '01/01/2026',
            'sanctioned_amount' => '1000000',
            'sanctioned_rate' => '8.5',
            'tenure_months' => '240',
            'emi_amount' => '8000',
        ]]);
        $this->assertSame([], $errors);
    }

    /* ── Auto-complete predicate ── */

    public function test_sanction_data_complete_with_only_date(): void
    {
        $assignment = new StageAssignment([
            'stage_key' => 'sanction',
            'notes' => json_encode([
                'sanction_phase' => '3',
                'sanction_date' => '01/01/2026',
            ]),
        ]);
        $this->assertTrue($this->invoke('isStageDataComplete', ['sanction', $assignment]));
    }

    public function test_docket_data_not_complete_without_financials(): void
    {
        $assignment = new StageAssignment([
            'stage_key' => 'docket',
            'notes' => json_encode([
                'docket_phase' => '2',
                'login_date' => '01/01/2026',
            ]),
        ]);
        $this->assertFalse($this->invoke('isStageDataComplete', ['docket', $assignment]));
    }

    public function test_docket_data_complete_with_all_financials(): void
    {
        $assignment = new StageAssignment([
            'stage_key' => 'docket',
            'notes' => json_encode([
                'docket_phase' => '2',
                'login_date' => '01/01/2026',
                'sanctioned_amount' => '1000000',
                'sanctioned_rate' => '8.5',
                'tenure_months' => '240',
                'emi_amount' => '8000',
            ]),
        ]);
        $this->assertTrue($this->invoke('isStageDataComplete', ['docket', $assignment]));
    }
}
