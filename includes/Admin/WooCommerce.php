<?php
namespace MNS\NavasanPlus\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\Services\PriceCalculator;
use MNS\NavasanPlus\DB;

final class WooCommerce {

    private static array $calc_guard = [];

    public function run(): void {
        // --- Save Actions
        add_action( 'woocommerce_admin_process_product_object', [ $this, 'save_product_object' ] );
        add_action( 'woocommerce_save_product_variation',       [ $this, 'save_product_variation' ], 10, 2 );

        // --- Final, Robust Price Filtering Architecture ---
        add_filter( 'woocommerce_product_get_price',         [ $this, 'set_and_filter_dynamic_prices' ], 20, 2 );
        add_filter( 'woocommerce_product_variation_get_price', [ $this, 'set_and_filter_dynamic_prices' ], 20, 2 );
        add_filter( 'woocommerce_variation_prices_price',      [ $this, 'set_and_filter_dynamic_prices' ], 20, 2 );
        
        add_action( 'init', [ $this, 'add_order_macros' ] );
        
        // Add order item meta display
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'add_order_item_meta' ], 10, 4 );
        add_filter( 'woocommerce_order_item_display_meta_key', [ $this, 'display_order_item_meta_key' ], 10, 3 );
        add_filter( 'woocommerce_order_item_display_meta_value', [ $this, 'display_order_item_meta_value' ], 10, 3 );
        
        // Add direct display for admin order pages (fallback)
        add_action( 'woocommerce_before_order_itemmeta', [ $this, 'display_order_item_breakdown' ], 10, 3 );
    }

    /**
     * This is the single entry point for dynamic pricing.
     * It calculates all prices, sets them on the product object to create a stable state,
     * and then returns the final active price. This prevents conflicts with WC validation.
     */
    public function set_and_filter_dynamic_prices( $price, $product ) {
        if ( ! $product instanceof \WC_Product ) {
            return $price;
        }

        $pid = $product->get_id();

        // Recursion guard: If we have already processed this product object in this request, return its current price.
        if ( isset( self::$calc_guard[ $pid ] ) ) {
            return $product->get_price('view');
        }

        // If Navasan Plus pricing is not active for this product, do nothing.
        if ( ! $this->product_meta_bool( $product, 'active', true ) ) {
            return $price;
        }

        self::$calc_guard[ $pid ] = true; // Set the guard
        $calculated_price = $price; // Default to original price if calculation fails

        try {
            if ( class_exists( PriceCalculator::class ) ) {
                $calc = PriceCalculator::instance()->calculate( $pid );

                if ( is_array( $calc ) && isset( $calc['price'] ) ) {
                    $price_after  = (float) $calc['price'];
                    $price_before = (float) ($calc['price_before'] ?? $price_after);
                    $has_discount = $price_before > $price_after;
                    
                    $regular_price = $has_discount ? $price_before : $price_after;
                    $sale_price    = $has_discount ? $price_after  : '';

                    // Set all price properties on the in-memory product object.
                    // This creates a stable and consistent state for WooCommerce to use.
                    $product->set_regular_price( $regular_price );
                    $product->set_sale_price( $sale_price );
                    
                    $calculated_price = $sale_price !== '' ? $sale_price : $regular_price;
                    $product->set_price( $calculated_price );
                }
            }
        } finally {
            unset( self::$calc_guard[ $pid ] ); // Unset the guard
        }
        
        return $calculated_price;
    }

    private function product_meta_bool( \WC_Product $product, string $suffix, bool $default = false ): bool {
        $key = DB::instance()->full_meta_key( $suffix );
        $raw = $product->get_meta( $key, true );
        if ( $raw === '' || $raw === null ) {
            if ( $product instanceof \WC_Product_Variation ) {
                $parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : null;
                if ( $parent ) {
                    $raw = $parent->get_meta( $key, true );
                }
            }
        }
        if ( $raw === '' || $raw === null ) {
            return $default;
        }
        return function_exists( 'wc_string_to_bool' ) ? wc_string_to_bool( (string) $raw ) : ( $raw === 'yes' || $raw === '1' || $raw === 1 || $raw === true );
    }

    private function vpost( string $base_key, int $i ) {
        if ( isset( $_POST[ $base_key ][ $i ] ) ) return wp_unslash( $_POST[ $base_key ][ $i ] );
        $with_prefix = '_variable' . $base_key;
        if ( isset( $_POST[ $with_prefix ][ $i ] ) ) return wp_unslash( $_POST[ $with_prefix ][ $i ] );
        return null;
    }

    public function save_product_object( \WC_Product $product ): void {
        if ( ! current_user_can( 'edit_product', $product->get_id() ) ) return;
        $db = DB::instance();
        foreach ( [ 'active', 'price_alert' ] as $key ) { $post_key = "_mns_navasan_plus_{$key}"; $val = isset( $_POST[ $post_key ] ) ? 'yes' : 'no'; $product->update_meta_data( $db->full_meta_key( $key ), $val ); }
        foreach ( [ 'dependence_type', 'rounding_type', 'rounding_side', 'profit_type' ] as $key ) { $post_key = "_mns_navasan_plus_{$key}"; if ( isset( $_POST[ $post_key ] ) ) { $product->update_meta_data( $db->full_meta_key( $key ), sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ) ); } }
        if ( isset( $_POST['_mns_navasan_plus_currency_id'] ) ) { $product->update_meta_data( $db->full_meta_key( 'currency_id' ), absint( wp_unslash( $_POST['_mns_navasan_plus_currency_id'] ) ) ); }
        $decimals = [ 'profit_value', 'rounding_value', 'ceil_price', 'floor_price' ];
        foreach ( $decimals as $key ) { $post_key = "_mns_navasan_plus_{$key}"; if ( isset( $_POST[ $post_key ] ) ) { $product->update_meta_data( $db->full_meta_key( $key ), wc_format_decimal( wp_unslash( $_POST[ $post_key ] ) ) ); } }
        if ( isset( $_POST['_mns_navasan_plus_formula_id'] ) ) { $product->update_meta_data( $db->full_meta_key( 'formula_id' ), absint( wp_unslash( $_POST['_mns_navasan_plus_formula_id'] ) ) ); }
        if ( isset( $_POST['_mns_navasan_plus_formula_variables'] ) && is_array( $_POST['_mns_navasan_plus_formula_variables'] ) ) { $vars = []; foreach ( $_POST['_mns_navasan_plus_formula_variables'] as $fid => $codes ) { $fid = (int) $fid; foreach ( (array) $codes as $code => $vals ) { $code = sanitize_key( $code ); $vars[ $fid ][ $code ] = [ 'regular' => isset( $vals['regular'] ) ? sanitize_text_field( wp_unslash( $vals['regular'] ) ) : '', 'sale'    => isset( $vals['sale'] )    ? sanitize_text_field( wp_unslash( $vals['sale'] ) )    : '', ]; } } $product->update_meta_data( $db->full_meta_key( 'formula_variables' ), $vars ); }
    }

    public function save_product_variation( int $variation_id, int $i ): void {
        if ( ! current_user_can( 'edit_product', $variation_id ) ) return;
        $db = DB::instance();
        foreach ( [ 'active', 'price_alert' ] as $key ) { $base = "_mns_navasan_plus_{$key}"; $exists = isset( $_POST[ $base ][ $i ] ) || isset( $_POST[ '_variable' . $base ][ $i ] ); update_post_meta( $variation_id, $db->full_meta_key( $key ), $exists ? 'yes' : 'no' ); }
        foreach ( [ 'dependence_type', 'rounding_type', 'rounding_side', 'profit_type' ] as $key ) { $base = "_mns_navasan_plus_{$key}"; $raw = $this->vpost( $base, $i ); if ( $raw !== null ) update_post_meta( $variation_id, $db->full_meta_key( $key ), sanitize_text_field( $raw ) ); }
        $raw = $this->vpost( '_mns_navasan_plus_currency_id', $i ); if ( $raw !== null ) update_post_meta( $variation_id, $db->full_meta_key( 'currency_id' ), absint( $raw ) );
        $decimals = [ 'profit_value', 'rounding_value', 'ceil_price', 'floor_price' ];
        foreach ( $decimals as $key ) { $base = "_mns_navasan_plus_{$key}"; $raw = $this->vpost( $base, $i ); if ( $raw !== null ) update_post_meta( $variation_id, $db->full_meta_key( $key ), wc_format_decimal( $raw ) ); }
        $raw = $this->vpost( '_mns_navasan_plus_formula_id', $i ); if ( $raw !== null ) update_post_meta( $variation_id, $db->full_meta_key( 'formula_id' ), absint( $raw ) );
        $vars_payload = $_POST['_mns_navasan_plus_formula_variables'] ?? ( $_POST['_variable_mns_navasan_plus_formula_variables'] ?? null );
        if ( is_array( $vars_payload ) ) { $vars = []; foreach ( $vars_payload as $fid => $codes ) { $fid = (int) $fid; foreach ( (array) $codes as $code => $vals ) { $code = sanitize_key( $code ); $reg = is_array( $vals['regular'] ?? null ) ? ( $vals['regular'][ $i ] ?? '' ) : ( $vals['regular'] ?? '' ); $sal = is_array( $vals['sale'] ?? null ) ? ( $vals['sale'][ $i ] ?? '' ) : ( $vals['sale'] ?? '' ); $vars[ $fid ][ $code ] = [ 'regular' => sanitize_text_field( wp_unslash( $reg ) ), 'sale'    => sanitize_text_field( wp_unslash( $sal ) ), ]; } } update_post_meta( $variation_id, $db->full_meta_key( 'formula_variables' ), $vars ); }
    }

    public function add_order_macros(): void {
        if ( method_exists( 'WC_Order', 'macro' ) && ! method_exists( 'WC_Order', 'get_currency_rate' ) ) {
            \WC_Order::macro( 'get_currency_rate', function( $currency_id ) {
                /** @var \WC_Order $this */
                $currency_id = (int) $currency_id; $key = \MNS\NavasanPlus\DB::instance()->full_meta_key( 'currency_' . $currency_id . '_rate' );
                foreach ( $this->get_items( 'fee' ) as $item ) { $rate = $item->get_meta( $key, true ); if ( $rate !== '' ) return (float) $rate; }
                foreach ( $this->get_items() as $item ) { $rate = $item->get_meta( $key, true ); if ( $rate !== '' ) return (float) $rate; }
                return 0.0;
            } );
        }
    }
    
    /**
     * Add order item meta data for weight, profit, and charge
     */
    public function add_order_item_meta( $item, $cart_item_key, $values, $order ): void {
        $product = $item->get_product();
        if ( ! $product ) return;
        
        $db = DB::instance();
        
        // Check if this product uses Navasan Plus pricing
        $is_active = $this->product_meta_bool( $product, 'active' );
        $dep_type = $product->get_meta( $db->full_meta_key('dependence_type'), true ) ?: 'simple';
        
        if ( ! $is_active || ! in_array($dep_type, ['advanced', 'formula']) ) {
            return;
        }
        
        $fid = (int) $product->get_meta( $db->full_meta_key('formula_id'), true );
        if ( $fid <= 0 ) {
            return;
        }
        
        // Get formula variables and overrides
        $vars_meta = get_post_meta( $fid, $db->full_meta_key('formula_variables'), true );
        $overAll = $product->get_meta( $db->full_meta_key('formula_variables'), true );
        
        $vars_meta = is_array($vars_meta) ? $vars_meta : [];
        $overAll = is_array($overAll) ? $overAll : [];
        $overrides = $overAll[$fid] ?? [];
        
        if ( empty($vars_meta) ) return;
        
        // Extract weight, profit, and charge values
        foreach ( $vars_meta as $code => $row ) {
            $role = $row['role'] ?? 'none';
            if ( in_array($role, ['weight', 'profit', 'charge']) ) {
                // Try to get override value first, then fallback to formula default
                $value = null;
                if ( isset($overrides[$code]['regular']) && $overrides[$code]['regular'] !== '' ) {
                    $value = $overrides[$code]['regular'];
                } elseif ( isset($overrides[$code]) && !is_array($overrides[$code]) && $overrides[$code] !== '' ) {
                    $value = $overrides[$code];
                } elseif ( isset($row['value']) && $row['value'] !== '' ) {
                    $value = $row['value'];
                }
                
                if ( $value !== null && $value !== '' ) {
                    $label = $row['name'] ?? $role;
                    $symbol = $row['value_symbol'] ?? '';
                    
                    // Format the value based on role
                    if ( $role === 'weight' ) {
                        $formatted_value = number_format_i18n( (float)$value, 3 ) . ' ' . ($symbol ?: __('grams', 'mns-navasan-plus'));
                    } else {
                        // Remove decimals for profit and charge
                        $formatted_value = number_format_i18n( (int)$value ) . ' ' . $symbol;
                    }
                    
                    // Add as order item meta
                    $item->add_meta_data( $label, $formatted_value, true );
                }
            }
        }
    }
    
    /**
     * Display custom meta key labels in orders
     */
    public function display_order_item_meta_key( $display_key, $meta, $item ): string {
        // Return the key as-is since we're already using proper labels
        return $display_key;
    }
    
    /**
     * Display custom meta values in orders
     */
    public function display_order_item_meta_value( $display_value, $meta, $item ): string {
        // Return the value as-is since we're already formatting it properly
        return $display_value;
    }
    
    /**
     * Display breakdown info directly in order items section (with duplication prevention)
     */
    public function display_order_item_breakdown( $item_id, $item, $product ): void {
        if ( ! $product || ! is_admin() ) return;
        
        // Prevent duplicate display - check if meta is already showing
        static $displayed_items = [];
        if ( isset($displayed_items[$item_id]) ) return;
        
        $db = DB::instance();
        
        // Check if this product uses Navasan Plus pricing
        $is_active = $this->product_meta_bool( $product, 'active' );
        $dep_type = $product->get_meta( $db->full_meta_key('dependence_type'), true ) ?: 'simple';
        
        if ( ! $is_active || ! in_array($dep_type, ['advanced', 'formula']) ) {
            return;
        }
        
        $fid = (int) $product->get_meta( $db->full_meta_key('formula_id'), true );
        if ( $fid <= 0 ) {
            return;
        }
        
        // Get formula variables and overrides
        $vars_meta = get_post_meta( $fid, $db->full_meta_key('formula_variables'), true );
        $overAll = $product->get_meta( $db->full_meta_key('formula_variables'), true );
        
        $vars_meta = is_array($vars_meta) ? $vars_meta : [];
        $overAll = is_array($overAll) ? $overAll : [];
        $overrides = $overAll[$fid] ?? [];
        
        if ( empty($vars_meta) ) return;
        
        // Check if item already has this meta (from checkout)
        $existing_meta = $item->get_meta_data();
        $has_breakdown_meta = false;
        foreach ( $existing_meta as $meta ) {
            $meta_data = $meta->get_data();
            foreach ( $vars_meta as $code => $row ) {
                if ( ($row['role'] ?? '') === 'weight' && $meta_data['key'] === ($row['name'] ?? 'weight') ) {
                    $has_breakdown_meta = true;
                    break 2;
                }
            }
        }
        
        // If meta already exists, don't display again
        if ( $has_breakdown_meta ) {
            $displayed_items[$item_id] = true;
            return;
        }
        
        // Extract weight, profit, and charge values
        $display_data = [];
        foreach ( $vars_meta as $code => $row ) {
            $role = $row['role'] ?? 'none';
            if ( in_array($role, ['weight', 'profit', 'charge']) ) {
                $value = null;
                if ( isset($overrides[$code]['regular']) && $overrides[$code]['regular'] !== '' ) {
                    $value = $overrides[$code]['regular'];
                } elseif ( isset($overrides[$code]) && !is_array($overrides[$code]) && $overrides[$code] !== '' ) {
                    $value = $overrides[$code];
                } elseif ( isset($row['value']) && $row['value'] !== '' ) {
                    $value = $row['value'];
                }
                
                if ( $value !== null && $value !== '' ) {
                    $label = $row['name'] ?? $role;
                    $symbol = $row['value_symbol'] ?? '';
                    
                    if ( $role === 'weight' ) {
                        $formatted_value = number_format_i18n( (float)$value, 3 ) . ' ' . ($symbol ?: __('grams', 'mns-navasan-plus'));
                    } else {
                        $formatted_value = number_format_i18n( (int)$value ) . ' ' . $symbol;
                    }
                    
                    $display_data[$role] = [
                        'label' => $label,
                        'value' => $formatted_value
                    ];
                }
            }
        }
        
        if ( empty($display_data) ) return;
        
        $displayed_items[$item_id] = true;
        
        // Display in compact table format (following user's table layout preference)
        echo '<div class="mnsnp-order-breakdown" style="margin: 5px 0; padding: 5px; background: #f9f9f9; border: 1px solid #e1e1e1;">';
        echo '<table style="width: 100%; border-collapse: collapse; font-size: 11px; line-height: 1.2;">';
        
        foreach ( $display_data as $role => $data ) {
            echo '<tr style="border-bottom: 1px solid #ddd;">';
            echo '<td style="padding: 3px 5px; font-weight: 600; width: 35%; text-align: right; border-right: 1px solid #ddd;">' . esc_html($data['label']) . ':</td>';
            echo '<td style="padding: 3px 5px; text-align: right; color: #333;">' . esc_html($data['value']) . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</div>';
    }
}