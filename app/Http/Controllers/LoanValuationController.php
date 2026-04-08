<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\LoanDetail;
use App\Models\ValuationDetail;
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
        $type = $request->input('valuation_type');

        $rules = [
            'valuation_type' => 'required|in:property,vehicle,business',
            'market_value' => 'required|numeric|min:0.01|max:100000000000',
            'government_value' => 'required|numeric|min:0.01|max:100000000000',
            'valuation_date' => 'required|date_format:d/m/Y',
            'valuator_name' => 'required|string|max:255',
            'valuator_report_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:5000',
        ];

        // Type-specific validation
        if ($type === 'property') {
            $rules['property_type'] = 'required|string|max:100';
            $rules['property_area'] = 'required|string|max:100';
            $rules['property_address'] = 'required|string|max:1000';
        } elseif ($type === 'vehicle') {
            $rules['property_type'] = 'required|string|max:100'; // vehicle type
            $rules['property_area'] = 'nullable|string|max:100'; // registration no (optional)
            $rules['property_address'] = 'nullable|string|max:1000';
        } elseif ($type === 'business') {
            $rules['property_type'] = 'required|string|max:100'; // business type
            $rules['property_area'] = 'nullable|string|max:100';
            $rules['property_address'] = 'nullable|string|max:1000';
        }

        $validated = $request->validate($rules);

        $validated['valuation_date'] = \Carbon\Carbon::createFromFormat('d/m/Y', $validated['valuation_date'])->toDateString();

        $valuation = $loan->valuationDetails()->updateOrCreate(
            ['loan_id' => $loan->id, 'valuation_type' => $validated['valuation_type']],
            $validated,
        );

        ActivityLog::log('save_valuation', $valuation, [
            'loan_number' => $loan->loan_number,
            'valuation_type' => $validated['valuation_type'],
        ]);

        // Auto-complete the corresponding valuation stage
        $stageKey = match ($validated['valuation_type']) {
            'property' => 'property_valuation',
            'vehicle' => 'vehicle_valuation',
            'business' => 'business_valuation',
            default => 'technical_valuation',
        };

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
