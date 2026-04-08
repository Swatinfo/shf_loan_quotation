<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\LoanDetail;
use App\Services\LoanStageService;
use Illuminate\Http\Request;

class LoanValuationController extends Controller
{
    public function show(LoanDetail $loan)
    {
        $valuations = $loan->valuationDetails;

        return view('loans.valuation', compact('loan', 'valuations'));
    }

    public function store(Request $request, LoanDetail $loan)
    {
        $validated = $request->validate([
            'valuation_type' => 'required|in:property',
            'property_type' => 'required|string|max:100',
            'property_address' => 'nullable|string|max:1000',
            'latitude' => 'nullable|string|max:50',
            'longitude' => 'nullable|string|max:50',
            'land_area' => 'required|string|max:100',
            'land_rate' => 'required|numeric|min:0',
            'construction_area' => 'nullable|string|max:100',
            'construction_rate' => 'nullable|numeric|min:0',
            'valuation_date' => 'required|date_format:d/m/Y',
            'valuator_name' => 'required|string|max:255',
            'valuator_report_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:5000',
        ]);

        $validated['valuation_date'] = \Carbon\Carbon::createFromFormat('d/m/Y', $validated['valuation_date'])->toDateString();

        // Calculate valuations
        $landArea = (float) preg_replace('/[^0-9.]/', '', $validated['land_area']);
        $landRate = (float) ($validated['land_rate'] ?? 0);
        $validated['land_valuation'] = (int) round($landArea * $landRate);

        $constructionArea = (float) preg_replace('/[^0-9.]/', '', $validated['construction_area'] ?? '0');
        $constructionRate = (float) ($validated['construction_rate'] ?? 0);
        $validated['construction_valuation'] = (int) round($constructionArea * $constructionRate);

        $validated['final_valuation'] = $validated['land_valuation'] + $validated['construction_valuation'];
        $validated['market_value'] = $validated['final_valuation'];

        $valuation = $loan->valuationDetails()->updateOrCreate(
            ['loan_id' => $loan->id, 'valuation_type' => 'property'],
            $validated,
        );

        ActivityLog::log('save_valuation', $valuation, [
            'loan_number' => $loan->loan_number,
            'valuation_type' => 'property',
        ]);

        // Auto-complete the technical_valuation stage
        $stageKey = 'technical_valuation';

        $stageService = app(LoanStageService::class);
        $assignment = $loan->stageAssignments()->where('stage_key', $stageKey)->first();
        if ($assignment && in_array($assignment->status, ['pending', 'in_progress'])) {
            if ($assignment->status === 'pending') {
                $stageService->updateStageStatus($loan, $stageKey, 'in_progress', auth()->id());
            }
            $stageService->updateStageStatus($loan, $stageKey, 'completed', auth()->id());

            return redirect()->route('loans.stages', $loan)->with('success', 'Valuation saved — stage completed!');
        }

        return redirect()->route('loans.stages', $loan)->with('success', 'Valuation details saved');
    }
}
