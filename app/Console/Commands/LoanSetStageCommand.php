<?php

namespace App\Console\Commands;

use App\Models\LoanDetail;
use App\Models\StageAssignment;
use App\Models\User;
use App\Services\LoanStageService;
use Illuminate\Console\Command;

class LoanSetStageCommand extends Command
{
    protected $signature = 'loan:set-stage
        {loan? : Loan ID (prompted when omitted)}
        {stage? : Target stage key — runs non-interactively when given}
        {--phase= : Phase number for phased stages}
        {--variant= : escalated_bm | escalated_bdh | transferred_oe}
        {--force : Skip the prior-stage completeness check}';

    protected $description = 'Reset a loan to a specific stage/phase — interactive menu, or pass {loan} {stage} for a non-interactive reset';

    /** @var array<int, array{stage_key: string, phase: ?int, label: string, role: string, section: string}> */
    private array $menuOptions = [];

    private ?LoanDetail $loan = null;

    /** Resolved user IDs keyed by role */
    private array $users = [];

    /** Parallel sub-stage keys */
    private const PARALLEL_SUBS = ['app_number', 'bsm_osv', 'legal_verification', 'original_document_verification', 'technical_valuation', 'sanction_decision'];

    /** Ordered stage keys for sequential flow (includes parallel subs in sequence) */
    private const STAGE_ORDER = [
        'inquiry', 'document_selection', 'document_collection',
        'app_number', 'bsm_osv', 'legal_verification', 'original_document_verification', 'technical_valuation', 'sanction_decision',
        'rate_pf', 'sanction', 'docket', 'kfs', 'esign', 'disbursement', 'otc_clearance',
    ];

    /** Stage name labels */
    private const STAGE_NAMES = [
        'inquiry' => 'Inquiry',
        'document_selection' => 'Document Selection',
        'document_collection' => 'Document Collection',
        'parallel_processing' => 'Parallel Processing',
        'app_number' => 'App Number',
        'bsm_osv' => 'BSM/OSV',
        'legal_verification' => 'Legal Verification',
        'original_document_verification' => 'Original Document Verification',
        'technical_valuation' => 'Technical Valuation',
        'sanction_decision' => 'Sanction Decision',
        'rate_pf' => 'Rate & PF',
        'sanction' => 'Sanction',
        'docket' => 'Docket Login',
        'kfs' => 'KFS',
        'esign' => 'E-Sign & eNACH',
        'disbursement' => 'Disbursement',
        'otc_clearance' => 'OTC Clearance',
    ];

    public function handle(): int
    {
        $loanId = $this->argument('loan') ?? $this->ask('Enter Loan ID');
        $this->loan = LoanDetail::with(['bank', 'product', 'branch', 'advisor', 'creator', 'stageAssignments'])
            ->find($loanId);

        if (! $this->loan) {
            $this->error("Loan ID {$loanId} not found.");

            return self::FAILURE;
        }

        // Default users (by role) come from the shared service so CLI display
        // and the actual reset assignee resolution stay in lockstep.
        $this->users = app(LoanStageService::class)->resolveResetUsers($this->loan);

        // Non-interactive: `loan:set-stage {loan} {stage}` skips the menu.
        if ($this->argument('stage')) {
            return $this->runNonInteractive();
        }

        $this->buildMenu();
        $this->displayLoanInfo();
        $this->displayStageProgress();
        $this->displayMenu();

        $choice = (int) $this->ask('Enter choice [1-'.count($this->menuOptions).']');
        if ($choice < 1 || $choice > count($this->menuOptions)) {
            $this->error('Invalid choice.');

            return self::FAILURE;
        }

        $option = $this->menuOptions[$choice];

        // Validate prior stages
        $errors = $this->validatePriorStages($choice);
        if (! empty($errors)) {
            $this->newLine();
            $this->error('Cannot proceed! '.count($errors).' issue(s) found:');
            foreach ($errors as $err) {
                $this->line("  <fg=red>✗</> {$err}");
            }
            $this->newLine();
            $this->info('Complete these stages first, or choose an earlier option.');

            return self::FAILURE;
        }

        $this->info('All prior stages valid!');
        $this->newLine();

        // Execute reset
        $this->resetToOption($option);

        $this->newLine();
        $this->info('Done! Open the loan to test.');

        return self::SUCCESS;
    }

