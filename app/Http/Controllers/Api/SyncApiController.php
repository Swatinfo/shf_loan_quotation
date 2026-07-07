<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\QuotationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SyncApiController extends Controller
{
    public function __construct(
        private QuotationService $quotationService,
    ) {}

    /**
     * Batch sync offline quotations.
     * POST: { "quotations": [ ...payload... ] }
     */
    public function sync(Request $request): \Illuminate\Http\JsonResponse
    {
        $quotations = $request->input('quotations', []);

        if (empty($quotations)) {
            return response()->json(['error' => 'No quotations to sync'], 400);
        }

        $user = Auth::user();
        $results = [];

        foreach ($quotations as $idx => $payload) {
            // Auto-fill prepared by from auth user if not provided (matches QuotationController)
            if (empty($payload['preparedByName'])) {
                $payload['preparedByName'] = $user->name;
            }
            if (empty($payload['preparedByMobile'])) {
                $payload['preparedByMobile'] = $user->phone ?? '';
            }

            $result = $this->quotationService->generate($payload, $user->id);

            $saved = ! empty($result['success']) && ! empty($result['quotation']);

            if ($saved) {
                ActivityLog::log('create_quotation', $result['quotation'], [
                    'customer_name' => $payload['customerName'] ?? '',
                    'loan_amount' => $payload['loanAmount'] ?? 0,
                    'filename' => $result['quotation']->pdf_filename ?? '',
                    'source' => 'offline_sync',
                ]);
            }

            $results[] = [
                'index' => $idx,
                'success' => $saved,
                'filename' => $result['quotation']?->pdf_filename ?? ($result['filename'] ?? null),
                'error' => $result['error'] ?? null,
            ];
        }

        return response()->json(['success' => true, 'results' => $results]);
    }
}
