<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankCharge;
use App\Services\ConfigService;

class ConfigApiController extends Controller
{
    public function __construct(
        protected ConfigService $configService
    ) {}

    /**
     * Public config endpoint for PWA/offline mode.
     * Returns config JSON without authentication.
     */
    public function public()
    {
        $config = $this->configService->load();

        // Also include bank charges as array
        $config['bankCharges'] = BankCharge::all()->map(function ($charge) {
            return [
                'bank_name' => $charge->bank_name,
                'pf' => $charge->pf,
                'admin' => $charge->admin,
                'stamp_notary' => $charge->stamp_notary,
                'registration_fee' => $charge->registration_fee,
                'advocate' => $charge->advocate,
                'tc' => $charge->tc,
                'extra1_name' => $charge->extra1_name ?? '',
                'extra1_amt' => $charge->extra1_amt ?? 0,
                'extra2_name' => $charge->extra2_name ?? '',
                'extra2_amt' => $charge->extra2_amt ?? 0,
            ];
        })->values();

        return response()->json($config);
    }
}
