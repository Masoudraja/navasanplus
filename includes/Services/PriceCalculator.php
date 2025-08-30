<?php
namespace MNS\NavasanPlus\Services;

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\DB;

final class PriceCalculator {

    private static ?self $instance = null;
    public static function instance(): self { return self::$instance ??= new self(); }

    public function calculate( $product_or_id ) {
        $product = is_numeric( $product_or_id ) ? wc_get_product( (int) $product_or_id ) : $product_or_id;
        if ( ! $product instanceof \WC_Product ) return null;

        $db = DB::instance();

        // active?
        $active = $product->get_meta( $db->full_meta_key( 'active' ), true );
        $active = is_string($active) ? ($active === 'yes' || $active === '1') : (bool)$active;
        if ( ! $active ) return null;

        $dep = (string) $product->get_meta( $db->full_meta_key('dependence_type'), true );
        $dep = $dep !== '' ? strtolower($dep) : 'simple';

        return in_array($dep, ['advanced','formula'], true)
            ? $this->calc_advanced( $product )
            : $this->calc_simple( $product );
    }

    // -------------------- Advanced (with role-based discounts) --------------------

    private function calc_advanced( \WC_Product $product ): ?float {
        $db = DB::instance();

        $fid = (int) $product->get_meta( $db->full_meta_key('formula_id'), true );
        if ( $fid <= 0 ) return null;

        $vars_meta  = (array) get_post_meta($fid, $db->full_meta_key('formula_variables'), true);
        $expr       = (string) get_post_meta($fid, $db->full_meta_key('formula_expression'), true);
        $components = (array) get_post_meta($fid, $db->full_meta_key('formula_components'), true);

        // product overrides: [fid][code]['regular']
        $overrides_all = (array) $product->get_meta( $db->full_meta_key('formula_variables'), true );
        $overrides     = (array) ($overrides_all[$fid] ?? []);

        // 1) بساز: اطلاعات هر متغیّر
        $perVar = []; // code => ['role'=>'none|profit|charge','contrib'=>float]
        foreach ( $vars_meta as $code => $row ) {
            $code = sanitize_key($code);
            if ( $code === '' ) continue;

            $type   = (string)($row['type'] ?? 'custom');
            $role   = (string)($row['role'] ?? 'none');
            if ( ! in_array($role, ['none','profit','charge'], true) ) $role = 'none';

            // unit (currency → نرخ لحظه‌ای)
            $unit = (float)($row['unit'] ?? 0);
            if ( $type === 'currency' ) {
                $cid  = (int)($row['currency_id'] ?? 0);
                $unit = $this->get_currency_rate($cid);
            }

            // value (override محصول ← اگر خالی بود از فرمول)
            $valOverride = $overrides[$code]['regular'] ?? null;
            if ( $valOverride === '' ) $valOverride = null;
            $value = $valOverride !== null ? (float)$valOverride : (float)($row['value'] ?? 0);

            $perVar[$code] = [
                'role'    => $role,
                'contrib' => (float)$unit * (float)$value,
            ];
        }

        // 2) تخفیف‌ها از متای محصول
        $p_pct = (float)$product->get_meta( $db->full_meta_key('discount_profit_percentage'), true );
        $p_fix = (float)$product->get_meta( $db->full_meta_key('discount_profit_fixed'),      true );
        $c_pct = (float)$product->get_meta( $db->full_meta_key('discount_charge_percentage'), true );
        $c_fix = (float)$product->get_meta( $db->full_meta_key('discount_charge_fixed'),      true );

        // 3) مجموع سهم profit/charge قبل از تخفیف
        $sum_profit = 0.0; $sum_charge = 0.0;
        foreach ( $perVar as $v ) {
            if ( $v['role'] === 'profit' ) $sum_profit += (float)$v['contrib'];
            if ( $v['role'] === 'charge' ) $sum_charge += (float)$v['contrib'];
        }

        // 4) فاکتورهای مقیاس
        $s_profit = $this->scale_factor($sum_profit, $p_pct, $p_fix); // بین 0..∞ (معمولاً <=1)
        $s_charge = $this->scale_factor($sum_charge, $c_pct, $c_fix);

        // 5) نقشهٔ نهایی برای Engine: code => adjusted_contrib
        $vars_for_engine = [];
        foreach ( $perVar as $code => $v ) {
            $k = $v['role'] === 'profit' ? $s_profit : ( $v['role'] === 'charge' ? $s_charge : 1.0 );
            $vars_for_engine[$code] = max( 0.0, (float)$v['contrib'] * (float)$k );
        }

        // 6) محاسبه
        if ( trim($expr) !== '' && class_exists(FormulaEngine::class) ) {
            try {
                $out = FormulaEngine::evaluate($expr, $vars_for_engine);
                return is_numeric($out) ? (float)$out : null;
            } catch (\Throwable $e) { /* noop */ }
        }

        if ( ! empty($components) && class_exists(FormulaEngine::class) ) {
            $sum = 0.0;
            foreach ( $components as $c ) {
                $texpr = (string)($c['expression'] ?? $c['text'] ?? '');
                if ( trim($texpr) === '' ) continue;
                try {
                    $val = FormulaEngine::evaluate($texpr, $vars_for_engine);
                    if ( is_numeric($val) ) $sum += (float)$val;
                } catch (\Throwable $e) { /* noop */ }
            }
            return $sum;
        }

        return null;
    }

