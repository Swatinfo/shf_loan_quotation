<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\Product;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        // Banks from config/app-defaults.php
        $banks = [
            ['name' => 'HDFC Bank', 'code' => 'HDFC'],
            ['name' => 'ICICI Bank', 'code' => 'ICICI'],
            ['name' => 'Axis Bank', 'code' => 'AXIS'],
            ['name' => 'Kotak Mahindra Bank', 'code' => 'KOTAK'],
        ];

        foreach ($banks as $bankData) {
            $bank = Bank::updateOrCreate(
                ['name' => $bankData['name']],
                $bankData,
            );

            // Default products per bank (from ourServices in config)
            $products = [
                'Home Loan', 'Mortgage Loan', 'Commercial Loan',
                'Industrial Loan', 'Land Loan', 'Over Draft (OD)',
            ];

            foreach ($products as $productName) {
                Product::updateOrCreate(
                    ['bank_id' => $bank->id, 'name' => $productName],
                    ['bank_id' => $bank->id, 'name' => $productName],
                );
            }
        }

        // Default branch from company info
        Branch::updateOrCreate(
            ['name' => 'Rajkot Main Office'],
            [
                'name' => 'Rajkot Main Office',
                'code' => 'RJK-MAIN',
                'address' => 'OFFICE NO 911, R K PRIME, CIRCLE, next to SILVER HEIGHT, Nehru Nagar Co operative Society, Nana Mava, Rajkot, Gujarat 360004',
                'city' => 'Rajkot',
                'phone' => '+91 99747 89089',
            ],
        );
    }
}
