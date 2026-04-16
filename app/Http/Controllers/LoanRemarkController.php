<?php

namespace App\Http\Controllers;

use App\Models\LoanDetail;
use App\Services\RemarkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanRemarkController extends Controller
{
    public function __construct(
        private RemarkService $remarkService,
    ) {}

    public function index(Request $request, LoanDetail $loan): JsonResponse
    {
        $stageKey = $request->get('stage_key');
        $remarks = $this->remarkService->getRemarks($loan->id, $stageKey);

        return response()->json([
            'remarks' => $remarks->map(fn ($r) => [
                'id' => $r->id,
                'remark' => $r->remark,
                'user_name' => $r->user->name,
                'stage_key' => $r->stage_key,
                'created_at' => $r->created_at->diffForHumans(),
                'created_at_full' => $r->created_at->format('d M Y H:i'),
            ]),
        ]);
    }

    public function store(Request $request, LoanDetail $loan): JsonResponse
    {
        $validated = $request->validate([
            'remark' => 'required|string|max:5000',
            'stage_key' => 'nullable|string|max:50',
        ]);

        $remark = $this->remarkService->addRemark(
            $loan->id,
            auth()->id(),
            $validated['remark'],
            $validated['stage_key'] ?? null,
        );

        return response()->json([
            'success' => true,
            'remark' => [
                'id' => $remark->id,
                'remark' => $remark->remark,
                'user_name' => auth()->user()->name,
                'stage_key' => $remark->stage_key,
                'created_at' => $remark->created_at->diffForHumans(),
            ],
        ]);
    }
}
