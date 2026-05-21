<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Support;

use App\Modules\Purchases\Enums\MarginType;

/**
 * MoneyHelper - أدوات حسابات مالية دقيقة
 *
 * ⚠️ مهم: PHP floats غير دقيقة للحسابات المالية.
 * استخدم BCMath دائماً للمبالغ.
 *
 * كل القيم تُعالج كـ strings للحفاظ على الدقة.
 *
 * @see CLAUDE.md Section 13 - قاعدة "لا تستخدم float للمال"
 */
final class MoneyHelper
{
    /**
     * عدد المنازل العشرية الافتراضي للمبالغ
     */
    public const DEFAULT_SCALE = 2;

    /**
     * عدد المنازل العشرية لأسعار الصرف
     */
    public const RATE_SCALE = 8;

    /**
     * جمع مبلغين
     */
    public static function add(string|float|int $a, string|float|int $b, int $scale = self::DEFAULT_SCALE): string
    {
        return bcadd((string) $a, (string) $b, $scale);
    }

    /**
     * طرح
     */
    public static function sub(string|float|int $a, string|float|int $b, int $scale = self::DEFAULT_SCALE): string
    {
        return bcsub((string) $a, (string) $b, $scale);
    }

    /**
     * ضرب
     */
    public static function mul(string|float|int $a, string|float|int $b, int $scale = self::DEFAULT_SCALE): string
    {
        return bcmul((string) $a, (string) $b, $scale);
    }

    /**
     * قسمة
     */
    public static function div(string|float|int $a, string|float|int $b, int $scale = self::DEFAULT_SCALE): string
    {
        if (bccomp((string) $b, '0', self::RATE_SCALE) === 0) {
            throw new \InvalidArgumentException('Division by zero');
        }
        return bcdiv((string) $a, (string) $b, $scale);
    }

    /**
     * مقارنة: -1 لو a < b، 0 لو متساويين، 1 لو a > b
     */
    public static function cmp(string|float|int $a, string|float|int $b, int $scale = self::DEFAULT_SCALE): int
    {
        return bccomp((string) $a, (string) $b, $scale);
    }

    /**
     * هل a >= b؟
     */
    public static function gte(string|float|int $a, string|float|int $b, int $scale = self::DEFAULT_SCALE): bool
    {
        return self::cmp($a, $b, $scale) >= 0;
    }

    /**
     * هل a > b؟
     */
    public static function gt(string|float|int $a, string|float|int $b, int $scale = self::DEFAULT_SCALE): bool
    {
        return self::cmp($a, $b, $scale) > 0;
    }

    /**
     * هل a < b؟
     */
    public static function lt(string|float|int $a, string|float|int $b, int $scale = self::DEFAULT_SCALE): bool
    {
        return self::cmp($a, $b, $scale) < 0;
    }

    /**
     * هل a <= b؟
     */
    public static function lte(string|float|int $a, string|float|int $b, int $scale = self::DEFAULT_SCALE): bool
    {
        return self::cmp($a, $b, $scale) <= 0;
    }

    /**
     * هل a = b (مع تحمّل tolerance)؟
     */
    public static function eq(string|float|int $a, string|float|int $b, int $scale = self::DEFAULT_SCALE): bool
    {
        return self::cmp($a, $b, $scale) === 0;
    }

    /**
     * القيمة المطلقة
     */
    public static function abs(string|float|int $value, int $scale = self::DEFAULT_SCALE): string
    {
        $str = (string) $value;
        if (str_starts_with($str, '-')) {
            $str = substr($str, 1);
        }
        return bcadd($str, '0', $scale);
    }

    /**
     * عكس الإشارة
     */
    public static function negate(string|float|int $value, int $scale = self::DEFAULT_SCALE): string
    {
        return bcmul((string) $value, '-1', $scale);
    }

    /**
     * تحويل عملة باستخدام سعر صرف
     *
     * @example convertCurrency('700.00', '7.20') => '97.22' (700 CNY ÷ 7.20 = 97.22 USD)
     */
    public static function convertCurrency(
        string|float|int $amount,
        string|float|int $rate,
        int $scale = self::DEFAULT_SCALE,
    ): string {
        return self::div($amount, $rate, $scale);
    }

    /**
     * حساب نسبة مئوية من مبلغ
     *
     * @example percentage('1000', '7') => '70.00'
     */
    public static function percentage(
        string|float|int $amount,
        string|float|int $percent,
        int $scale = self::DEFAULT_SCALE,
    ): string {
        return self::div(self::mul($amount, $percent, $scale + 2), '100', $scale);
    }

    /**
     * تطبيق هامش على سعر صرف
     *
     * @example applyMargin('7.20', MarginType::PERCENTAGE, '1.5') => '7.30800000'
     */
    public static function applyMargin(
        string|float|int $rate,
        MarginType $marginType,
        string|float|int $marginValue,
        int $scale = self::RATE_SCALE,
    ): string {
        return match ($marginType) {
            MarginType::NONE => bcadd((string) $rate, '0', $scale),

            // rate * (1 + margin/100)
            MarginType::PERCENTAGE => self::mul(
                $rate,
                self::add('1', self::div($marginValue, '100', $scale), $scale),
                $scale,
            ),

            // rate + marginValue
            MarginType::FIXED_AMOUNT => self::add($rate, $marginValue, $scale),
        };
    }

    /**
     * حساب الانحراف بالنسبة المئوية بين قيمتين
     *
     * @example deviationPercent('7.20', '7.50') => '4.17' (4.17% increase)
     */
    public static function deviationPercent(
        string|float|int $oldValue,
        string|float|int $newValue,
        int $scale = 2,
    ): string {
        if (self::eq($oldValue, '0', $scale)) {
            return '0';
        }

        $diff = self::sub($newValue, $oldValue, $scale + 4);
        $absDiff = self::abs($diff, $scale + 4);
        $pct = self::mul(self::div($absDiff, self::abs($oldValue, $scale + 4), $scale + 4), '100', $scale);

        return $pct;
    }

    /**
     * تنسيق للعرض
     */
    public static function format(string|float|int $value, int $scale = self::DEFAULT_SCALE): string
    {
        return number_format((float) $value, $scale, '.', ',');
    }

    /**
     * حدّ أدنى من الصفر (مثل max(0, value))
     */
    public static function nonNegative(string|float|int $value, int $scale = self::DEFAULT_SCALE): string
    {
        if (self::lt($value, '0', $scale)) {
            return bcadd('0', '0', $scale);
        }
        return bcadd((string) $value, '0', $scale);
    }
}
