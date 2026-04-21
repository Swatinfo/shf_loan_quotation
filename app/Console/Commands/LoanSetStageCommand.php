<?php

namespace App\Console\Commands;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\ProductStage;
use App\Models\StageAssignment;
use App\Models\User;
use Illuminate\Console\Command;

class LoanSetStageCommand extends Command
{
    protected $signature = 'loan:set-stage';

    protected $description = 'Interactively set a loan to a specific stage/phase for testing';

    /** @var array<int, array{stage_key: string, phase: ?int, label: string, role: string, section: string}> */
    private array $menuOptions = [];

    private ?LoanDetail $loan = null;

    /** Resolved user IDs keyed by role */
    private array $users = [];

    /** Parallel sub-stage keys */
    private const PARALLEL_SUBS = ['app_number', 'bsm_osv', 'legal_verification', 'technical_valuation', 'sanction_decision'];

    /** Ordered stage keys for sequential flow (includes parallel subs in sequence) */
    private const STAGE_ORDER = [
        'inquiry', 'document_selection', 'document_collection',
        'app_number', 'bsm_osv', 'legal_verification', 'technical_valuation', 'sanction_decision',
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
        $loanId = $this->ask('Enter Loan ID');
        $this->loan = LoanDetail::with(['bank', 'product', 'branch', 'advisor', 'creator', 'stageAssignments'])
            ->find($loanId);

        if (! $this->loan) {
            $this->error("Loan ID {$loanId} not found.");

            return self::FAILURE;
        }

        $this->resolveUsers();
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

    private function resolveUsers(): void
    {
        $loan = $this->loan;
        $taskOwnerId = $loan->assigned_advisor ?? $loan->created_by;
        $this->users['task_owner'] = $taskOwnerId;

        // Bank employee: product stage config → bank default → any BE with matching bank
        $this->users['bank_employee'] = $this->findBankEmployee();

        // Office employee: product stage config → branch default OE → any OE in branch
        $this->users['office_employee'] = $this->findOfficeEmployee();

        // Branch manager for loan's branch
        $this->users['branch_manager'] = $this->findUserByRoleInBranch('branch_manager');

        // BDH for loan's branch
        $this->users['bdh'] = $this->findUserByRoleInBranch('bdh');
    }

    private function findBankEmployee(): ?int
    {
        $loan = $this->loan;

        // Product stage config
        if ($loan->product_id) {
            $stages = ProductStage::where('product_id', $loan->product_id)
                ->whereHas('stage', fn ($q) => $q->where('stage_key', 'bsm_osv'))
                ->first();
            if ($stages) {
                $branch = $loan->branch_id ? Branch::with('location.parent')->find($loan->branch_id) : null;
                $userId = $stages->getUserForLocation($loan->branch_id, $branch?->location_id, $branch?->location?->parent_id);
                if ($userId && User::where('id', $userId)->where('is_active', true)->exists()) {
                    return $userId;
                }
            }
        }

        // Bank default employee for city
        if ($loan->bank_id) {
            $cityId = $loan->branch_id ? Branch::find($loan->branch_id)?->location_id : null;
            $bank = Bank::find($loan->bank_id);
            if ($bank) {
                $defaultBEId = $bank->getDefaultEmployeeForCity($cityId);
                if ($defaultBEId && User::where('id', $defaultBEId)->where('is_active', true)->exists()) {
                    return $defaultBEId;
                }
            }
        }

        // Fallback: any active bank employee
        return User::whereHas('roles', fn ($q) => $q->where('slug', 'bank_employee'))
            ->where('is_active', true)
            ->first()?->id;
    }

    private function findOfficeEmployee(): ?int
    {
        $loan = $this->loan;

        if ($loan->branch_id) {
            $defaultOE = User::whereHas('branches', fn ($q) => $q->where('branches.id', $loan->branch_id)
                ->where('user_branches.is_default_office_employee', true))
                ->where('is_active', true)->first();
            if ($defaultOE) {
                return $defaultOE->id;
            }

            $branchOE = User::whereHas('roles', fn ($q) => $q->where('slug', 'office_employee'))
                ->whereHas('branches', fn ($q) => $q->where('branches.id', $loan->branch_id))
                ->where('is_active', true)->first();
            if ($branchOE) {
                return $branchOE->id;
            }
        }

        return User::whereHas('roles', fn ($q) => $q->where('slug', 'office_employee'))
            ->where('is_active', true)->first()?->id;
    }

    private function findUserByRoleInBranch(string $role): ?int
    {
        $loan = $this->loan;
        if (! $loan->branch_id) {
            return User::whereHas('roles', fn ($q) => $q->where('slug', $role))
                ->where('is_active', true)->first()?->id;
        }

        return User::whereHas('roles', fn ($q) => $q->where('slug', $role))
            ->whereHas('branches', fn ($q) => $q->where('branches.id', $loan->branch_id))
            ->where('is_active', true)->first()?->id;
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
            'legal_verification' => (($notes['legal_phase'] ?? '') !== '3' ? 'phase is '.($notes['legal_phase'] ?? 'none').', expected 3' : null),
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

    private function resetToOption(array $option): void
    {
        $stageKey = $option['stage_key'];
        $phase = $option['phase'];
        $role = $option['role'];
        $variant = $option['variant'] ?? null;
        $userId = $this->users[$role] ?? null;
        $stageName = self::STAGE_NAMES[$stageKey] ?? $stageKey;

        $this->line("<fg=white;options=bold>Resetting: {$option['label']}</>");

        // 1. Set target stage to in_progress with correct phase and assignee
        $assignment = $this->loan->stageAssignments->firstWhere('stage_key', $stageKey);
        if ($assignment) {
            $updateData = [
                'status' => 'in_progress',
                'assigned_to' => $userId,
                'started_at' => now(),
                'completed_at' => null,
                'completed_by' => null,
            ];

            // Build notes for the target phase
            $notesData = $this->buildTargetNotes($stageKey, $phase, $variant, $assignment);
            $updateData['notes'] = ! empty($notesData) ? json_encode($notesData) : null;

            $assignment->update($updateData);
            $this->line("  → <fg=green>{$stageName}</>: in_progress, phase ".($phase ?? '—').', assigned to '.$this->userName($userId));
        }

        // 2. Reset loan status and clear stage-dependent fields
        $currentStage = in_array($stageKey, self::PARALLEL_SUBS) ? 'parallel_processing' : $stageKey;
        $loanUpdate = [
            'current_stage' => $currentStage,
            'status' => 'active',
            'rejected_at' => null,
            'rejected_by' => null,
            'rejected_stage' => null,
            'rejection_reason' => null,
        ];

        $targetIdx = array_search($stageKey, self::STAGE_ORDER);

        // Clear is_sanctioned if resetting before sanction_decision
        if ($targetIdx <= array_search('sanction_decision', self::STAGE_ORDER)) {
            $loanUpdate['is_sanctioned'] = false;
        }

        // Clear application_number if resetting before or at app_number
        if ($targetIdx <= array_search('app_number', self::STAGE_ORDER)) {
            $loanUpdate['application_number'] = null;
        }

        // Clear expected_docket_date if resetting before or at sanction
        if ($targetIdx <= array_search('sanction', self::STAGE_ORDER)) {
            $loanUpdate['expected_docket_date'] = null;
        }

        $this->loan->update($loanUpdate);
        $this->line("  → Loan status set to <fg=green>active</>, current_stage: <fg=cyan>{$currentStage}</>");

        // 3. Clear related data for reset stages
        $this->clearRelatedData($stageKey, $targetIdx);

        // 4. Handle parallel processing parent
        if (in_array($stageKey, self::PARALLEL_SUBS)) {
            $ppAssignment = $this->loan->stageAssignments->firstWhere('stage_key', 'parallel_processing');
            if ($ppAssignment) {
                $ppAssignment->update([
                    'status' => 'in_progress',
                    'started_at' => $ppAssignment->started_at ?? now(),
                    'completed_at' => null,
                    'completed_by' => null,
                ]);
            }

            // Reset parallel subs that come after this one
            $this->resetParallelSubsAfter($stageKey);
        }

        // 5. Reset all stages after target
        $this->resetStagesAfter($stageKey);

        // 6. Recalculate progress
        app(\App\Services\LoanStageService::class)->recalculateProgress($this->loan);
        $this->line('  → Loan progress recalculated');
    }

    private function clearRelatedData(string $stageKey, int $targetIdx): void
    {
        $loan = $this->loan;

        // Clear disbursement record if resetting before or at disbursement
        if ($targetIdx <= array_search('disbursement', self::STAGE_ORDER)) {
            $deleted = $loan->disbursement()->delete();
            if ($deleted) {
                $this->line('  → Disbursement record cleared');
            }
        }

        // Clear valuation details if resetting before or at technical_valuation
        if ($targetIdx <= array_search('technical_valuation', self::STAGE_ORDER)) {
            $deleted = $loan->valuationDetails()->delete();
            if ($deleted) {
                $this->line('  → Valuation details cleared');
            }
        }
    }

    private function buildTargetNotes(string $stageKey, ?int $phase, ?string $variant, StageAssignment $assignment): array
    {
        $existing = $assignment->getNotesData();
        $phaseKey = $this->getPhaseNoteKey($stageKey);

        // For phased stages, keep notes from prior phases but clear current phase data
        if ($phaseKey && $phase) {
            $existing[$phaseKey] = (string) $phase;

            // Clear phase-specific fields that the current user needs to fill
            $clearFields = $this->getPhaseFieldsToClear($stageKey, $phase);
            foreach ($clearFields as $field) {
                unset($existing[$field]);
            }

            return $existing;
        }

        // Variant-specific notes
        if ($stageKey === 'sanction_decision') {
            // Clear decision data so user can decide
            unset($existing['decision_action'], $existing['decision_remarks'], $existing['rejection_reason'], $existing['decided_by']);
            if ($variant === 'escalated_bm') {
                $existing['escalated_to'] = 'branch_manager';
            } elseif ($variant === 'escalated_bdh') {
                $existing['escalated_to'] = 'bdh';
            }

            return $existing;
        }

        if ($stageKey === 'otc_clearance') {
            unset($existing['handover_date']);

            return $existing;
        }

        // Default: clear all notes for fresh start
        return [];
    }

    private function getPhaseFieldsToClear(string $stageKey, int $phase): array
    {
        return match ($stageKey) {
            'bsm_osv' => match ($phase) {
                2, 3 => ['bsm_status', 'bsm_remarks'],
                4 => ['stageRemarks'],
                default => [],
            },
            'legal_verification' => match ($phase) {
                2 => ['legal_status', 'legal_remarks'],
                3 => ['stageRemarks'],
                default => [],
            },
            'rate_pf' => match ($phase) {
                2 => ['interest_rate', 'repo_rate', 'bank_rate', 'rate_valid_until'],
                3 => ['pf_type', 'pf_percentage', 'pf_amount', 'gst_percentage', 'total_pf', 'total_admin_charges'],
                default => [],
            },
            'sanction' => match ($phase) {
                2 => [],
                3 => ['sanction_date', 'sanction_conditions'],
                default => [],
            },
            'docket' => match ($phase) {
                2 => ['login_date', 'sanctioned_amount', 'sanctioned_rate', 'tenure_months', 'emi_amount'],
                default => [],
            },
            'esign' => match ($phase) {
                2, 3 => [],
                4 => ['stageRemarks'],
                default => [],
            },
            default => [],
        };
    }

    private function resetParallelSubsAfter(string $stageKey): void
    {
        $idx = array_search($stageKey, self::PARALLEL_SUBS);
        if ($idx === false) {
            return;
        }

        $subsToReset = array_slice(self::PARALLEL_SUBS, $idx + 1);
        foreach ($subsToReset as $subKey) {
            $subAssignment = $this->loan->stageAssignments->firstWhere('stage_key', $subKey);
            if ($subAssignment) {
                $subAssignment->update([
                    'status' => 'pending',
                    'assigned_to' => null,
                    'started_at' => null,
                    'completed_at' => null,
                    'completed_by' => null,
                    'notes' => null,
                ]);
                $subName = self::STAGE_NAMES[$subKey] ?? $subKey;
                $this->line("  → <fg=gray>{$subName}</>: reset to pending");
            }
        }
    }

    private function resetStagesAfter(string $stageKey): void
    {
        $idx = array_search($stageKey, self::STAGE_ORDER);
        if ($idx === false) {
            return;
        }

        // For parallel subs, also reset everything after parallel processing
        $resetFrom = $idx + 1;
        if (in_array($stageKey, self::PARALLEL_SUBS)) {
            // Stages after parallel subs = from rate_pf onward (after sanction_decision)
            $sdIdx = array_search('sanction_decision', self::STAGE_ORDER);
            $resetFrom = $sdIdx + 1;
        }

        $stagesToReset = array_slice(self::STAGE_ORDER, $resetFrom);
        foreach ($stagesToReset as $resetKey) {
            $resetAssignment = $this->loan->stageAssignments->firstWhere('stage_key', $resetKey);
            if ($resetAssignment) {
                $resetAssignment->update([
                    'status' => 'pending',
                    'assigned_to' => null,
                    'started_at' => null,
                    'completed_at' => null,
                    'completed_by' => null,
                    'notes' => null,
                ]);
                $resetName = self::STAGE_NAMES[$resetKey] ?? $resetKey;
                $this->line("  → <fg=gray>{$resetName}</>: reset to pending");
            }
        }

        // Also reset parallel_processing parent if we're resetting a main stage
        if (! in_array($stageKey, self::PARALLEL_SUBS) && $idx > array_search('sanction_decision', self::STAGE_ORDER)) {
            // Main stage after parallel — don't touch parallel parent
        } else {
            // If target is before parallel, reset the parent too
            if ($idx < array_search('app_number', self::STAGE_ORDER)) {
                $ppAssignment = $this->loan->stageAssignments->firstWhere('stage_key', 'parallel_processing');
                if ($ppAssignment) {
                    $ppAssignment->update([
                        'status' => 'pending',
                        'assigned_to' => null,
                        'started_at' => null,
                        'completed_at' => null,
                        'completed_by' => null,
                        'notes' => null,
                    ]);
                    $this->line('  → <fg=gray>Parallel Processing</>: reset to pending');
                }
            }
        }
    }
}
