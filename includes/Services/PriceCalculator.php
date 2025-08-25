<?php
namespace MNS\NavasanPlus\Services;

use MNS\NavasanPlus\DB;
use MNS\NavasanPlus\Services\FormulaEngine;
use MNS\NavasanPlus\PublicNS\Formula as FormulaModel;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * PriceCalculator (Advanced, Navasan-compatible)
 *
 * Flow:
 *   1) Base  ← currency or formula (with FormulaEngine)
 *   2) Profit_base / Charge_base ← from formula components (profit/charge) if dep=formula,
 *      otherwise from (percent|fixed) meta
 *   3) Discounts only on Profit & Charge ← DiscountService::apply()
 *   4) price = Base + Profit + Charge
 *   5) Rounding / Bounds (ceil/floor/round | zero/integer + side)
 *
 * Result: ['price'=>float, 'base'=>float, 'profit'=>float, 'charge'=>float]
 */
final class PriceCalculator {

    /** @var self|null */
    private static $instance = null;

    /** Singleton */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Calculate final price for a product/variation.
     *
     * @param int $product_id
     * @return array{price:float, base:float, profit:float, charge:float}
     */
    public function calculate( int $product_id ): array {
        // فعال بودن (سازگار با حالت‌های قدیمی/جدید)
        $rawActive = (string) $this->get_meta_with_fallback( $product_id, 'active', '1' );
        $active    = in_array( strtolower( (string) $rawActive ), ['1','yes','true','on'], true );
        if ( ! $active ) {
            return ['price'=>0.0, 'base'=>0.0, 'profit'=>0.0, 'charge'=>0.0];
        }

        // 1) Base
        $base = (float) $this->calculate_base( $product_id );
        if ( ! $this->isFinite( $base ) || $base < 0 ) { $base = 0.0; }

        // 2) Profit / Charge (قبل از تخفیف) — با فرمول یا با متا (fallback)
        [ $profit_base, $charge_base ] = $this->calculate_components( $product_id, $base );

        // 3) Discounts روی سود و اجرت
        if ( class_exists( __NAMESPACE__ . '\\DiscountService' ) ) {
            [ $profit, $charge ] = DiscountService::apply(
                (float) $profit_base,
                (float) $charge_base,
                (int)   $product_id
            );
        } else {
            $profit = (float) $profit_base;
            $charge = (float) $charge_base;
        }

        // 4) Sum
        $price = (float) $base + (float) $profit + (float) $charge;

        // 5) Rounding & Bounds
        $price = $this->apply_rounding_and_bounds( $product_id, $price );

        $result = [
            'price'  => max( 0.0, (float) $price ),
            'base'   => max( 0.0, (float) $base ),
            'profit' => max( 0.0, (float) $profit ),
            'charge' => max( 0.0, (float) $charge ),
        ];

        return apply_filters( 'mnsnp/calculated_price', $result, $product_id );
    }

    // ------------------------------------------------------------------
    // Base: currency | formula
    // ------------------------------------------------------------------

    /**
     * Calculate Base by currency or formula (handles simple/advanced aliases).
     */
    protected function calculate_base( int $product_id ): float {
        $db  = DB::instance();

        // normalize dependence type
        $depRaw = (string) $this->get_meta_with_fallback( $product_id, 'dependence_type', 'currency' );
        $depRaw = strtolower( trim( $depRaw ) );
        // legacy UI aliases
        if ( $depRaw === 'advanced' ) { $dep = 'formula'; }
        elseif ( $depRaw === 'simple' ) { $dep = 'currency'; }
        else { $dep = $depRaw; } // 'currency' | 'formula'

        // ===== Formula path =====
        if ( $dep === 'formula' ) {
            $formula_id = (int) $this->get_meta_with_fallback( $product_id, 'formula_id', 0 );
            if ( $formula_id > 0 ) {
                $post = get_post( $formula_id );
                if ( $post && $post->post_type === 'mnswmc-formula' ) {
                    $F    = new FormulaModel( $post );
                    $expr = (string) $F->get_expression();

                    // env (variables + overrides + rate + ratio + weight)
                    $env = $this->build_formula_env( $product_id, $F, $formula_id );

                    // Evaluate expression → base
                    $eng  = new FormulaEngine();
                    $base = (float) $eng->evaluate( $expr, $env );
                    if ( ! $this->isFinite( $base ) ) { $base = 0.0; }

                    return (float) apply_filters(
                        'mnsnp/calc/base/formula/result',
                        max( 0.0, $base ),
                        $product_id,
                        $formula_id,
                        $env,
                        $this
                    );
                }
            }

            // Fallback hook if formula invalid/missing
            $fallback = apply_filters( 'mnsnp/calc/base/formula', null, $product_id, $this );
            return is_numeric( $fallback ) ? (float) $fallback : 0.0;
        }

        // ===== Currency path =====
        $currency_id = (int) $this->get_meta_with_fallback( $product_id, 'currency_id', 0 );
        if ( $currency_id <= 0 ) {
            $fallback = apply_filters( 'mnsnp/calc/base/no-currency', null, $product_id, $this );
            return is_numeric( $fallback ) ? (float) $fallback : 0.0;
        }

        $value = (float) $db->read_post_meta( $currency_id, 'currency_value', 0 );
        $ratio = (float) $this->get_meta_with_fallback( $product_id, 'ratio', 1 );
        if ( $ratio <= 0 ) { $ratio = 1.0; }

        $base = $value * $ratio;

        return (float) apply_filters( 'mnsnp/calc/base', $base, $product_id, $currency_id, $this );
    }

