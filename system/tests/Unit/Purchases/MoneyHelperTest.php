<?php

declare(strict_types=1);

namespace Tests\Unit\Purchases;

use App\Modules\Purchases\Enums\MarginType;
use App\Modules\Purchases\Support\MoneyHelper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests للحسابات المالية
 *
 * ⚠️ هذه الـ tests حرجة - أي خطأ هنا = خسائر مالية في الإنتاج
 */
class MoneyHelperTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════════
    // Basic arithmetic
    // ═══════════════════════════════════════════════════════════════

    public function test_addition_is_precise(): void
    {
        // مثال على floating point bug في PHP العادي:
        // 0.1 + 0.2 = 0.30000000000000004
        $result = MoneyHelper::add('0.1', '0.2');
        $this->assertSame('0.30', $result);
    }

    public function test_subtraction(): void
    {
        $this->assertSame('50.00', MoneyHelper::sub('100', '50'));
        $this->assertSame('-25.00', MoneyHelper::sub('25', '50'));
    }

    public function test_multiplication(): void
    {
        $this->assertSame('100.00', MoneyHelper::mul('10', '10'));
        $this->assertSame('15.75', MoneyHelper::mul('3.15', '5'));
    }

    public function test_division_by_zero_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MoneyHelper::div('100', '0');
    }

    // ═══════════════════════════════════════════════════════════════
    // Comparisons
    // ═══════════════════════════════════════════════════════════════

    public function test_comparisons(): void
    {
        $this->assertTrue(MoneyHelper::gt('100', '50'));
        $this->assertFalse(MoneyHelper::gt('50', '100'));

        $this->assertTrue(MoneyHelper::lt('50', '100'));
        $this->assertFalse(MoneyHelper::lt('100', '50'));

        $this->assertTrue(MoneyHelper::eq('100.00', '100'));
        $this->assertTrue(MoneyHelper::gte('100', '100'));
        $this->assertTrue(MoneyHelper::lte('100', '100'));
    }

    // ═══════════════════════════════════════════════════════════════
    // Currency conversion
    // ═══════════════════════════════════════════════════════════════

    public function test_convert_700_cny_to_usd(): void
    {
        // 700 CNY ÷ 7.20 = 97.22 USD
        $result = MoneyHelper::convertCurrency('700.00', '7.20');
        $this->assertSame('97.22', $result);
    }

    public function test_convert_5000_try_to_usd(): void
    {
        // 5000 TRY ÷ 32.50 = 153.84 USD (rounded)
        $result = MoneyHelper::convertCurrency('5000', '32.50');
        $this->assertSame('153.84', $result);
    }

    // ═══════════════════════════════════════════════════════════════
    // Apply margin (CLAUDE.md Section 5)
    // ═══════════════════════════════════════════════════════════════

    public function test_apply_margin_none(): void
    {
        $result = MoneyHelper::applyMargin('7.20', MarginType::NONE, '1.5');
        // النوع NONE يتجاهل قيمة الهامش
        $this->assertSame('7.20000000', $result);
    }

    public function test_apply_margin_percentage_positive(): void
    {
        // 7.20 × (1 + 1.5/100) = 7.308
        $result = MoneyHelper::applyMargin('7.20', MarginType::PERCENTAGE, '1.5');
        $this->assertSame('7.30800000', $result);
    }

    public function test_apply_margin_percentage_negative(): void
    {
        // 7.20 × (1 - 0.5/100) = 7.164
        $result = MoneyHelper::applyMargin('7.20', MarginType::PERCENTAGE, '-0.5');
        $this->assertSame('7.16400000', $result);
    }

    public function test_apply_margin_fixed_amount(): void
    {
        // 7.20 + 0.05 = 7.25
        $result = MoneyHelper::applyMargin('7.20', MarginType::FIXED_AMOUNT, '0.05');
        $this->assertSame('7.25000000', $result);
    }

    // ═══════════════════════════════════════════════════════════════
    // Real-world scenarios (CLAUDE.md examples)
    // ═══════════════════════════════════════════════════════════════

    public function test_cny_with_typical_margin(): void
    {
        // سعر السوق 7.1850، هامش 1.5%
        $result = MoneyHelper::applyMargin('7.1850', MarginType::PERCENTAGE, '1.5');
        $this->assertSame('7.29277500', $result);
    }

    public function test_try_with_high_margin_volatile(): void
    {
        // سعر السوق 32.50، هامش 3%
        $result = MoneyHelper::applyMargin('32.50', MarginType::PERCENTAGE, '3');
        $this->assertSame('33.47500000', $result);
    }

    public function test_aed_with_minimal_margin_stable(): void
    {
        // سعر السوق 3.6725، هامش 0.5%
        $result = MoneyHelper::applyMargin('3.6725', MarginType::PERCENTAGE, '0.5');
        $this->assertSame('3.69086250', $result);
    }

    // ═══════════════════════════════════════════════════════════════
    // Percentage calculations (commissions)
    // ═══════════════════════════════════════════════════════════════

    public function test_calculate_commission_percentage(): void
    {
        // 7% من 1000
        $result = MoneyHelper::percentage('1000', '7');
        $this->assertSame('70.00', $result);
    }

    public function test_calculate_commission_small_value(): void
    {
        // 5% من 25.50
        $result = MoneyHelper::percentage('25.50', '5');
        $this->assertSame('1.27', $result); // 1.275 -> rounded
    }

    // ═══════════════════════════════════════════════════════════════
    // Deviation (Spike protection)
    // ═══════════════════════════════════════════════════════════════

    public function test_deviation_percent_increase(): void
    {
        // من 7.20 إلى 7.50 = ~4.17%
        $result = MoneyHelper::deviationPercent('7.20', '7.50');
        $this->assertSame('4.16', $result);
    }

    public function test_deviation_percent_decrease(): void
    {
        // من 7.50 إلى 7.20 = 4%
        $result = MoneyHelper::deviationPercent('7.50', '7.20');
        $this->assertSame('4.00', $result);
    }

    public function test_deviation_percent_no_change(): void
    {
        $result = MoneyHelper::deviationPercent('7.20', '7.20');
        $this->assertSame('0.00', $result);
    }

    public function test_deviation_percent_zero_old(): void
    {
        // قسمة على صفر = 0 (special case)
        $result = MoneyHelper::deviationPercent('0', '5');
        $this->assertSame('0', $result);
    }

    // ═══════════════════════════════════════════════════════════════
    // Edge cases
    // ═══════════════════════════════════════════════════════════════

    public function test_abs(): void
    {
        $this->assertSame('100.00', MoneyHelper::abs('-100'));
        $this->assertSame('100.00', MoneyHelper::abs('100'));
        $this->assertSame('0.00', MoneyHelper::abs('0'));
    }

    public function test_negate(): void
    {
        $this->assertSame('-100.00', MoneyHelper::negate('100'));
        $this->assertSame('100.00', MoneyHelper::negate('-100'));
    }

    public function test_non_negative_floor(): void
    {
        $this->assertSame('0.00', MoneyHelper::nonNegative('-100'));
        $this->assertSame('50.00', MoneyHelper::nonNegative('50'));
    }
}
