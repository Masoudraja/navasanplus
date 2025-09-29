<?php
/**
 * PublicNS\Product
 *
 * A thin layer over WC_Product that reads "Navasan Plus" metadata
 * and provides several helper methods for use in templates.
 *
 * File: includes/PublicNS/Product.php
 */

namespace MNS\NavasanPlus\PublicNS;

use MNS\NavasanPlus\Admin\Options;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Product {

    /** @var \WC_Product */
    protected $wc;

    public function __construct( \WC_Product $product ) {
        $this->wc = $product;
    }

    /** Access to WooCommerce object */
    public function get_wc(): \WC_Product {
        return $this->wc;
    }

    /** ID Product */
    public function get_id(): int {
        return (int) $this->wc->get_id();
    }

    /** Name Product */
    public function get_name(): string {
        return (string) $this->wc->get_name();
    }

    /** Is rate-based pricing active? */
    public function is_rate_based(): bool {
        return (bool) $this->get_meta( '_mns_navasan_plus_active', false );
    }

    /** Dependency type (simple|advanced) */
    public function get_dependence_type(): string {
        $type = (string) $this->get_meta( '_mns_navasan_plus_dependence_type', 'simple' );
        return in_array( $type, [ 'simple', 'advanced' ], true ) ? $type : 'simple';
    }

    /** Base currency ID of product (if set) */
    public function get_currency_id(): int {
        return (int) $this->get_meta( '_mns_navasan_plus_currency_id', 0 );
    }

    /**
     * List of currencies related to product
     * Currently we only return one base currency (multi-currency can be added later if needed)
     *
     * @return Currency[]
     */
    public function get_currencies(): array {
        $cid = $this->get_currency_id();
        if ( $cid > 0 && class_exists( __NAMESPACE__ . '\\Currency' ) ) {
            $post = get_post( $cid );
            if ( $post ) {
                return [ new Currency( $post ) ];
            }
        }
        return [];
    }

    /** Current product rate (base currency rate or 0) */
    public function get_rate(): float {
        $currs = $this->get_currencies();
        return ! empty( $currs ) ? (float) $currs[0]->get_rate() : 0.0;
    }

    /** Profit type (percent|fixed) */
    public function get_profit_type(): string {
        $t = (string) $this->get_meta( '_mns_navasan_plus_profit_type', 'percent' );
        return in_array( $t, [ 'percent', 'fixed' ], true ) ? $t : 'percent';
    }

    /** Profit value */
    public function get_profit_value(): float {
        return (float) $this->get_meta( '_mns_navasan_plus_profit_value', 0 );
    }

    /** Rounding settings (type|value|side) considering global settings as default */
    public function get_rounding(): array {
        $global = class_exists( Options::class ) ? Options::get_global_rounding() : [
            'type'  => 'zero',
            'value' => 0,
            'side'  => 'close',
        ];
        $type  = (string) $this->get_meta( '_mns_navasan_plus_rounding_type',  $global['type']  ?? 'zero' );
        $value = (float)  $this->get_meta( '_mns_navasan_plus_rounding_value', $global['value'] ?? 0 );
        $side  = (string) $this->get_meta( '_mns_navasan_plus_rounding_side',  $global['side']  ?? 'close' );

        return [
            'type'  => in_array( $type, [ 'none', 'zero', 'integer' ], true ) ? $type : 'zero',
            'value' => max( 0, $value ),
            'side'  => in_array( $side, [ 'close', 'up', 'down' ], true ) ? $side : 'close',
        ];
    }

    /** Price ceiling and floor (if set) */
    public function get_price_limits(): array {
        return [
            'ceil'  => (float) $this->get_meta( '_mns_navasan_plus_ceil_price',  0 ),
            'floor' => (float) $this->get_meta( '_mns_navasan_plus_floor_price', 0 ),
        ];
    }

    // ---------------------------------------------------------------------
    // Formulas (advanced)
    // ---------------------------------------------------------------------

    /** Selected formula ID for this product */
    public function get_formula_id(): int {
        return (int) $this->get_meta( '_mns_navasan_plus_formula_id', 0 );
    }

    /** Selected formula object (if Formula class is available) */
    public function get_formula(): ?Formula {
        $fid = $this->get_formula_id();
        if ( $fid > 0 && class_exists( __NAMESPACE__ . '\\Formula' ) ) {
            $post = get_post( $fid );
            if ( $post ) {
                return new Formula( $post );
            }
        }
        return null;
    }

    /**
     * Value of a formula variable for this product
     * - First from new aggregated meta: _mns_navasan_plus_formula_variables[ fid ][ code ]['regular']
     * - If not found, fallback to old key: _mns_navasan_plus_formula_{fid}_{code}_regular
     * - If still not found and $fallback_value is passed, use that
     * - Otherwise use variable's default value
     *
     * @param object     $variable       شیء Variable (FormulaVariable) با get_code()/get_value()
     * @param float|null $fallback_value
     * @param int|null   $formula_id     (optional) force to a specific formula
     * @return float
     */
    public function get_formula_variable( $variable, ?float $fallback_value = null, ?int $formula_id = null ): float {
        if ( ! is_object( $variable ) || ! method_exists( $variable, 'get_code' ) ) {
            return 0.0;
        }
        $fid  = $formula_id ?: $this->get_formula_id();
        $code = (string) $variable->get_code();

        // 1) New aggregated meta
        $map = $this->get_meta( '_mns_navasan_plus_formula_variables', null );
        if ( is_array( $map )
            && isset( $map[ $fid ], $map[ $fid ][ $code ], $map[ $fid ][ $code ]['regular'] )
            && $map[ $fid ][ $code ]['regular'] !== '' && $map[ $fid ][ $code ]['regular'] !== null
        ) {
            return (float) $map[ $fid ][ $code ]['regular'];
        }

        // 2) Fallback: old separate keys
        $legacy_key = sprintf( '_mns_navasan_plus_formula_%d_%s_regular', $fid, $code );
        $legacy_val = $this->get_meta( $legacy_key, null );
        if ( $legacy_val !== null && $legacy_val !== '' ) {
            return (float) $legacy_val;
        }

        // 3) Fallback to input/default value
        if ( $fallback_value !== null ) {
            return (float) $fallback_value;
        }
        if ( method_exists( $variable, 'get_value' ) ) {
            return (float) $variable->get_value();
        }
        return 0.0;
    }

    /**
     * Associative array of variables for executing formula parts/components
     * Output: ['code' => value, ...]
     *
     * @param float|null $fallback_value if you have a base input (e.g. weight value) pass it
     * @return array
     */
    public function get_formula_variables( ?float $fallback_value = null ): array {
        $out = [];
        $formula = $this->get_formula();
        if ( ! $formula ) {
            return $out;
        }
        foreach ( $formula->get_variables() as $var ) {
            $code          = (string) $var->get_code();
            $out[ $code ]  = $this->get_formula_variable( $var, $fallback_value, $formula->get_id() );
        }
        return $out;
    }

    /**
     * List of formula components related to this product (if class/method exists)
     * Output: array of objects that have at least get_name(), get_symbol(), execute($vars) methods.
     */
    public function get_formula_components(): array {
        $formula = $this->get_formula();
        if ( $formula && method_exists( $formula, 'get_components' ) ) {
            $comps = $formula->get_components();
            return is_array( $comps ) ? $comps : [];
        }
        return [];
    }

    // ---------------------------------------------------------------------
    // Price calculation helpers (simple)
    // ---------------------------------------------------------------------

    /**
     * Price calculation based on rate + profit + rounding (+ applying ceiling/floor if exists)
     *
     * @param float $base_number  base number (e.g. weight/quantity)
     * @return float
     */
    public function compute_rate_price( float $base_number ): float {
        $rate         = $this->get_rate();
        $profit_type  = $this->get_profit_type();
        $profit_value = $this->get_profit_value();
        $round        = $this->get_rounding();   // ['type','value','side']
        $limits       = $this->get_price_limits();

        // 1) Apply profit
        if ( $profit_type === 'percent' ) {
            $factor = max( -100.0, (float) $profit_value ) / 100.0 + 1.0;
            $price  = $base_number * $rate * $factor;
        } else { // fixed
            $price = $base_number * $rate + (float) $profit_value;
        }

        // 2) Rounding
        $price = $this->round_number( $price, (float) $round['value'], (string) $round['type'], (string) $round['side'] );

        // 3) Apply ceiling/floor
        if ( $limits['ceil'] > 0 && $price > $limits['ceil'] ) {
            $price = (float) $limits['ceil'];
        }
        if ( $limits['floor'] > 0 && $price < $limits['floor'] ) {
            $price = (float) $limits['floor'];
        }

        return max( 0.0, (float) $price );
    }

    /** Rounding with step and type (none|zero|integer) and direction (close|up|down) */
    protected function round_number( float $number, float $step = 0, string $type = 'zero', string $side = 'close' ): float {
        if ( $type === 'none' || $step <= 0 ) {
            return $number;
        }
        if ( $type === 'integer' ) {
            return (float) round( $number );
        }

        // type === 'zero'
        $factor = ( $step > 0 ) ? ( 1 / $step ) : 1;
        switch ( $side ) {
            case 'up':   return (float) ( ceil( $number * $factor ) / $factor );
            case 'down': return (float) ( floor( $number * $factor ) / $factor );
            case 'close':
            default:     return (float) ( round( $number * $factor ) / $factor );
        }
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    /** Read product meta (wrapper over WC_Product::get_meta) */
    protected function get_meta( string $key, $default = null ) {
        $val = $this->wc->get_meta( $key, true );
        return ( $val === '' || $val === null ) ? $default : $val;
    }
}