    /**
     * Non-interactive reset driven by the {stage} argument + options.
     */
    private function runNonInteractive(): int
    {
        $stage = $this->argument('stage');
        $phase = ($this->option('phase') !== null && $this->option('phase') !== '') ? (int) $this->option('phase') : null;
        $variant = $this->option('variant') ?: null;

        if (! in_array($stage, self::STAGE_ORDER, true)) {
            $this->error("Unknown stage key: {$stage}");
            $this->line('Valid stages: '.implode(', ', self::STAGE_ORDER));

            return self::FAILURE;
        }

        $this->buildMenu();

        // Locate a matching menu option so we can run the same prior-stage check.
        $choice = null;
        foreach ($this->menuOptions as $num => $opt) {
            if ($opt['stage_key'] === $stage
                && ($opt['phase'] ?? null) === $phase
                && ($opt['variant'] ?? null) === $variant) {
                $choice = $num;
                break;
            }
        }

        if (! $this->option('force') && $choice !== null) {
            $errors = $this->validatePriorStages($choice);
            if (! empty($errors)) {
                $this->newLine();
                $this->error('Prior stages incomplete ('.count($errors).'). Re-run with --force to override.');

                return self::FAILURE;
            }
        }

        $log = app(LoanStageService::class)->resetToStage($this->loan, $stage, $phase, $variant);
        foreach ($log as $line) {
            $this->line("  <fg=green>→</> {$line}");
        }

        $this->info("Loan #{$this->loan->loan_number} reset to {$stage}.");

        return self::SUCCESS;
    }

    private function userName(?int $userId): string
    {
        if (! $userId) {
            return '<fg=red>not found</>';
        }
        $user = User::find($userId);

        return $user ? $user->name." ({$userId})" : '<fg=red>not found</>';
    }

    private function userLabel(string $role): string
    {
        $userId = $this->users[$role] ?? null;

        return $this->userName($userId);
    }

