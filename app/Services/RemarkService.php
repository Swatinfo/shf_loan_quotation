<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\LoanDetail;
use App\Models\Remark;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class RemarkService
{
    public function addRemark(int $loanId, int $userId, string $remark, ?string $stageKey = null): Remark
    {
        $remarkModel = Remark::create([
            'loan_id' => $loanId,
            'user_id' => $userId,
            'remark' => trim($remark),
            'stage_key' => $stageKey,
        ]);

        $loan = LoanDetail::find($loanId);
        ActivityLog::log('add_remark', $remarkModel, [
            'loan_number' => $loan?->loan_number,
            'stage_key' => $stageKey,
            'preview' => Str::limit($remark, 100),
        ]);

        return $remarkModel;
    }

    public function getRemarks(int $loanId, ?string $stageKey = null): Collection
    {
        $query = Remark::where('loan_id', $loanId)->with('user')->latest();

        if ($stageKey !== null) {
            $query->where(function ($q) use ($stageKey) {
                $q->where('stage_key', $stageKey)->orWhereNull('stage_key');
            });
        }

        return $query->get();
    }
}