    // ------------------------------------------------------------------
    // Profit / Charge before discounts
    // ------------------------------------------------------------------

    /**
     * Compute profit & charge base values.
     * - If dependence = formula: read them from formula components (profit/charge).
     * - Else fallback to meta (percent|fixed).
     */
    protected function calculate_components( int $product_id, float $base ): array {
        // normalize dependence type (legacy aliases)
        $depRaw = strtolower( (string) $this->get_meta_with_fallback( $product_id, 'dependence_type', 'currency' ) );
        $dep    = ($depRaw === 'advanced') ? 'formula' : (($depRaw === 'simple') ? 'currency' : $depRaw);

        if ( $dep === 'formula' ) {
            $formula_id = (int) $this->get_meta_with_fallback( $product_id, 'formula_id', 0 );
            if ( $formula_id > 0 ) {
                $post = get_post( $formula_id );
                if ( $post && $post->post_type === 'mnswmc-formula' ) {
                    $F   = new FormulaModel( $post );
                    $env = $this->build_formula_env( $product_id, $F, $formula_id );

                    $profit = 0.0;
                    $charge = 0.0;

                    // نام‌ها/سیمبل‌های قابل‌قبول برای تشخیص کامپوننت‌ها
                    $map = apply_filters( 'mnsnp/calc/formula_components_map', [
                        'profit' => ['profit','سود'],
                        'charge' => ['charge','اجرت'],
                    ], $product_id, $formula_id, $this );

                    foreach ( (array) $F->get_components() as $component ) {
                        if ( ! is_object( $component ) || ! method_exists( $component, 'execute' ) ) continue;

                        $name   = function_exists('mb_strtolower') ? mb_strtolower( trim( (string) $component->get_name() ) )   : strtolower( trim( (string) $component->get_name() ) );
                        $symbol = function_exists('mb_strtolower') ? mb_strtolower( trim( (string) $component->get_symbol() ) ) : strtolower( trim( (string) $component->get_symbol() ) );

                        $val = (float) $component->execute( $env );
                        if ( ! $this->isFinite( $val ) ) $val = 0.0;

                        if ( in_array( $name, (array) $map['profit'], true ) || in_array( $symbol, (array) $map['profit'], true ) ) {
                            $profit += max(0.0, $val);
                        }
                        if ( in_array( $name, (array) $map['charge'], true ) || in_array( $symbol, (array) $map['charge'], true ) ) {
                            $charge += max(0.0, $val);
                        }
                    }

                    // اگر چیزی یافت شد، همان را به عنوان پایه سود/اجرت برمی‌گردانیم
                    if ( $profit > 0.0 || $charge > 0.0 ) {
                        $profit = (float) apply_filters( 'mnsnp/calc/profit_base', $profit, $product_id, $base, 'formula', null, $this );
                        $charge = (float) apply_filters( 'mnsnp/calc/charge_base', $charge, $product_id, $base, 'formula', null, $this );
                        return [ $profit, $charge ];
                    }
                }
            }
            // در غیر این صورت: fallback به درصد/ثابت
        }

        // --- Fallback: percent|fixed روی base ---
        $p_type = (string) $this->get_meta_with_fallback( $product_id, 'profit_type', 'percent' );
        $p_val  = (float)  $this->get_meta_with_fallback( $product_id, 'profit_value', 0 );
        $c_type = (string) $this->get_meta_with_fallback( $product_id, 'charge_type', 'percent' );
        $c_val  = (float)  $this->get_meta_with_fallback( $product_id, 'charge_value', 0 );

        $profit = ( $p_type === 'fixed' ) ? $p_val : ( $base * $p_val / 100 );
        $charge = ( $c_type === 'fixed' ) ? $c_val : ( $base * $c_val / 100 );

        $profit = (float) apply_filters( 'mnsnp/calc/profit_base', $profit, $product_id, $base, $p_type, $p_val, $this );
        $charge = (float) apply_filters( 'mnsnp/calc/charge_base', $charge, $product_id, $base, $c_type, $c_val, $this );

        return [ max(0.0, $profit), max(0.0, $charge) ];
    }

