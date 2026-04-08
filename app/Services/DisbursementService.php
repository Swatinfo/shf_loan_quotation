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

            if ($disbursement->isComplete()) {
                $this->stageService->updateStageStatus($loan, 'disbursement', 'completed', auth()->id());
                $loan->update(['status' => LoanDetail::STATUS_COMPLETED]);

                ActivityLog::log('process_disbursement', $disbursement, [
                    'loan_number' => $loan->loan_number,
                    'type' => $disbursement->disbursement_type,
                    'amount' => $disbursement->amount_disbursed,
                ]);

                app(NotificationService::class)->notifyLoanCompleted($loan);
            } else {
                ActivityLog::log('save_disbursement_pending_otc', $disbursement, [
                    'loan_number' => $loan->loan_number,
                    'otc_branch' => $disbursement->otc_branch,
                ]);
            }

            return $disbursement;
        });
    }

    public function clearOtc(DisbursementDetail $disbursement): DisbursementDetail
    {
        return DB::transaction(function () use ($disbursement) {
            $disbursement->update([
                'otc_cleared' => true,
                'otc_cleared_date' => now()->toDateString(),
                'otc_cleared_by' => auth()->id(),
            ]);

            $loan = $disbursement->loan;
            $this->stageService->updateStageStatus($loan, 'disbursement', 'completed', auth()->id());
            $loan->update(['status' => LoanDetail::STATUS_COMPLETED]);

            ActivityLog::log('otc_cleared', $disbursement, [
                'loan_number' => $loan->loan_number,
            ]);

            app(NotificationService::class)->notifyLoanCompleted($loan);

            return $disbursement->fresh();
        });
    }
}
