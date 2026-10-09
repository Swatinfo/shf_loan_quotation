<?php

use App\Services\NumberToWordsService;

if (! function_exists('inr')) {
    /**
     * Indian-grouped number, no currency symbol (e.g. 58,00,000 — NOT 5,800,000).
     * Use for amounts already prefixed with ₹ in the markup: "₹ {{ inr($amt) }}".
     */
    function inr($num): string
    {
        return NumberToWordsService::formatIndianNumber((int) round((float) $num));
    }
}

if (! function_exists('inrc')) {
    /**
     * Indian-grouped amount WITH the ₹ symbol (non-breaking space): "₹ 58,00,000".
     */
    function inrc($num): string
    {
        return NumberToWordsService::formatCurrency($num);
    }
}