    private function buildMenu(): void
    {
        $n = 0;

        // Sequential stages
        $this->menuOptions[++$n] = ['stage_key' => 'inquiry', 'phase' => null, 'label' => 'Inquiry → Task owner starts', 'role' => 'task_owner', 'section' => 'SEQUENTIAL STAGES'];
        $this->menuOptions[++$n] = ['stage_key' => 'document_selection', 'phase' => null, 'label' => 'Document Selection → Task owner selects docs', 'role' => 'task_owner', 'section' => 'SEQUENTIAL STAGES'];
        $this->menuOptions[++$n] = ['stage_key' => 'document_collection', 'phase' => null, 'label' => 'Document Collection → Task owner collects docs', 'role' => 'task_owner', 'section' => 'SEQUENTIAL STAGES'];

        // Parallel processing sub-stages
        $this->menuOptions[++$n] = ['stage_key' => 'app_number', 'phase' => null, 'label' => 'App Number → Task owner fills app# & docket timeline', 'role' => 'task_owner', 'section' => 'PARALLEL PROCESSING (Sub-Stages)'];

        $this->menuOptions[++$n] = ['stage_key' => 'bsm_osv', 'phase' => 1, 'label' => 'BSM/OSV → Phase 1: Task owner sends to bank', 'role' => 'task_owner', 'section' => 'PARALLEL PROCESSING (Sub-Stages)'];
        $this->menuOptions[++$n] = ['stage_key' => 'bsm_osv', 'phase' => 2, 'label' => 'BSM/OSV → Phase 2: Bank employee processes', 'role' => 'bank_employee', 'section' => 'PARALLEL PROCESSING (Sub-Stages)'];
        $this->menuOptions[++$n] = ['stage_key' => 'bsm_osv', 'phase' => 3, 'label' => 'BSM/OSV → Phase 3: Bank employee marks done', 'role' => 'bank_employee', 'section' => 'PARALLEL PROCESSING (Sub-Stages)'];
        $this->menuOptions[++$n] = ['stage_key' => 'bsm_osv', 'phase' => 4, 'label' => 'BSM/OSV → Phase 4: Task owner reviews', 'role' => 'task_owner', 'section' => 'PARALLEL PROCESSING (Sub-Stages)'];

        $this->menuOptions[++$n] = ['stage_key' => 'legal_verification', 'phase' => 1, 'label' => 'Legal Verification → Phase 1: Task owner sends to bank', 'role' => 'task_owner', 'section' => 'PARALLEL PROCESSING (Sub-Stages)'];
        $this->menuOptions[++$n] = ['stage_key' => 'legal_verification', 'phase' => 2, 'label' => 'Legal Verification → Phase 2: Bank employee verifies', 'role' => 'bank_employee', 'section' => 'PARALLEL PROCESSING (Sub-Stages)'];
        $this->menuOptions[++$n] = ['stage_key' => 'legal_verification', 'phase' => 3, 'label' => 'Legal Verification → Phase 3: Task owner reviews', 'role' => 'task_owner', 'section' => 'PARALLEL PROCESSING (Sub-Stages)'];

        $this->menuOptions[++$n] = ['stage_key' => 'original_document_verification', 'phase' => null, 'label' => 'Original Document Verification → Task owner verifies originals', 'role' => 'task_owner', 'section' => 'PARALLEL PROCESSING (Sub-Stages)'];

        $this->menuOptions[++$n] = ['stage_key' => 'technical_valuation', 'phase' => 1, 'label' => 'Technical Valuation → Phase 1: Task owner sends to OE', 'role' => 'task_owner', 'section' => 'PARALLEL PROCESSING (Sub-Stages)'];
        $this->menuOptions[++$n] = ['stage_key' => 'technical_valuation', 'phase' => 2, 'label' => 'Technical Valuation → Phase 2: OE completes valuation', 'role' => 'office_employee', 'section' => 'PARALLEL PROCESSING (Sub-Stages)'];

        $this->menuOptions[++$n] = ['stage_key' => 'sanction_decision', 'phase' => null, 'label' => 'Sanction Decision → Task owner decides', 'role' => 'task_owner', 'section' => 'PARALLEL PROCESSING (Sub-Stages)', 'variant' => 'task_owner'];
        $this->menuOptions[++$n] = ['stage_key' => 'sanction_decision', 'phase' => null, 'label' => 'Sanction Decision → Escalated to Branch Manager', 'role' => 'branch_manager', 'section' => 'PARALLEL PROCESSING (Sub-Stages)', 'variant' => 'escalated_bm'];
        $this->menuOptions[++$n] = ['stage_key' => 'sanction_decision', 'phase' => null, 'label' => 'Sanction Decision → Escalated to BDH', 'role' => 'bdh', 'section' => 'PARALLEL PROCESSING (Sub-Stages)', 'variant' => 'escalated_bdh'];

        // Main stages
        $this->menuOptions[++$n] = ['stage_key' => 'rate_pf', 'phase' => 1, 'label' => 'Rate & PF → Phase 1: Task owner sends to bank', 'role' => 'task_owner', 'section' => 'MAIN STAGES'];
        $this->menuOptions[++$n] = ['stage_key' => 'rate_pf', 'phase' => 2, 'label' => 'Rate & PF → Phase 2: Bank employee fills rate', 'role' => 'bank_employee', 'section' => 'MAIN STAGES'];
        $this->menuOptions[++$n] = ['stage_key' => 'rate_pf', 'phase' => 3, 'label' => 'Rate & PF → Phase 3: Task owner fills PF & charges', 'role' => 'task_owner', 'section' => 'MAIN STAGES'];

        $this->menuOptions[++$n] = ['stage_key' => 'sanction', 'phase' => 1, 'label' => 'Sanction → Phase 1: Task owner sends for sanction', 'role' => 'task_owner', 'section' => 'MAIN STAGES'];
        $this->menuOptions[++$n] = ['stage_key' => 'sanction', 'phase' => 2, 'label' => 'Sanction → Phase 2: Bank employee generates letter', 'role' => 'bank_employee', 'section' => 'MAIN STAGES'];
        $this->menuOptions[++$n] = ['stage_key' => 'sanction', 'phase' => 3, 'label' => 'Sanction → Phase 3: Task owner fills details', 'role' => 'task_owner', 'section' => 'MAIN STAGES'];

        $this->menuOptions[++$n] = ['stage_key' => 'docket', 'phase' => 1, 'label' => 'Docket → Phase 1: Task owner sends to OE', 'role' => 'task_owner', 'section' => 'MAIN STAGES'];
        $this->menuOptions[++$n] = ['stage_key' => 'docket', 'phase' => 2, 'label' => 'Docket → Phase 2: OE fills login date & completes', 'role' => 'office_employee', 'section' => 'MAIN STAGES'];

        $this->menuOptions[++$n] = ['stage_key' => 'kfs', 'phase' => null, 'label' => 'KFS → Task owner completes', 'role' => 'task_owner', 'section' => 'MAIN STAGES'];
        $this->menuOptions[++$n] = ['stage_key' => 'esign', 'phase' => 1, 'label' => 'E-Sign → Phase 1: Task owner sends to bank', 'role' => 'task_owner', 'section' => 'MAIN STAGES'];
        $this->menuOptions[++$n] = ['stage_key' => 'esign', 'phase' => 2, 'label' => 'E-Sign → Phase 2: Bank employee processes', 'role' => 'bank_employee', 'section' => 'MAIN STAGES'];
        $this->menuOptions[++$n] = ['stage_key' => 'esign', 'phase' => 3, 'label' => 'E-Sign → Phase 3: Bank employee completes e-sign', 'role' => 'bank_employee', 'section' => 'MAIN STAGES'];
        $this->menuOptions[++$n] = ['stage_key' => 'esign', 'phase' => 4, 'label' => 'E-Sign → Phase 4: Task owner confirms', 'role' => 'task_owner', 'section' => 'MAIN STAGES'];

        $this->menuOptions[++$n] = ['stage_key' => 'disbursement', 'phase' => null, 'label' => 'Disbursement → OE processes', 'role' => 'office_employee', 'section' => 'MAIN STAGES'];
        $this->menuOptions[++$n] = ['stage_key' => 'otc_clearance', 'phase' => null, 'label' => 'OTC Clearance → Task owner completes (handover)', 'role' => 'task_owner', 'section' => 'MAIN STAGES', 'variant' => 'task_owner'];
        $this->menuOptions[++$n] = ['stage_key' => 'otc_clearance', 'phase' => null, 'label' => 'OTC Clearance → Transferred to OE (OE completes)', 'role' => 'office_employee', 'section' => 'MAIN STAGES', 'variant' => 'transferred_oe'];
    }

