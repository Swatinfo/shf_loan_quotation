<?php

namespace App\Services;

use App\Models\LoanDetail;
use Illuminate\Support\Collection;

class LoanTimelineService
{
    /**
     * Build a complete lifecycle timeline for a loan.
     * Merges: quotation, loan creation, stage changes, transfers, queries, remarks, disbursement.
     */
    public function getTimeline(LoanDetail $loan): Collection
    {
        $entries = collect();

        // 1. Quotation created (if converted from quotation)
        if ($loan->quotation) {
            $entries->push([
                'type' => 'quotation_created',
                'date' => $loan->quotation->created_at,
                'title' => 'Quotation Created',
                'description' => "Quotation #{$loan->quotation->id} created for {$loan->customer_name}",
                'user' => $loan->quotation->user?->name ?? '—',
                'icon' => 'document',
                'color' => 'secondary',
            ]);

            $entries->push([
                'type' => 'converted',
                'date' => $loan->created_at,
                'title' => 'Converted to Loan',
                'description' => "Quotation converted to Loan #{$loan->loan_number}",
                'user' => $loan->creator?->name ?? '—',
                'icon' => 'arrow-right',
                'color' => 'primary',
            ]);
        } else {
            $entries->push([
                'type' => 'loan_created',
                'date' => $loan->created_at,
                'title' => 'Loan Created',
                'description' => "Loan #{$loan->loan_number} created directly",
                'user' => $loan->creator?->name ?? '—',
                'icon' => 'plus',
                'color' => 'primary',
            ]);
        }

        // 2. Stage assignments (started + completed)
        $loan->loadMissing('stageAssignments.stage');
        foreach ($loan->stageAssignments as $sa) {
            if ($sa->started_at) {
                $entries->push([
                    'type' => 'stage_started',
                    'date' => $sa->started_at,
                    'title' => ($sa->stage?->stage_name_en ?? $sa->stage_key) . ' — Started',
                    'description' => $sa->assignee ? "Assigned to {$sa->assignee->name}" : 'Started',
                    'user' => $sa->assignee?->name ?? '—',
                    'icon' => 'play',
                    'color' => 'primary',
                ]);
            }

            if ($sa->completed_at && in_array($sa->status, ['completed', 'skipped'])) {
                $entries->push([
                    'type' => 'stage_' . $sa->status,
                    'date' => $sa->completed_at,
                    'title' => ($sa->stage?->stage_name_en ?? $sa->stage_key) . ' — ' . ucfirst($sa->status),
                    'description' => $sa->completedByUser ? "By {$sa->completedByUser->name}" : '',
                    'user' => $sa->completedByUser?->name ?? '—',
                    'icon' => $sa->status === 'completed' ? 'check' : 'skip',
                    'color' => $sa->status === 'completed' ? 'success' : 'warning',
                ]);
            }
        }

        // 3. Transfers
        $loan->loadMissing('stageTransfers.fromUser', 'stageTransfers.toUser');
        foreach ($loan->stageTransfers as $transfer) {
            $entries->push([
                'type' => 'transfer',
                'date' => $transfer->created_at,
                'title' => 'Stage Transferred',
                'description' => "{$transfer->fromUser?->name} → {$transfer->toUser?->name}"
                    . ($transfer->reason ? " — {$transfer->reason}" : ''),
                'user' => $transfer->fromUser?->name ?? '—',
                'icon' => 'transfer',
                'color' => 'info',
            ]);
        }

        // 4. Queries
        $loan->loadMissing('stageQueries.raisedByUser', 'stageQueries.responses.respondedByUser');
        foreach ($loan->stageQueries as $query) {
            $entries->push([
                'type' => 'query_raised',
                'date' => $query->created_at,
                'title' => 'Query Raised',
                'description' => \Illuminate\Support\Str::limit($query->query_text, 100),
                'user' => $query->raisedByUser?->name ?? '—',
                'icon' => 'question',
                'color' => 'warning',
            ]);

            foreach ($query->responses as $response) {
                $entries->push([
                    'type' => 'query_response',
                    'date' => $response->created_at,
                    'title' => 'Query Response',
                    'description' => \Illuminate\Support\Str::limit($response->response_text, 100),
                    'user' => $response->respondedByUser?->name ?? '—',
                    'icon' => 'reply',
                    'color' => 'info',
                ]);
            }
        }

        // 5. Remarks
        $loan->loadMissing('remarks.user');
        foreach ($loan->remarks as $remark) {
            $entries->push([
                'type' => 'remark',
                'date' => $remark->created_at,
                'title' => 'Remark' . ($remark->stage_key ? " ({$remark->stage_key})" : ''),
                'description' => \Illuminate\Support\Str::limit($remark->remark, 100),
                'user' => $remark->user?->name ?? '—',
                'icon' => 'note',
                'color' => 'secondary',
            ]);
        }

        // 6. Rejection
        if ($loan->status === 'rejected' && $loan->rejected_at) {
            $rejectedBy = $loan->rejected_by ? \App\Models\User::find($loan->rejected_by)?->name : '—';
            $entries->push([
                'type' => 'rejected',
                'date' => $loan->rejected_at,
                'title' => 'Loan Rejected',
                'description' => "At stage '{$loan->rejected_stage}' — {$loan->rejection_reason}",
                'user' => $rejectedBy,
                'icon' => 'x',
                'color' => 'danger',
            ]);
        }

        // 7. Disbursement
        if ($loan->disbursement) {
            $entries->push([
                'type' => 'disbursement',
                'date' => $loan->disbursement->created_at,
                'title' => 'Disbursement Processed',
                'description' => ucfirst(str_replace('_', ' ', $loan->disbursement->disbursement_type))
                    . ($loan->disbursement->amount_disbursed ? " — ₹ " . number_format($loan->disbursement->amount_disbursed) : ''),
                'user' => '—',
                'icon' => 'cash',
                'color' => 'success',
            ]);
        }

        // 8. Completion
        if ($loan->status === 'completed') {
            $entries->push([
                'type' => 'completed',
                'date' => $loan->updated_at,
                'title' => 'Loan Completed',
                'description' => "All stages finished. Loan #{$loan->loan_number} completed.",
                'user' => '—',
                'icon' => 'flag',
                'color' => 'success',
            ]);
        }

        return $entries->sortBy('date')->values();
    }
}
