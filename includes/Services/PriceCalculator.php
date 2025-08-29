<?php
namespace MNS\NavasanPlus\Services;

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\DB;

final class PriceCalculator {
    private static ?self $instance = null;
    public static function instance(): self {
        return self::$instance ?? ( self::$instance = new self() );
    }
    private function __construct() {}

    /** استفادهٔ عمومی (وقتی فقط ID داری) */
    public function calculate( int $product_id ) {
        $product = wc_get_product( $product_id );
        return $product ? $this->calculate_for_product( $product ) : 0.0;
    }

    /** محاسبه روی خود آبجکت محصول (برای زمان ذخیره، متاهای تازه ست‌شده هم قابل خوندن‌اند) */
    public function calculate_for_product( \WC_Product $product ) {
        $db  = DB::instance();
        $get = fn(string $k, $def='') => $product->get_meta( $db->full_meta_key($k), true ) ?? $def;

        // فعال‌بودن: اگر متا خالیه یعنی هنوز ذخیره نشده → پیش‌فرض «فعال»
        $active_raw = (string) $get('active', '');
        $active = ($active_raw === '')
            ? true
            : ( function_exists('wc_string_to_bool') ? wc_string_to_bool($active_raw) : in_array($active_raw, ['yes','1',1,true], true) );
        if ( ! $active ) {
            $p = (float) $product->get_regular_price();
            return $p > 0 ? $p : (float) $product->get_price();
        }

        $dep = strtolower( (string) $get('dependence_type','simple') );
        if ( in_array($dep, ['advanced','formula'], true) ) {
            $out = $this->calc_formula( $product, $db );
        } else {
            $out = $this->calc_simple( $product, $db );
        }

        if ( is_numeric($out) ) return (float) $out;
        if ( is_array($out) ) {
            if ( isset($out['price']) ) return (float) $out['price'];
            if ( isset($out['profit'],$out['charge']) ) return (float)$out['profit'] + (float)$out['charge'];
        }
        return 0.0;
    }

    // ----------------- Simple -----------------
    private function calc_simple( \WC_Product $product, DB $db ) {
        $currency_id = (int) $product->get_meta( $db->full_meta_key('currency_id'), true );
        if ( $currency_id <= 0 ) {
            $manual = (float) $product->get_regular_price();
            return $manual > 0 ? $manual : (float) $product->get_price();
        }

        $rate = (float) $db->read_post_meta( $currency_id, 'currency_value', 0 );
        if ( $rate <= 0 ) {
            $hist = $db->read_post_meta( $currency_id, 'currency_history', [] );
            if ( is_array($hist) && $hist ) $rate = (float) end($hist);
        }
        if ( $rate <= 0 ) {
            $manual = (float) $product->get_regular_price();
            return $manual > 0 ? $manual : (float) $product->get_price();
        }

        $profit_type  = (string) ($product->get_meta( $db->full_meta_key('profit_type'), true ) ?: 'percent');
        $profit_value = (float)  ($product->get_meta( $db->full_meta_key('profit_value'), true ) ?: 0);

        $base = $rate;
        $profit_amount = ($profit_type === 'percent') ? $base * ($profit_value/100) : $profit_value;

        $disc = $this->read_discounts($product,$db);
        $profit_amount = $this->apply_discount_pair($profit_amount, $disc['profit_percent'], $disc['profit_fixed']);

        $price = $base + $profit_amount;
        $price = $this->round_and_bounds($price, $product, $db);
        return max(0.0,(float)$price);
    }