    private function displayLoanInfo(): void
    {
        $loan = $this->loan;
        $currentAssignment = $loan->stageAssignments->firstWhere('status', 'in_progress');
        $currentStageLabel = self::STAGE_NAMES[$loan->current_stage] ?? $loan->current_stage;

        // Detect current phase
        $phaseInfo = '';
        if ($currentAssignment) {
            $notes = $currentAssignment->getNotesData();
            $phaseKey = match ($currentAssignment->stage_key) {
                'bsm_osv' => 'bsm_osv_phase',
                'legal_verification' => 'legal_phase',
                'rate_pf' => 'rate_pf_phase',
                'sanction' => 'sanction_phase',
                'docket' => 'docket_phase',
                'esign' => 'esign_phase',
                default => null,
            };
            if ($phaseKey && ! empty($notes[$phaseKey])) {
                $phaseInfo = "Phase {$notes[$phaseKey]}";
            }
        }

        $this->newLine();
        $this->line('╔══════════════════════════════════════════════════════════════════════╗');
        $this->line('║  <fg=white;options=bold>LOAN DETAILS</>                                                       ║');
        $this->line('╠══════════════════════════════════════════════════════════════════════╣');
        $this->printRow('Loan #', $loan->loan_number);
        if ($loan->application_number) {
            $this->printRow('App #', $loan->application_number);
        }
        $this->printRow('Customer', $loan->customer_name." ({$loan->customer_type})");
        $this->printRow('Bank / Product', ($loan->bank?->name ?? '—').' | '.($loan->product?->name ?? '—'));
        $this->printRow('Branch', $loan->branch?->name ?? '—');
        $this->printRow('Amount', '₹ '.number_format($loan->loan_amount));
        $this->printRow('Status', ucfirst($loan->status));
        $this->line('╠══════════════════════════════════════════════════════════════════════╣');
        $this->line('║  <fg=white;options=bold>CURRENT STATE</>                                                      ║');
        $this->line('╠══════════════════════════════════════════════════════════════════════╣');
        $this->printRow('Current Stage', $currentStageLabel);
        if ($phaseInfo) {
            $this->printRow('Phase', $phaseInfo);
        }
        $this->printRow('Assigned To', $currentAssignment ? $this->userName($currentAssignment->assigned_to) : '—');
        if ($currentAssignment?->started_at) {
            $this->printRow('Started At', $currentAssignment->started_at->format('d/m/Y h:i A'));
        }
        $this->line('╠══════════════════════════════════════════════════════════════════════╣');
        $this->line('║  <fg=white;options=bold>KEY USERS</>                                                          ║');
        $this->line('╠══════════════════════════════════════════════════════════════════════╣');
        $this->printRow('Task Owner', $this->userLabel('task_owner'));
        $this->printRow('Bank Employee', $this->userLabel('bank_employee'));
        $this->printRow('Office Employee', $this->userLabel('office_employee'));
        $this->printRow('Branch Manager', $this->userLabel('branch_manager'));
        $this->printRow('BDH', $this->userLabel('bdh'));
        $this->line('╚══════════════════════════════════════════════════════════════════════╝');
    }

