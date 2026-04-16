<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DisbursementDetail;
use App\Models\LoanDetail;
use Illuminate\Support\Facades\DB;

class DisbursementService
{
    public function __construct(
        private LoanStageService $stageService,
    ) {}

    public function processDisbursement(LoanDetail $loan, array $data): DisbursementDetail
    {
        return DB::transaction(function () use ($loan, $data) {
            $disbursement = DisbursementDetail::updateOrCreate(
                ['loan_id' => $loan->id],
                $data,
            );

            // For fund_transfer, refresh the relationship so handleStageCompletion can skip OTC
            if ($disbursement->disbursement_type === 'fund_transfer') {
                $loan->setRelation('disbursement', $disbursement);
            }

            // Complete disbursement stage — handleStageCompletion handles the rest
            $this->stageService->updateStageStatus($loan, 'disbursement', 'completed', auth()->id());

            ActivityLog::log('process_disbursement', $disbursement, [
                'loan_number' => $loan->loan_number,
                'type' => $disbursement->disbursement_type,
                'amount' => $disbursement->amount_disbursed,
            ]);

            // Notify if loan completed (fund_transfer skips OTC → completes in handleStageCompletion)
            $loan->refresh();
            if ($loan->status === LoanDetail::STATUS_COMPLETED) {
                app(NotificationService::class)->notifyLoanCompleted($loan);
            }

            return $disbursement;
        });
    }
}