    private function scale_factor( float $sum, float $pct, float $fix ): float {
        if ( $sum <= 0 ) return 1.0;
        $after = $sum * (1.0 - max(0.0,$pct)/100.0) - max(0.0,$fix);
        if ( $after < 0 ) $after = 0.0;
        return $after / $sum;
    }

    private function get_currency_rate( int $currency_id ): float {
        if ( $currency_id <= 0 ) return 0.0;
        $db = DB::instance();
        $rate = get_post_meta( $currency_id, $db->full_meta_key('currency_value'), true );
        return ($rate === '' ? 0.0 : (float)$rate);
    }

    // -------------------- Simple --------------------

    private function calc_simple( \WC_Product $product ): ?float {
        $db = DB::instance();
        $cid = (int)$product->get_meta( $db->full_meta_key('currency_id'), true );
        if ( $cid <= 0 ) return null;

        $rate = $this->get_currency_rate($cid);
        if ( $rate <= 0 ) return null;

        $profit_type  = (string)$product->get_meta( $db->full_meta_key('profit_type'),  true ) ?: 'percent';
        $profit_value = (float) $product->get_meta( $db->full_meta_key('profit_value'), true );

        $base  = (float)$rate;
        $price = $profit_type === 'fixed' ? ($base + $profit_value) : ($base * (1.0 + $profit_value/100.0));

        // rounding ceil/floor (در صورت نیاز می‌تونی این را هم برای Advanced به‌کار ببری)
        $round_type  = (string)$product->get_meta( $db->full_meta_key('rounding_type'),  true ) ?: 'none';
        $round_side  = (string)$product->get_meta( $db->full_meta_key('rounding_side'),  true ) ?: 'close';
        $round_value = (float) $product->get_meta( $db->full_meta_key('rounding_value'), true );
        if ( $round_type !== 'none' && $round_value > 0 ) {
            $price = $this->round_price($price, $round_type, $round_side, $round_value);
        }

        $ceil = $product->get_meta( $db->full_meta_key('ceil_price'),  true );
        $floor= $product->get_meta( $db->full_meta_key('floor_price'), true );
        if ( $ceil  !== '' ) $price = min( (float)$ceil,  $price );
        if ( $floor !== '' ) $price = max( (float)$floor, $price );

        return $price;
    }

    private function round_price( float $price, string $type, string $side, float $value ): float {
        switch ($type) {
            case 'zero':
                $m = max(1.0,$value);
                $d = $price / $m;
                if ( $side === 'up' ) $d = ceil($d);
                elseif ( $side === 'down' ) $d = floor($d);
                else $d = round($d);
                return $d * $m;
            case 'integer':
                if ( $side === 'up' )   return ceil($price);
                if ( $side === 'down' ) return floor($price);
                return round($price);
        }
        return $price;
    }
}