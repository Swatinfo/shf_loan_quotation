<?php

namespace Tests\Unit;

use App\Services\NumberToWordsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Indian-system amount-in-words conversion. The crore segment must recurse
 * (innerDigitsEn/Gu) so values like 2000 crore render "Two Thousand Crore"
 * instead of indexing past the ones table — the JS copies in shf-app.js and
 * shf-newtheme.js mirror this exact behavior.
 */
class NumberToWordsServiceTest extends TestCase
{
    /**
     * @return array<string, array{int, string, string}>
     */
    public static function amountProvider(): array
    {
        return [
            'zero' => [0, 'Zero Rupees', 'શૂન્ય રૂપિયા'],
            'hundreds' => [999, 'Nine Hundred Ninety Nine Rupees', 'નવ સો નવ્વાણું રૂપિયા'],
            'thousands' => [50000, 'Fifty Thousand Rupees', 'પચાસ હજાર રૂપિયા'],
            'lakhs' => [1234567, 'Twelve Lakh Thirty Four Thousand Five Hundred Sixty Seven Rupees', 'બાર લાખ ચોંત્રીસ હજાર પાંચ સો સડસઠ રૂપિયા'],
            'crores' => [25000000, 'Two Crore Fifty Lakh Rupees', 'બે કરોડ પચાસ લાખ રૂપિયા'],
            'hundred crores' => [2000000000, 'Two Hundred Crore Rupees', 'બે સો કરોડ રૂપિયા'],
            'thousand crores' => [20000000000, 'Two Thousand Crore Rupees', 'બે હજાર કરોડ રૂપિયા'],
            'ten thousand crores' => [100000000000, 'Ten Thousand Crore Rupees', 'દસ હજાર કરોડ રૂપિયા'],
        ];
    }

    #[DataProvider('amountProvider')]
    public function test_converts_amounts_to_english_and_gujarati(int $amount, string $english, string $gujarati): void
    {
        $this->assertSame($english, NumberToWordsService::toEnglish($amount));
        $this->assertSame($gujarati, NumberToWordsService::toGujarati($amount));
    }

    public function test_bilingual_combines_both_languages(): void
    {
        $this->assertSame(
            'Two Thousand Crore Rupees / બે હજાર કરોડ રૂપિયા',
            NumberToWordsService::toBilingual(20000000000)
        );
    }

    public function test_format_indian_number_groups_by_lakh_crore(): void
    {
        $this->assertSame('20,00,00,00,000', NumberToWordsService::formatIndianNumber(20000000000));
        $this->assertSame('7,50,00,000', NumberToWordsService::formatIndianNumber(75000000));
    }
}
