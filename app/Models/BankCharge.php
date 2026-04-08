<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankCharge extends Model
{
    protected $fillable = [
        'bank_name', 'pf', 'admin', 'stamp_notary', 'registration_fee', 'advocate', 'tc',
        'extra1_name', 'extra1_amt', 'extra2_name', 'extra2_amt',
    ];

    protected $casts = [
        'pf' => 'decimal:2',
    ];
}