    private function printRow(string $label, string $value): void
    {
        $this->line(sprintf('║  %-16s %s', $label.':', $value));
    }

    private function displayStageProgress(): void
    {
        $this->newLine();
        $this->line('<fg=white;options=bold>STAGE PROGRESS:</>');
        $loan = $this->loan;

        foreach (self::STAGE_ORDER as $key) {
            $assignment = $loan->stageAssignments->firstWhere('stage_key', $key);
            if (! $assignment) {
                continue;
            }

            $name = str_pad(self::STAGE_NAMES[$key] ?? $key, 25);
            $status = str_pad($assignment->status, 13);
            $assignee = $assignment->assigned_to ? $this->userName($assignment->assigned_to) : '—';
            $notes = $assignment->getNotesData();
            $extra = $this->getStageProgressExtra($key, $notes, $assignment);

            $icon = match ($assignment->status) {
                'completed' => '<fg=green>✓</>',
                'in_progress' => '<fg=blue>●</>',
                'rejected' => '<fg=red>✗</>',
                'skipped' => '<fg=yellow>⊘</>',
                default => '<fg=gray>○</>',
            };

            $statusColor = match ($assignment->status) {
                'completed' => 'green',
                'in_progress' => 'blue',
                'rejected' => 'red',
                'skipped' => 'yellow',
                default => 'gray',
            };

            $line = "  {$icon} {$name} <fg={$statusColor}>{$status}</> {$assignee}";
            if ($extra) {
                $line .= "  <fg=gray>{$extra}</>";
            }
            $this->line($line);
        }
    }

    private function getStageProgressExtra(string $key, array $notes, StageAssignment $assignment): string
    {
        return match ($key) {
            'app_number' => ! empty($notes['application_number']) ? "App#: {$notes['application_number']}" : '',
            'bsm_osv' => ! empty($notes['bsm_osv_phase']) ? "Phase {$notes['bsm_osv_phase']}" : '',
            'legal_verification' => ! empty($notes['legal_phase']) ? "Phase {$notes['legal_phase']}" : '',
            'original_document_verification' => ! empty($notes['auto_completed_reason'])
                ? 'Auto-completed (legal skipped bank)'
                : (! empty($notes['verification_date']) ? "Verified: {$notes['verification_date']}" : ''),
            'technical_valuation' => $assignment->status === 'completed' ? 'Valuation completed' : '',
            'sanction_decision' => ! empty($notes['decision_action']) ? ucfirst($notes['decision_action']) : '',
            'rate_pf' => ! empty($notes['rate_pf_phase']) ? "Phase {$notes['rate_pf_phase']}".
                (! empty($notes['interest_rate']) ? ", Rate: {$notes['interest_rate']}%" : '') : '',
            'sanction' => ! empty($notes['sanction_phase']) ? "Phase {$notes['sanction_phase']}".
                (! empty($notes['sanction_date']) ? ", Date: {$notes['sanction_date']}" : '') : '',
            'docket' => ! empty($notes['docket_phase']) ? "Phase {$notes['docket_phase']}".
                (! empty($notes['login_date']) ? ", Login: {$notes['login_date']}" : '').
                (! empty($notes['sanctioned_amount']) ? ', ₹'.number_format((int) $notes['sanctioned_amount']) : '') : '',
            'esign' => ! empty($notes['esign_phase']) ? "Phase {$notes['esign_phase']}" : '',
            'otc_clearance' => ! empty($notes['handover_date']) ? "Handover: {$notes['handover_date']}" : '',
            default => '',
        };
    }

