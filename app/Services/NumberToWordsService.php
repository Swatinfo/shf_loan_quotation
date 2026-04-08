<?php

namespace App\Services;

/**
 * Number to Words — Indian numbering system (English + Gujarati)
 * Ported from legacy includes/number-words.php
 */
class NumberToWordsService
{
    private static array $ones = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen'
    ];

    private static array $tens = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'
    ];

    private static array $gujarati = [
        '', 'એક', 'બે', 'ત્રણ', 'ચાર', 'પાંચ', 'છ', 'સાત', 'આઠ', 'નવ',
        'દસ', 'અગિયાર', 'બાર', 'તેર', 'ચૌદ', 'પંદર', 'સોળ', 'સત્તર', 'અઢાર', 'ઓગણીસ',
        'વીસ', 'એકવીસ', 'બાવીસ', 'ત્રેવીસ', 'ચોવીસ', 'પચ્ચીસ', 'છવ્વીસ', 'સત્તાવીસ', 'અઠ્ઠાવીસ', 'ઓગણત્રીસ',
        'ત્રીસ', 'એકત્રીસ', 'બત્રીસ', 'તેત્રીસ', 'ચોત્રીસ', 'પાંત્રીસ', 'છત્રીસ', 'સાડત્રીસ', 'આડત્રીસ', 'ઓગણચાલીસ',
        'ચાલીસ', 'એકતાલીસ', 'બેતાલીસ', 'ત્રેતાલીસ', 'ચુંમાલીસ', 'પિસ્તાલીસ', 'છેતાલીસ', 'સુડતાલીસ', 'અડતાલીસ', 'ઓગણપચાસ',
        'પચાસ', 'એકાવન', 'બાવન', 'ત્રેપન', 'ચોપન', 'પંચાવન', 'છપ્પન', 'સત્તાવન', 'અઠ્ઠાવન', 'ઓગણસાઈઐ',
        'સાઈઐ', 'એકસઠ', 'બાસઠ', 'ત્રેસઠ', 'ચોસઠ', 'પાંસઠ', 'છાસઠ', 'સડસઠ', 'અડસઠ', 'ઓગણસિત્તેર',
        'સિત્તેર', 'એકોતેર', 'બોતેર', 'તોતેર', 'ચુમોતેર', 'પંચોતેર', 'છોતેર', 'સિત્યોતેર', 'ઇઠ્યોતેર', 'ઓગણાએંસી',
        'એંસી', 'એક્યાસી', 'બ્યાસી', 'ત્યાસી', 'ચોર્યાસી', 'પંચાસી', 'છ્યાસી', 'સત્યાસી', 'અઠ્ઠ્યાસી', 'નેવ્યાસી',
        'નેવું', 'એકાણું', 'બાણું', 'ત્રાણું', 'ચોરાણું', 'પંચાણું', 'છન્નું', 'સત્તાણું', 'અઠ્ઠાણું', 'નવ્વાણું',
    ];

    public static function toEnglish(int $num): string
    {
        if ($num === 0) return 'Zero Rupees';

        $result = '';
        if ($num >= 10000000) {
            $result .= self::innerDigitsEn(intdiv($num, 10000000)) . ' Crore ';
            $num %= 10000000;
        }
        if ($num >= 100000) {
            $result .= self::twoDigitsEn(intdiv($num, 100000)) . ' Lakh ';
            $num %= 100000;
        }
        if ($num >= 1000) {
            $result .= self::twoDigitsEn(intdiv($num, 1000)) . ' Thousand ';
            $num %= 1000;
        }
        if ($num > 0) {
            $result .= self::threeDigitsEn($num);
        }
        return trim($result) . ' Rupees';
    }

    public static function toGujarati(int $num): string
    {
        if ($num === 0) return 'શૂન્ય રૂપિયા';

        $result = '';
        if ($num >= 10000000) {
            $result .= self::innerDigitsGu(intdiv($num, 10000000)) . ' કરોડ ';
            $num %= 10000000;
        }
        if ($num >= 100000) {
            $result .= self::twoDigitsGu(intdiv($num, 100000)) . ' લાખ ';
            $num %= 100000;
        }
        if ($num >= 1000) {
            $result .= self::twoDigitsGu(intdiv($num, 1000)) . ' હજાર ';
            $num %= 1000;
        }
        if ($num > 0) {
            $result .= self::threeDigitsGu($num);
        }
        return trim($result) . ' રૂપિયા';
    }

    public static function toBilingual(int $num): string
    {
        return self::toEnglish($num) . ' / ' . self::toGujarati($num);
    }

    public static function formatIndianNumber($num): string
    {
        $num = (int) $num;
        if ($num < 1000) return (string) $num;

        $lastThree = $num % 1000;
        $rest = intdiv($num, 1000);
        $lastThreeStr = str_pad((string) $lastThree, 3, '0', STR_PAD_LEFT);

        $parts = [];
        while ($rest > 0) {
            $parts[] = str_pad((string) ($rest % 100), $rest >= 100 ? 2 : 0, '0', STR_PAD_LEFT);
            $rest = intdiv($rest, 100);
        }
        $parts = array_reverse($parts);
        return implode(',', $parts) . ',' . $lastThreeStr;
    }

    public static function formatCurrency($num): string
    {
        return '₹ ' . self::formatIndianNumber((int) round($num));
    }

    private static function twoDigitsEn(int $n): string
    {
        if ($n < 20) return self::$ones[$n];
        return self::$tens[intdiv($n, 10)] . ($n % 10 ? ' ' . self::$ones[$n % 10] : '');
    }

    private static function threeDigitsEn(int $n): string
    {
        if ($n >= 100) return self::$ones[intdiv($n, 100)] . ' Hundred' . ($n % 100 ? ' ' . self::twoDigitsEn($n % 100) : '');
        return self::twoDigitsEn($n);
    }

    private static function twoDigitsGu(int $n): string
    {
        return self::$gujarati[$n] ?? '';
    }

    private static function threeDigitsGu(int $n): string
    {
        if ($n >= 100) {
            $h = self::$gujarati[intdiv($n, 100)];
            $rem = $n % 100;
            return $h . ' સો' . ($rem ? ' ' . self::twoDigitsGu($rem) : '');
        }
        return self::twoDigitsGu($n);
    }

    private static function innerDigitsEn(int $n): string
    {
        $r = '';
        if ($n >= 10000000) {
            $r .= self::innerDigitsEn(intdiv($n, 10000000)) . ' Crore ';
            $n %= 10000000;
        }
        if ($n >= 100000) {
            $r .= self::twoDigitsEn(intdiv($n, 100000)) . ' Lakh ';
            $n %= 100000;
        }
        if ($n >= 1000) {
            $r .= self::twoDigitsEn(intdiv($n, 1000)) . ' Thousand ';
            $n %= 1000;
        }
        if ($n > 0) {
            $r .= self::threeDigitsEn($n);
        }
        return trim($r);
    }

    private static function innerDigitsGu(int $n): string
    {
        $r = '';
        if ($n >= 10000000) {
            $r .= self::innerDigitsGu(intdiv($n, 10000000)) . ' કરોડ ';
            $n %= 10000000;
        }
        if ($n >= 100000) {
            $r .= (self::$gujarati[intdiv($n, 100000)] ?? '') . ' લાખ ';
            $n %= 100000;
        }
        if ($n >= 1000) {
            $r .= (self::$gujarati[intdiv($n, 1000)] ?? '') . ' હજાર ';
            $n %= 1000;
        }
        if ($n > 0) {
            $r .= self::threeDigitsGu($n);
        }
        return trim($r);
    }
}
