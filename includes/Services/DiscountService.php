<?php
namespace MNS\NavasanPlus\Services;

use MNS\NavasanPlus\DB;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * DiscountService
 *
 * ورودی: سود و اجرت قبل از تخفیف + شناسه محصول/ورییشن
 * خروجی: [profit_after, charge_after] پس از اعمال تخفیف‌ها
 *
 * تخفیف‌ها فقط روی سود (profit) و اجرت (charge) اعمال می‌شوند:
 *  - درصدی:  discount_*_percentage
 *  - ثابت:   discount_*_fixed
 * خواندن متاها با fallback از ورییشن ← والد انجام می‌شود.
 */
final class DiscountService
{
    /**
     * اعمال تخفیف‌ها روی سود و اجرت.
     *
     * @param float $profit_base
     * @param float $charge_base
     * @param int   $product_id
     * @return array{0: float, 1: float} [profit_after, charge_after]
     */
    public static function apply( float $profit_base, float $charge_base, int $product_id ): array
    {
        $profit_base = max(0.0, (float) $profit_base);
        $charge_base = max(0.0, (float) $charge_base);

        // ---- در ابتدای DiscountService::apply()، قبل از نرمال‌سازی ----
        $override = apply_filters('mnsnp/discounts/override_values', null, $product_id);

        if ( is_array($override) ) {
            $pp = (float) ($override['profit_percentage'] ?? 0);
            $pf = (float) ($override['profit_fixed']      ?? 0);
            $cp = (float) ($override['charge_percentage'] ?? 0);
            $cf = (float) ($override['charge_fixed']      ?? 0);
        } else {
            // حالت عادی: از متا بخوان
            $pp = (float) self::get_meta_with_fallback( $product_id, 'discount_profit_percentage', 0 );
            $pf = (float) self::get_meta_with_fallback( $product_id, 'discount_profit_fixed',      0 );
            $cp = (float) self::get_meta_with_fallback( $product_id, 'discount_charge_percentage', 0 );
            $cf = (float) self::get_meta_with_fallback( $product_id, 'discount_charge_fixed',      0 );
        }

        // نرمال‌سازی
        $pp = max(0.0, $pp);
        $pf = max(0.0, $pf);
        $cp = max(0.0, $cp);
        $cf = max(0.0, $cf);

        // ترتیب اعمال: اول درصدی، بعد ثابت
        $profit_after = self::apply_component_discount( $profit_base, $pp, $pf, 'profit', $product_id );
        $charge_after = self::apply_component_discount( $charge_base, $cp, $cf, 'charge', $product_id );

        // هوک کلی برای سفارشی‌سازی احتمالی
        [ $profit_after, $charge_after ] = (array) apply_filters(
            'mnsnp/discounts/apply',
            [ $profit_after, $charge_after ],
            [
                'profit_base' => $profit_base,
                'charge_base' => $charge_base,
                'meta' => compact('pp','pf','cp','cf'),
            ],
            $product_id
        );

        return [ max(0.0, (float) $profit_after), max(0.0, (float) $charge_after) ];
    }

    /**
     * اعمال تخفیف روی یک مؤلفه (profit یا charge).
     */
    private static function apply_component_discount( float $base, float $percent, float $fixed, string $which, int $product_id ): float
    {
        $after = max(0.0, $base);

        // درصدی
        if ( $percent > 0 ) {
            $after -= ( $after * $percent / 100 );
        }

        // ثابت
        if ( $fixed > 0 ) {
            $after -= $fixed;
        }

        $after = max(0.0, $after);

        // هوک جزئی
        return (float) apply_filters(
            'mnsnp/discounts/apply/component',
            $after,
            [
                'which'   => $which, // 'profit' | 'charge'
                'base'    => $base,
                'percent' => $percent,
                'fixed'   => $fixed,
            ],
            $product_id
        );
    }

    /**
     * خواندن متا با fallback از ورییشن ← والد (بدون آندرلاین/پیشوند بده)
     */
    private static function get_meta_with_fallback( int $post_id, string $key, $default = '' )
    {
        $db  = DB::instance();
        $val = $db->read_post_meta( $post_id, $key, '' );

        if ( $val === '' ) {
            $parent_id = (int) wp_get_post_parent_id( $post_id );
            if ( $parent_id ) {
                $val = $db->read_post_meta( $parent_id, $key, '' );
            }
        }
        return ($val === '' ? $default : $val);
    }
}