    /**
     * Build formula environment (variables + overrides + rate + ratio [+weight])
     */
    private function build_formula_env( int $product_id, FormulaModel $F, int $formula_id ): array {
        $db  = DB::instance();

        // defaults from formula variables
        $env = [];
        foreach ( $F->get_variables() as $v ) {
            $env[ $v->get_code() ] = (float) $v->get_value();
        }

        // product overrides (regular/sale)
        $overrides = $db->read_post_meta( $product_id, 'formula_variables', [] );
        if ( is_array( $overrides ) && ! empty( $overrides[ $formula_id ] ) ) {
            $use_sale = false;
            if ( function_exists( 'wc_get_product' ) ) {
                $p = wc_get_product( $product_id );
                if ( $p && method_exists( $p, 'is_on_sale' ) ) $use_sale = (bool) $p->is_on_sale();
            }
            foreach ( (array) $overrides[ $formula_id ] as $code => $pair ) {
                if ( is_array( $pair ) ) {
                    $val = $use_sale ? ($pair['sale'] ?? null) : ($pair['regular'] ?? null);
                    if ( $val !== null && $val !== '' ) {
                        $env[ $code ] = (float) $val;
                    }
                }
            }
        }

        // currency rate
        $currency_id = (int) $this->get_meta_with_fallback( $product_id, 'currency_id', 0 );
        if ( $currency_id > 0 ) {
            $env['rate'] = (float) $db->read_post_meta( $currency_id, 'currency_value', 0 );
        }

        // ratio
        $env['ratio'] = (float) $this->get_meta_with_fallback( $product_id, 'ratio', 1 );
        if ( $env['ratio'] <= 0 ) { $env['ratio'] = 1.0; }

        // weight (optional)
        if ( function_exists( 'wc_get_product' ) ) {
            $p = wc_get_product( $product_id );
            if ( $p ) {
                $w = (float) $p->get_weight();
                if ( $w > 0 ) { $env['weight'] = $w; }
            }
        }

        // dev hook
        return (array) apply_filters( 'mnsnp/calc/formula_env', $env, $product_id, $formula_id, $this );
    }

    // ------------------------------------------------------------------
    // Rounding & Bounds
    // ------------------------------------------------------------------

    protected function apply_rounding_and_bounds( int $product_id, float $price ): float {
        $r_type = strtolower( (string) $this->get_meta_with_fallback( $product_id, 'rounding_type', 'none' ) );
        $r_step = (float)  $this->get_meta_with_fallback( $product_id, 'rounding_value', 0 );
        $r_side = strtolower( (string) $this->get_meta_with_fallback( $product_id, 'rounding_side', 'nearest' ) );
        if ( $r_side === 'close' ) { $r_side = 'nearest'; } // legacy compat

        // legacy: integer → step=1
        if ( $r_type === 'integer' && $r_step <= 0 ) {
            $r_step = 1.0;
        }

        // legacy: zero → toward-zero rounding with step
        if ( $r_type === 'zero' && $r_step > 0 ) {
            $q = $price / $r_step;
            $q = ( $q >= 0 ) ? floor( $q ) : ceil( $q );
            $price = $q * $r_step;
        }
        // new styles + legacy integer/round with side
        elseif ( $r_step > 0 ) {
            $q = $price / $r_step;
            switch ( $r_type ) {
                case 'ceil':
                    $price = ceil( $q ) * $r_step;
                    break;
                case 'floor':
                    $price = floor( $q ) * $r_step;
                    break;
                case 'round':
                case 'integer': // treat like 'round' with step
                    if ( $r_side === 'up' ) {
                        $price = ceil( $q ) * $r_step;
                    } elseif ( $r_side === 'down' ) {
                        $price = floor( $q ) * $r_step;
                    } else {
                        $price = round( $q ) * $r_step; // nearest
                    }
                    break;
                default:
                    // none
                    break;
            }
        }

        $ceil  = (float) $this->get_meta_with_fallback( $product_id, 'ceil_price', 0 );
        $floor = (float) $this->get_meta_with_fallback( $product_id, 'floor_price', 0 );

        if ( $ceil  > 0 && $price > $ceil  ) { $price = $ceil;  }
        if ( $floor > 0 && $price < $floor ) { $price = $floor; }

        return (float) apply_filters( 'mnsnp/calc/rounded_price', $price, $product_id, $this );
    }

    // ------------------------------------------------------------------
    // Utils
    // ------------------------------------------------------------------

    /**
     * Read meta with variation→parent fallback.
     */
    protected function get_meta_with_fallback( int $post_id, string $key, $default = '' ) {
        $db  = DB::instance();
        $val = $db->read_post_meta( $post_id, $key, '' );

        if ( $val === '' ) {
            $parent_id = (int) wp_get_post_parent_id( $post_id );
            if ( $parent_id ) {
                $val = $db->read_post_meta( $parent_id, $key, '' );
            }
        }

        return ( $val === '' ) ? $default : $val;
    }

    /** Finite-number check (safe for older PHP) */
    private function isFinite( $n ): bool {
        if ( ! is_numeric( $n ) ) return false;
        if ( function_exists( 'is_infinite' ) && is_infinite( $n ) ) return false;
        if ( function_exists( 'is_nan' ) && is_nan( $n ) ) return false;
        return true;
    }
}