    // ----------------- Formula -----------------
    private function calc_formula( \WC_Product $product, DB $db ) {
        $formula_id = (int) $product->get_meta( $db->full_meta_key('formula_id'), true );
        if ( $formula_id <= 0 ) {
            $manual = (float) $product->get_regular_price();
            return $manual > 0 ? $manual : (float) $product->get_price();
        }

        $expr = (string) get_post_meta( $formula_id, $db->full_meta_key('formula_expression'), true );
        $expr = trim($expr);

        $vars_meta = get_post_meta( $formula_id, $db->full_meta_key('formula_variables'), true );
        $vars_meta = is_array($vars_meta) ? $vars_meta : [];

        $prod_vars = $product->get_meta( $db->full_meta_key('formula_variables'), true );
        $prod_vars = is_array($prod_vars) ? $prod_vars : [];

        $vars = [];
        foreach ( $vars_meta as $code => $row ) {
            $code = sanitize_key($code);
            if ( $code === '' ) continue;

            $type        = (string)($row['type'] ?? 'custom');
            $currency_id = (int)   ($row['currency_id'] ?? 0);
            $unit        = (float) ($row['unit'] ?? 0);
            $value_def   = (float) ($row['value'] ?? 1);

            if ( $type === 'currency' && $currency_id > 0 ) {
                $unit = (float) $db->read_post_meta( $currency_id, 'currency_value', 0 );
                if ( $unit <= 0 ) {
                    $hist = $db->read_post_meta( $currency_id, 'currency_history', [] );
                    if ( is_array($hist) && $hist ) $unit = (float) end($hist);
                }
            }

            $override = $prod_vars[ $formula_id ][ $code ]['regular'] ?? '';
            $value = ($override !== '') ? (float) $override : $value_def;

            $vars[$code] = (float)$unit * (float)$value;
        }

        if ( $expr === '' ) {
            $comps = get_post_meta( $formula_id, $db->full_meta_key('formula_components'), true );
            $comps = is_array($comps) ? $comps : [];
            $sum = 0.0;
            foreach ( $comps as $c ) {
                $t = trim( (string) ($c['expression'] ?? '') );
                if ( $t === '' ) continue;
                $sum += $this->eval_expr($t, $vars);
            }
            $price = (float)$sum;
        } else {
            $price = (float)$this->eval_expr($expr, $vars);
        }

        $price = $this->round_and_bounds($price, $product, $db);
        return max(0.0,(float)$price);
    }

    // ----------------- Helpers -----------------
    private function read_discounts( \WC_Product $product, DB $db ): array {
        $g = fn(string $s) => (float) ($product->get_meta( $db->full_meta_key($s), true ) ?: 0);
        return [
            'profit_percent' => $g('discount_profit_percentage'),
            'profit_fixed'   => $g('discount_profit_fixed'),
            'charge_percent' => $g('discount_charge_percentage'),
            'charge_fixed'   => $g('discount_charge_fixed'),
        ];
    }
    private function apply_discount_pair( float $amount, float $percent, float $fixed ): float {
        if ( $amount <= 0 ) return 0.0;
        if ( $percent > 0 ) $amount -= $amount * ($percent/100);
        if ( $fixed   > 0 ) $amount -= $fixed;
        return max(0.0,$amount);
    }
    private function round_and_bounds( float $price, \WC_Product $product, DB $db ): float {
        $type  = (string) $product->get_meta( $db->full_meta_key('rounding_type'), true );
        $side  = (string) $product->get_meta( $db->full_meta_key('rounding_side'), true );
        $value = (float)  ($product->get_meta( $db->full_meta_key('rounding_value'), true ) ?: 0);

        if ( $type === '' )  $type  = get_option('mns_navasan_plus_rounding_type','none');
        if ( $side === '' )  $side  = get_option('mns_navasan_plus_rounding_side','close');
        if ( $value <= 0 )   $value = (float) get_option('mns_navasan_plus_rounding_value',0);

        if ( $type === 'integer' ) {
            $price = (float) round($price);
        } elseif ( $type === 'zero' && $value > 0 ) {
            $m = (float)$value;
            if ( $side === 'up' )      $price = ceil($price/$m)*$m;
            elseif ( $side === 'down') $price = floor($price/$m)*$m;
            else                       $price = round($price/$m)*$m;
        }

        $ceil  = $product->get_meta( $db->full_meta_key('ceil_price'), true );
        $floor = $product->get_meta( $db->full_meta_key('floor_price'), true );
        if ( $ceil  !== '' ) $price = min($price, (float)$ceil);
        if ( $floor !== '' ) $price = max($price, (float)$floor);
        return (float)$price;
    }

    private function eval_expr( string $expr, array $vars ): float {
        if ( class_exists('\\MNS\\NavasanPlus\\Services\\FormulaEngine') ) {
            try {
                if ( method_exists('\\MNS\\NavasanPlus\\Services\\FormulaEngine','evaluate') )
                    return (float) \MNS\NavasanPlus\Services\FormulaEngine::evaluate($expr,$vars);
                if ( method_exists('\\MNS\\NavasanPlus\\Services\\FormulaEngine','calc') )
                    return (float) \MNS\NavasanPlus\Services\FormulaEngine::calc($expr,$vars);
            } catch (\Throwable $e) {}
        }
        $safe = $expr;
        foreach ($vars as $k=>$v) {
            $safe = preg_replace('/\b'.preg_quote($k,'/').'\b/u', (string)(float)$v, $safe);
        }
        if ( preg_match('/^[0-9\.\+\-\*\/\(\)\s]+$/u', $safe) ) {
            try { $val = eval('return (float)('.$safe.');'); return is_numeric($val)?(float)$val:0.0; }
            catch (\Throwable $e) { return 0.0; }
        }
        return 0.0;
    }
}