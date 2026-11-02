<?php

namespace Tests\Unit;

use App\Support\Format;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    public function test_rupiah(): void
    {
        $this->assertSame('Rp22.000', Format::rupiah(22000));
        $this->assertSame('Rp1.250.000', Format::rupiah('1250000.00'));
        $this->assertSame('-Rp500', Format::rupiah(-500));
        $this->assertSame('Rp0', Format::rupiah(null));
    }

    public function test_quantity_trims_trailing_zeros(): void
    {
        $this->assertSame('1.500', Format::qty('1500.000'));
        $this->assertSame('0,5', Format::qty('0.500'));
        $this->assertSame('0', Format::qty(0));
        $this->assertSame('-12,25', Format::qty(-12.25));
    }

    public function test_unit_cost_keeps_fractions(): void
    {
        $this->assertSame('Rp0,25', Format::unitCost('0.2500'));
        $this->assertSame('Rp250', Format::unitCost('250.0000'));
    }
}
