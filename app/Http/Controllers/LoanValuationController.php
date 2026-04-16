<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\LoanDetail;
use App\Services\LoanStageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LoanValuationController extends Controller
{
    public function show(LoanDetail $loan)
    {
        $valuations = $loan->valuationDetails;

        return view('loans.valuation', compact('loan', 'valuations'));
    }

    public function showMap(LoanDetail $loan)
    {
        $valuations = $loan->valuationDetails;

        return view('loans.valuation-map', compact('loan', 'valuations'));
    }

    public function searchLocation(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['q' => 'required|string|max:255']);

        $query = trim($request->q);

        $response = Http::withHeaders([
            'User-Agent' => 'SHF-LoanManagement/1.0',
        ])->get('https://nominatim.openstreetmap.org/search', [
            'q' => $query,
            'format' => 'json',
            'addressdetails' => 1,
            'limit' => 5,
            'countrycodes' => 'in',
        ]);

        $results = collect($response->successful() ? $response->json() : []);

        // Retry with ", India" suffix if no results (improves locality searches)
        if ($results->isEmpty() && ! str_contains(strtolower($query), 'india')) {
            $retryResponse = Http::withHeaders([
                'User-Agent' => 'SHF-LoanManagement/1.0',
            ])->get('https://nominatim.openstreetmap.org/search', [
                'q' => $query . ', India',
                'format' => 'json',
                'addressdetails' => 1,
                'limit' => 5,
            ]);

            if ($retryResponse->successful()) {
                $results = collect($retryResponse->json());
            }
        }

        $mapped = $results->map(fn ($item) => [
            'lat' => $item['lat'],
            'lng' => $item['lon'],
            'name' => $item['display_name'] ?? '',
        ])->values();

        return response()->json(['results' => $mapped]);
    }

    public function reverseGeocode(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $response = Http::withHeaders([
            'User-Agent' => 'SHF-LoanManagement/1.0',
        ])->get('https://nominatim.openstreetmap.org/reverse', [
            'lat' => $request->lat,
            'lon' => $request->lng,
            'format' => 'json',
            'addressdetails' => 1,
            'zoom' => 18,
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Geocoding failed'], 500);
        }

        $data = $response->json();
        $addr = $data['address'] ?? [];

        // Landmark: nearby place/building + road + area (short, recognizable)
        $place = $addr['amenity'] ?? $addr['building'] ?? $addr['shop'] ?? $addr['tourism']
            ?? $addr['leisure'] ?? $addr['office'] ?? $addr['man_made']
            ?? $addr['house_number'] ?? $addr['residential'] ?? $addr['hamlet'] ?? '';
        $road = $addr['road'] ?? $addr['pedestrian'] ?? $addr['footway'] ?? '';
        $area = $addr['suburb'] ?? $addr['neighbourhood'] ?? $addr['quarter']
            ?? $addr['village'] ?? $addr['town'] ?? $addr['city_district'] ?? '';
        $city = $addr['city'] ?? $addr['town'] ?? $addr['county'] ?? '';

        $landmarkParts = [];
        if ($place) {
            $landmarkParts[] = 'Near ' . $place;
        }
        if ($road) {
            $landmarkParts[] = $road;
        }
        if ($area) {
            $landmarkParts[] = $area;
        }
        // Fallback: if still empty, use road + city or display_name snippet
        if (empty($landmarkParts) && $city) {
            $landmarkParts[] = $city;
        }
        if (empty($landmarkParts) && ! empty($data['display_name'])) {
            // Take first 2 parts of display_name as landmark
            $displayParts = array_slice(explode(', ', $data['display_name']), 0, 3);
            $landmarkParts = $displayParts;
        }

        // Address: road, area, city, state, pincode (structured, no country)
        $state = $addr['state'] ?? '';
        $postcode = $addr['postcode'] ?? '';

        $addressParts = [];
        if ($road) {
            $addressParts[] = $road;
        }
        if ($area) {
            $addressParts[] = $area;
        }
        if ($city) {
            $addressParts[] = $city;
        }
        if ($state) {
            $addressParts[] = $state;
        }
        if ($postcode) {
            $addressParts[] = $postcode;
        }

        return response()->json([
            'landmark' => implode(', ', $landmarkParts),
            'address' => implode(', ', $addressParts),
        ]);
    }

    public function store(Request $request, LoanDetail $loan)
    {
        if (! in_array($loan->status, ['active', 'on_hold'])) {
            return redirect()->route('loans.stages', $loan)->with('error', 'Loan is '.ucfirst($loan->status).'. Changes are not allowed.');
        }

        $validated = $request->validate([
            'valuation_type' => 'required|in:property',
            'property_type' => 'required|string|max:100',
            'property_address' => 'nullable|string|max:1000',
            'landmark' => 'required|string|max:255',
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