    private function displayMenu(): void
    {
        $this->newLine();
        $this->line(str_repeat('─', 70));
        $this->newLine();
        $this->line('<fg=white;options=bold>Choose the state to set:</>');

        $currentSection = '';
        foreach ($this->menuOptions as $num => $opt) {
            if ($opt['section'] !== $currentSection) {
                $currentSection = $opt['section'];
                $this->newLine();
                $this->line(" <fg=yellow;options=bold>{$currentSection}</>");
            }

            $userId = $this->users[$opt['role']] ?? null;
            $userInfo = $userId ? $this->userName($userId) : '<fg=red>no user available</>';
            $numStr = str_pad("[{$num}]", 5);
            $labelStr = str_pad($opt['label'], 55);

            $this->line("  {$numStr} {$labelStr} → {$userInfo}");
        }

        $this->newLine();
    }

    private function validatePriorStages(int $choice): array
    {
        $errors = [];
        $targetOption = $this->menuOptions[$choice];
        $targetStageKey = $targetOption['stage_key'];
        $targetPhase = $targetOption['phase'];

        $this->newLine();
        $this->line('<fg=white;options=bold>Validating prior stages...</>');

        // Collect all menu options that must be "done" before this choice
        // Everything with a lower sequence position must be complete
        $targetStageIdx = array_search($targetStageKey, self::STAGE_ORDER);

        foreach (self::STAGE_ORDER as $idx => $stageKey) {
            if ($idx > $targetStageIdx) {
                break;
            }
            if ($idx === $targetStageIdx) {
                // Same stage — validate prior phases
                $phaseErrors = $this->validatePhasePrerequisites($stageKey, $targetPhase, $targetOption['variant'] ?? null);
                $errors = array_merge($errors, $phaseErrors);

                break;
            }

            $assignment = $this->loan->stageAssignments->firstWhere('stage_key', $stageKey);
            if (! $assignment) {
                continue; // Stage not assigned to this loan
            }

            if ($stageKey === $targetStageKey) {
                continue;
            }

            // For parallel sub-stages before the target: they must be completed
            // unless the target itself is a parallel sub-stage that comes before them
            if (in_array($stageKey, self::PARALLEL_SUBS) && in_array($targetStageKey, self::PARALLEL_SUBS)) {
                $stagePos = array_search($stageKey, self::PARALLEL_SUBS);
                $targetPos = array_search($targetStageKey, self::PARALLEL_SUBS);
                if ($stagePos >= $targetPos) {
                    continue; // This parallel sub comes after or is the target
                }
            }

            $stageName = self::STAGE_NAMES[$stageKey] ?? $stageKey;
            if ($assignment->status !== 'completed') {
                $errors[] = "{$stageName} — status: {$assignment->status} (expected: completed)";
                $this->line("  <fg=red>✗</> {$stageName} — {$assignment->status}");
            } else {
                $notes = $assignment->getNotesData();
                $dataError = $this->checkStageDataComplete($stageKey, $notes, $assignment);
                if ($dataError) {
                    $errors[] = "{$stageName} — {$dataError}";
                    $this->line("  <fg=red>✗</> {$stageName} — {$dataError}");
                } else {
                    $extra = $this->getStageProgressExtra($stageKey, $notes, $assignment);
                    $this->line("  <fg=green>✓</> {$stageName} — completed".($extra ? " ({$extra})" : ''));
                }
            }
        }

        return $errors;
    }

