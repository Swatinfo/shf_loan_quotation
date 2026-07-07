<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\LoanDetail;
use App\Models\QueryResponse;
use App\Models\StageAssignment;
use App\Models\StageQuery;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StageQueryService
{
    public function raiseQuery(StageAssignment $assignment, string $queryText, int $userId): StageQuery
    {
        $loan = $assignment->loan;
        $recipientId = $this->resolveQueryRecipient($loan, $assignment, $userId);

        $query = StageQuery::create([
            'stage_assignment_id' => $assignment->id,
            'loan_id' => $assignment->loan_id,
            'stage_key' => $assignment->stage_key,
            'query_text' => trim($queryText),
            'raised_by' => $userId,
            'assigned_to_user_id' => $recipientId,
            'status' => 'pending',
        ]);

        $advisorId = $loan->assigned_advisor ?? $loan->created_by;

        ActivityLog::log('raise_query', $query, [
            'loan_number' => $loan->loan_number,
            'stage_key' => $assignment->stage_key,
            'preview' => Str::limit($queryText, 100),
            'recipient_user_id' => $recipientId,
            'notified_advisor_id' => $advisorId,
        ]);

        $this->notifyQueryRaised($query, $loan, $assignment, $recipientId, $advisorId, $userId, $queryText);

        return $query;
    }

    /**
     * Resolve the user who should be assigned a newly raised query.
     *
     * Routing rule: queries are escalated to the internal SHF role responsible
     * for the loan — never to a bank_employee, even when bank_employee currently
     * owns the active phase. Bank-side raisers hitting an office-side phase get
     * routed to the office_employee on that assignment instead of the advisor.
     */
    public function resolveQueryRecipient(LoanDetail $loan, StageAssignment $assignment, int $raiserId): ?int
    {
        $advisorId = $loan->assigned_advisor ?? $loan->created_by;

        $raiser = User::with('roles')->find($raiserId);
        $raiserIsBankEmployee = $raiser ? $raiser->roles->contains('slug', 'bank_employee') : false;

        if ($raiserIsBankEmployee && $assignment->assigned_to) {
            $currentOwner = User::with('roles')->find($assignment->assigned_to);
            if ($currentOwner && $currentOwner->roles->contains('slug', 'office_employee')) {
                return $currentOwner->id;
            }
        }

        return $advisorId;
    }

    /**
     * Fan-out notifications for a freshly raised query.
     *
     * Notifies the resolved recipient + the loan's assigned_advisor (deduped),
     * skipping the raiser themselves so users don't get pinged about their own
     * actions. Wrapped in try/catch — broadcast/push failures must not bubble up
     * and 500 the primary request (lessons.md 2026-04-18 rule).
     */
    private function notifyQueryRaised(StageQuery $query, LoanDetail $loan, StageAssignment $assignment, ?int $recipientId, ?int $advisorId, int $raiserId, string $queryText): void
    {
        $message = "A query was raised for Loan of ({$loan->customer_name}) #{$loan->loan_number} on ".$assignment->stage_key.': '.Str::limit($queryText, 80);
        $advisorMessage = "A query was raised on your loan ({$loan->customer_name}) #{$loan->loan_number} on ".$assignment->stage_key.': '.Str::limit($queryText, 80);

        $targets = collect([
            ['user_id' => $recipientId, 'title' => 'Query Raised', 'body' => $message],
            ['user_id' => $advisorId, 'title' => 'Query Raised on Your Loan', 'body' => $advisorMessage],
        ])
            ->filter(fn ($t) => $t['user_id'] && $t['user_id'] !== $raiserId)
            ->unique('user_id');

        $service = app(NotificationService::class);

        foreach ($targets as $target) {
            try {
                $service->notify(
                    $target['user_id'],
                    $target['title'],
                    $target['body'],
                    'warning',
                    $loan->id,
                    $assignment->stage_key,
                );
            } catch (\Throwable $e) {
                Log::warning('Stage query notification failed', [
                    'query_id' => $query->id,
                    'user_id' => $target['user_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function respondToQuery(StageQuery $query, string $responseText, int $userId): QueryResponse
    {
        $response = QueryResponse::create([
            'stage_query_id' => $query->id,
            'response_text' => trim($responseText),
            'responded_by' => $userId,
        ]);

        $query->update(['status' => 'responded']);

        // Notify the user who raised the query that a response was given
        if ($query->raised_by !== $userId) {
            $message = 'Your query on '.$query->stage_key." for Loan of ({$query->loan->customer_name}) #{$query->loan->loan_number} has been responded to.";

            app(NotificationService::class)->notify(
                $query->raised_by,
                'Query Responded',
                $message,
                'info',
                $query->loan_id,
                $query->stage_key,
            );
        }

        return $response;
    }

    public function resolveQuery(StageQuery $query, int $userId): StageQuery
    {
        $query->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $userId,
        ]);

        $loan = $query->loan;

        ActivityLog::log('resolve_query', $query, [
            'loan_number' => $loan?->loan_number,
            'stage_key' => $query->stage_key,
            'raised_by' => $query->raised_by,
            'resolved_by' => $userId,
        ]);

        // Resolvers other than the raiser (current assignee, admin) close the
        // thread on the raiser's behalf — tell the raiser their query was closed.
        if ($query->raised_by !== $userId && $loan) {
            try {
                $resolverName = User::find($userId)?->name ?? 'another user';

                app(NotificationService::class)->notify(
                    $query->raised_by,
                    'Query Resolved',
                    'Your query on '.$query->stage_key." for Loan of ({$loan->customer_name}) #{$loan->loan_number} was resolved by {$resolverName}.",
                    'info',
                    $query->loan_id,
                    $query->stage_key,
                );
            } catch (\Throwable $e) {
                Log::warning('Stage query resolve notification failed', [
                    'query_id' => $query->id,
                    'user_id' => $query->raised_by,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $query->fresh();
    }

    public function getQueriesForStage(StageAssignment $assignment): Collection
    {
        return $assignment->queries()
            ->with(['raisedByUser', 'responses.respondedByUser'])
            ->latest()
            ->get();
    }
}
