<?php

namespace Database\Seeders;

use App\Models\BankCharge;
use Illuminate\Database\Seeder;

class BankChargesSeeder extends Seeder
{
    public function run(): void
    {
        $legacyFile = base_path('legacy/proposal_charges.json');

        if (file_exists($legacyFile)) {
            $charges = json_decode(file_get_contents($legacyFile), true);

            foreach ($charges as $bankName => $data) {
                BankCharge::updateOrCreate(
                    ['bank_name' => $bankName],
                    [
                        'pf' => 1.00, // Legacy values are corrupted calculated amounts; use safe default percentage
                        'admin' => $data['admin'] ?? 0,
                        'stamp_notary' => $data['stamp'] ?? 0,
                        'registration_fee' => $data['notary'] ?? 0,
                        'advocate' => $data['advocate'] ?? 0,
                        'tc' => $data['tc'] ?? 0,
                        'extra1_name' => $data['extra1Name'] ?? '',
                        'extra1_amt' => $data['extra1Amt'] ?? 0,
                        'extra2_name' => $data['extra2Name'] ?? '',
                        'extra2_amt' => $data['extra2Amt'] ?? 0,
                    ]
                );
            }
        } else {
            // Default bank charges if no legacy file
            $defaults = [
                ['bank_name' => 'HDFC Bank', 'pf' => 1.50, 'admin' => 2360, 'stamp_notary' => 1000, 'registration_fee' => 500, 'advocate' => 1000, 'tc' => 500],
                ['bank_name' => 'ICICI Bank', 'pf' => 1.50, 'admin' => 2950, 'stamp_notary' => 1200, 'registration_fee' => 600, 'advocate' => 1100, 'tc' => 600],
                ['bank_name' => 'Axis Bank', 'pf' => 1.50, 'admin' => 5900, 'stamp_notary' => 2000, 'registration_fee' => 2000, 'advocate' => 2000, 'tc' => 2500],
                ['bank_name' => 'Kotak Mahindra Bank', 'pf' => 1.50, 'admin' => 5000, 'stamp_notary' => 1000, 'registration_fee' => 500, 'advocate' => 1000, 'tc' => 1000],
            ];

            foreach ($defaults as $charge) {
                BankCharge::updateOrCreate(['bank_name' => $charge['bank_name']], $charge);
            }
        }
    }
}