    private function validatePhasePrerequisites(string $stageKey, ?int $targetPhase, ?string $variant): array
    {
        $errors = [];
        if ($targetPhase === null || $targetPhase <= 1) {
            return $errors;
        }

        $assignment = $this->loan->stageAssignments->firstWhere('stage_key', $stageKey);
        if (! $assignment) {
            return $errors;
        }

        $notes = $assignment->getNotesData();
        $stageName = self::STAGE_NAMES[$stageKey] ?? $stageKey;
        $phaseKey = $this->getPhaseNoteKey($stageKey);

        // The stored phase must be >= targetPhase (meaning prior phases were completed)
        // For the target phase itself, we just need prior phases done
        if ($phaseKey) {
            $currentPhase = (int) ($notes[$phaseKey] ?? 0);
            $requiredPhase = $targetPhase;
            if ($currentPhase < $requiredPhase && $assignment->status !== 'completed') {
                // Check if the assignment at least has the right phase set
                $errors[] = "{$stageName} — current phase is {$currentPhase}, need phase {$requiredPhase} set";
                $this->line("  <fg=red>✗</> {$stageName} Phase — at phase {$currentPhase}, need {$requiredPhase}");
            } else {
                $this->line("  <fg=green>✓</> {$stageName} — phase prerequisites met");
            }
        }

        return $errors;
    }

    private function checkStageDataComplete(string $stageKey, array $notes, StageAssignment $assignment): ?string
    {
        return match ($stageKey) {
            'app_number' => empty($notes['application_number']) ? 'application_number missing' :
                (! isset($notes['docket_days_offset']) || $notes['docket_days_offset'] === '' ? 'docket_days_offset missing' : null),
            'bsm_osv' => null, // Simple complete — no phase or data required
            'legal_verification' => (($notes['legal_phase'] ?? '') !== '3' && ($notes['legal_phase'] ?? '') !== 'completed_skip_bank'
                ? 'phase is '.($notes['legal_phase'] ?? 'none').', expected 3 or completed_skip_bank' : null),
            'original_document_verification' => (! empty($notes['auto_completed_reason']) || ! empty($notes['verification_date'])
                ? null
                : 'verification_date missing (and not auto-completed)'),
            'technical_valuation' => null, // Validated by valuation_details table
            'sanction_decision' => empty($notes['decision_action']) ? 'no decision made' : null,
            'rate_pf' => (($notes['rate_pf_phase'] ?? '') !== '3' ? 'phase is '.($notes['rate_pf_phase'] ?? 'none').', expected 3' : null),
            'sanction' => (($notes['sanction_phase'] ?? '') !== '3' ? 'phase is '.($notes['sanction_phase'] ?? 'none').', expected 3' :
                (empty($notes['sanction_date']) ? 'sanction_date missing' : null)),
            'docket' => (($notes['docket_phase'] ?? '') !== '2' ? 'phase is '.($notes['docket_phase'] ?? 'none').', expected 2' :
                (empty($notes['login_date']) ? 'login_date missing' :
                    (empty($notes['sanctioned_amount']) ? 'sanctioned_amount missing' :
                        (empty($notes['sanctioned_rate']) ? 'sanctioned_rate missing' :
                            (empty($notes['tenure_months']) ? 'tenure_months missing' :
                                (empty($notes['emi_amount']) ? 'emi_amount missing' : null)))))),
            'esign' => (($notes['esign_phase'] ?? '') !== '4' ? 'phase is '.($notes['esign_phase'] ?? 'none').', expected 4' : null),
            'otc_clearance' => empty($notes['handover_date']) ? 'handover_date missing' : null,
            default => null,
        };
    }

    private function getPhaseNoteKey(string $stageKey): ?string
    {
        return match ($stageKey) {
            'bsm_osv' => 'bsm_osv_phase',
            'legal_verification' => 'legal_phase',
            'rate_pf' => 'rate_pf_phase',
            'sanction' => 'sanction_phase',
            'docket' => 'docket_phase',
            'esign' => 'esign_phase',
            default => null,
        };
    }

    /**
     * Perform the reset via the shared service and echo its log lines.
     */
    private function resetToOption(array $option): void
    {
        $this->line("<fg=white;options=bold>Resetting: {$option['label']}</>");

        $log = app(LoanStageService::class)->resetToStage(
            $this->loan,
            $option['stage_key'],
            $option['phase'],
            $option['variant'] ?? null
        );

        foreach ($log as $line) {
            $this->line("  <fg=green>→</> {$line}");
        }
    }
}
