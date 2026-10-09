<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The inr() / inrc() helpers must group in the Indian system
 * (58,00,000 — NOT the international 5,800,000).
 */
class IndianNumberHelperTest extends TestCase
{
    public function test_inr_uses_indian_grouping(): void
    {
        $this->assertSame('0', inr(0));
        $this->assertSame('999', inr(999));
        $this->assertSame('1,000', inr(1000));
        $this->assertSame('1,00,000', inr(100000));
        $this->assertSame('58,00,000', inr(5800000));
        $this->assertSame('1,00,00,000', inr(10000000));
        $this->assertSame('16,20,000', inr(1620000));
    }

    public function test_inr_rounds_floats(): void
    {
        $this->assertSame('1,00,001', inr(100000.6));
        $this->assertSame('5,00,000', inr('500000'));
    }

    public function test_inrc_prefixes_rupee_symbol(): void
    {
        $this->assertSame("₹\u{00A0}58,00,000", inrc(5800000));
    }
}
