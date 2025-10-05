<?php
namespace MNS\NavasanPlus\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\DB;
use MNS\NavasanPlus\Templates\Classes\Snippets;

final class MetaBoxes {

    private static bool $booted = false;
    private static bool $printed_simple_box = false;

    public function run(): void {
        if ( self::$booted ) return;
        self::$booted = true;
        add_action( 'add_meta_boxes', [ $this, 'add_boxes' ] );
        add_action( 'save_post_mnsnp_currency', [ $this, 'save_currency' ], 10, 2 );
        add_action( 'save_post_mnsnp_formula',  [ $this, 'save_formula'  ], 10, 2 );
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'render_product_fields_simple' ], 25 );
        add_action( 'woocommerce_variation_options_pricing',            [ $this, 'render_product_fields_variation' ], 10, 3 );
        add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_formula_assets' ] );
        add_action( 'admin_notices', [ $this, 'maybe_show_debug_notice' ] );
    }

    public function maybe_enqueue_formula_assets(): void {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || $screen->post_type !== 'mnsnp_formula' ) return;
        if ( wp_script_is( 'mns-navasan-plus-formula-parser', 'registered' ) ) wp_enqueue_script( 'mns-navasan-plus-formula-parser' );
        if ( wp_script_is( 'mns-navasan-plus-admin', 'registered' ) ) wp_enqueue_script( 'mns-navasan-plus-admin' );
        if ( wp_style_is( 'mns-navasan-plus-admin', 'registered' ) ) wp_enqueue_style( 'mns-navasan-plus-admin' );
    }

    public function maybe_show_debug_notice(): void {
        if ( ! current_user_can('manage_woocommerce') ) return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ( ! $screen || $screen->post_type !== 'mnsnp_formula' ) return;
        $pid = isset($_GET['post']) ? absint($_GET['post']) : 0;
        if ( ! $pid ) return;
        $payload = get_transient( 'mnsnp_debug_' . $pid );
        if ( ! $payload ) return;
        delete_transient( 'mnsnp_debug_' . $pid );
        echo '<div class="notice notice-info"><p><strong>MNSNP DEBUG</strong></p><pre style="white-space:pre-wrap;max-height:360px;overflow:auto;">' . esc_html( $payload ) . '</pre></div>';
    }

    public function add_boxes(): void {
        add_meta_box('mnsnp_currency_box', __( 'Currency', 'mns-navasan-plus' ), [ $this, 'currency_output' ], 'mnsnp_currency', 'normal', 'high');
        add_meta_box('mnsnp_formula_box', __( 'Formula', 'mns-navasan-plus' ), [ $this, 'formula_output' ], 'mnsnp_formula', 'normal', 'high');
        add_meta_box('mnsnp_formula_components_box', __( 'Formula Components', 'mns-navasan-plus' ), [ $this, 'formula_components_output' ], 'mnsnp_formula', 'normal', 'default');
    }

    public function currency_output( \WP_Post $post ): void {
        $db = DB::instance();
        $currency = [
            'rate_symbol' => $db->read_post_meta( $post->ID, 'currency_rate_symbol', '' ),
            'code' => $db->read_post_meta( $post->ID, 'currency_code', '' ),
            'symbol' => $db->read_post_meta( $post->ID, 'currency_symbol', '' ),
            'value' => $db->read_post_meta( $post->ID, 'currency_value', 0 ),
            // Add other currency fields as needed
            'calculation_type' => $db->read_post_meta( $post->ID, 'currency_calculation_type', 'manual' ),
            'formula_text' => $db->read_post_meta( $post->ID, 'currency_formula_text', '' ),
            'profit' => $db->read_post_meta( $post->ID, 'currency_profit', 0 ),
            'fee' => $db->read_post_meta( $post->ID, 'currency_fee', 0 ),
            'ratio' => $db->read_post_meta( $post->ID, 'currency_ratio', 1 ),
            'fixed' => $db->read_post_meta( $post->ID, 'currency_fixed', 0 ),
            'update_type' => $db->read_post_meta( $post->ID, 'currency_update_type', 'none' ),
            'relation' => $db->read_post_meta( $post->ID, 'currency_relation', '' ),
            'connection' => $db->read_post_meta( $post->ID, 'currency_connection', '' ),
            'operation' => $db->read_post_meta( $post->ID, 'currency_operation', '+' ),
            'calculation_order' => $db->read_post_meta( $post->ID, 'currency_calculation_order', 0 ),
            'lowest_rate' => $db->read_post_meta( $post->ID, 'currency_lowest_rate', 0 ),
        ];
        Snippets::load_template( 'metaboxes/currency', [ 'post_id' => $post->ID, 'currency' => $currency ] );
    }

    public function formula_output( \WP_Post $post ): void {
        $db = DB::instance();
        $vars = get_post_meta( $post->ID, $db->full_meta_key( 'formula_variables' ), true );
        if ( ! is_array( $vars ) ) $vars = [];
        $expr   = (string) get_post_meta( $post->ID, $db->full_meta_key( 'formula_expression' ), true );
        $legacy = (string) get_post_meta( $post->ID, $db->full_meta_key( 'formul' ), true );
        $formula = [ 'variables' => $vars, 'variables_counter' => (int) get_post_meta( $post->ID, $db->full_meta_key( 'formula_variables_counter' ), true ), 'expression' => $expr, 'formul' => $legacy ];
        Snippets::load_template( 'metaboxes/formula', [ 'formula' => $formula ] );
    }

    public function formula_components_output( \WP_Post $post ): void {
        $db = DB::instance();
        $components = get_post_meta( $post->ID, $db->full_meta_key( 'formula_components' ), true );
        if ( ! is_array( $components ) ) $components = [];
        $counter = (int) get_post_meta( $post->ID, $db->full_meta_key( 'formula_components_counter' ), true );
        $formula = [ 'components' => $components, 'components_counter' => $counter ];
        Snippets::load_template( 'metaboxes/formula-components', [ 'formula' => $formula, 'components' => $components ] );
    }

    public function save_currency( int $post_id, \WP_Post $post ): void {
        if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! $post || $post->post_type !== 'mnsnp_currency' ) return;
        if ( ! current_user_can( 'manage_woocommerce' ) ) return;
        if ( empty( $_POST['_mns_navasan_plus_currency_nonce'] ) || ! wp_verify_nonce( $_POST['_mns_navasan_plus_currency_nonce'], 'mns_navasan_plus_currency' ) ) return;
        
        $db = DB::instance();
        
        // Currency Code
        if ( isset( $_POST['mns_navasan_plus_currency_code'] ) ) {
            $code = sanitize_text_field( wp_unslash( $_POST['mns_navasan_plus_currency_code'] ) );
            $db->update_post_meta( $post_id, 'currency_code', $code );
        }
        
        // Currency Symbol
        if ( isset( $_POST['mns_navasan_plus_currency_symbol'] ) ) {
            $symbol = sanitize_text_field( wp_unslash( $_POST['mns_navasan_plus_currency_symbol'] ) );
            $db->update_post_meta( $post_id, 'currency_symbol', $symbol );
        }
        
        // Legacy rate symbol
        if ( isset( $_POST['mns_navasan_plus_currency_rate_symbol'] ) ) {
            $rate_symbol = sanitize_text_field( wp_unslash( $_POST['mns_navasan_plus_currency_rate_symbol'] ) );
            $db->update_post_meta( $post_id, 'currency_rate_symbol', $rate_symbol );
        }
        
        // Currency Value
        if ( isset( $_POST['mns_navasan_plus_currency_value'] ) ) {
            $rate = wc_format_decimal( wp_unslash( $_POST['mns_navasan_plus_currency_value'] ), 6 );
            $db->update_post_meta( $post_id, 'currency_value', $rate );
        }
        
        // Other currency fields
        $fields = [
            'currency_calculation_type' => 'sanitize_text_field',
            'currency_formula_text' => 'sanitize_textarea_field', 
            'currency_profit' => 'floatval',
            'currency_fee' => 'floatval',
            'currency_ratio' => 'floatval',
            'currency_fixed' => 'floatval',
            'currency_update_type' => 'sanitize_text_field',
            'currency_relation' => 'sanitize_text_field',
            'currency_connection' => 'sanitize_text_field',
            'currency_operation' => 'sanitize_text_field',
            'currency_calculation_order' => 'intval',
            'currency_lowest_rate' => 'floatval',
        ];
        
        foreach ( $fields as $field => $sanitizer ) {
            $post_key = 'mns_navasan_plus_' . $field;
            if ( isset( $_POST[$post_key] ) ) {
                $value = wp_unslash( $_POST[$post_key] );
                $value = $sanitizer( $value );
                $db->update_post_meta( $post_id, $field, $value );
            }
        }
        
        // Legacy compatibility
        if ( isset( $_POST['mnswmc_currency_value'] ) ) {
            $rate = wc_format_decimal( wp_unslash( $_POST['mnswmc_currency_value'] ), 6 );
            $db->update_post_meta( $post_id, 'currency_value', $rate );
        }
        if ( isset( $_POST['mnswmc_currency_rate_symbol'] ) ) {
            $sym = sanitize_text_field( wp_unslash( $_POST['mnswmc_currency_rate_symbol'] ) );
            $db->update_post_meta( $post_id, 'currency_rate_symbol', $sym );
        }
    }

    public function save_formula( int $post_id, \WP_Post $post ): void {
        if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! $post || $post->post_type !== 'mnsnp_formula' ) return;
        if ( ! current_user_can( 'manage_woocommerce' ) ) return;
        if ( empty( $_POST['_mns_navasan_plus_formula_nonce'] ) || ! wp_verify_nonce( $_POST['_mns_navasan_plus_formula_nonce'], 'mns_navasan_plus_formula' ) ) return;
        $db = DB::instance();
        
        $expr = isset( $_POST['_mns_navasan_plus_formula_expression'] ) ? wp_kses_post( wp_unslash( $_POST['_mns_navasan_plus_formula_expression'] ) ) : '';
        $db->update_post_meta( $post_id, 'formula_expression', $expr );
        
        $vars_in  = $_POST['_mns_navasan_plus_formula_variables'] ?? [];
        $vars_out = [];
        if ( is_array( $vars_in ) ) {
            foreach ( $vars_in as $code => $v ) {
                $code = sanitize_key( $code );
                if ( $code === '' ) continue;
                
                $role = isset( $v['role'] ) ? (string) $v['role'] : 'none';
                if ( ! in_array( $role, [ 'none', 'profit', 'charge', 'weight' ], true ) ) {
                    $role = 'none';
                }

                $vars_out[ $code ] = [
                    'name'         => sanitize_text_field( $v['name'] ?? '' ),
                    'type'         => ( ( $v['type'] ?? 'custom' ) === 'currency' ) ? 'currency' : 'custom',
                    'currency_id'  => (int) ( $v['currency_id'] ?? 0 ),
                    'unit'         => ( isset($v['unit']) && $v['unit'] !== '' && $v['unit'] !== null ) ? (float) $v['unit'] : '',
                    'unit_symbol'  => sanitize_text_field( $v['unit_symbol'] ?? '' ),
                    'value'        => ( isset($v['value']) && $v['value'] !== '' && $v['value'] !== null ) ? (float) $v['value'] : '',
                    'value_symbol' => sanitize_text_field( $v['value_symbol'] ?? '' ),
                    'role'         => $role,
                ];
            }
        }
        $db->update_post_meta( $post_id, 'formula_variables', $vars_out );
        
        $comps_in = $_POST['_mns_navasan_plus_formula_components'] ?? [];
        $comps_out = [];
        if ( is_array( $comps_in ) ) {
            foreach ( $comps_in as $idx => $c ) {
                if ( $idx === '_sentinel' || ! is_array( $c ) ) continue;
                $name   = isset( $c['name'] ) ? sanitize_text_field( $c['name'] ) : '';
                $rawExp = $c['text'] ?? ( $c['expression'] ?? '' );
                $text   = $rawExp !== '' ? wp_kses_post( wp_unslash( $rawExp ) ) : '';
                $symbol = isset( $c['symbol'] ) ? sanitize_text_field( $c['symbol'] ) : '';
                $role   = isset( $c['role'] ) ? sanitize_text_field( $c['role'] ) : 'none';
                if ( ! in_array( $role, ['none','profit','charge'], true ) ) $role = 'none';
                if ( $name === '' && trim( (string) $text ) === '' && $symbol === '' ) continue;
                $comps_out[] = [ 'name' => $name, 'text' => $text, 'symbol' => $symbol, 'role' => $role ];
            }
        }
        $db->update_post_meta( $post_id, 'formula_components', $comps_out );
        
        if ( isset( $_POST['_mns_navasan_plus_formula_components_counter'] ) ) {
            $db->update_post_meta($post_id, 'formula_components_counter', absint( $_POST['_mns_navasan_plus_formula_components_counter'] ) );
        }
        if ( isset( $_POST['_mns_navasan_plus_formula_variables_counter'] ) ) {
            $db->update_post_meta($post_id, 'formula_variables_counter', absint( $_POST['_mns_navasan_plus_formula_variables_counter'] ) );
        }
    }
    
    public function render_product_fields_simple(): void {
        if ( self::$printed_simple_box ) return;
        self::$printed_simple_box = true;
        
        global $post, $thepostid, $product_object;
        $thepostid = $thepostid ?? ( $post->ID ?? 0 );
        $product = $product_object ?: wc_get_product( $thepostid );
        
        if ( ! $product ) return;
        
        Snippets::load_template( 'metaboxes/product', [
            'product' => $product,
            'loop'    => null,
        ] );
    }
    
    public function render_product_fields_variation( $loop, $variation_data, $variation ): void {
        $product = wc_get_product( $variation->ID );
        
        if ( ! $product ) return;
        
        Snippets::load_template( 'metaboxes/product', [
            'product' => $product,
            'loop'    => $loop,
        ] );
    }
}