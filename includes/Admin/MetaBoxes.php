<?php
namespace MNS\NavasanPlus\Admin;

use MNS\NavasanPlus\DB;
use MNS\NavasanPlus\Helpers;
use MNS\NavasanPlus\Templates\Classes\Snippets;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles registration, display and saving of the Currency, Formula (and optionally Chart) meta-boxes.
 */
class MetaBoxes {

    public function run() {
        add_action( 'add_meta_boxes', [ $this, 'register' ] );
        add_action( 'save_post',      [ $this, 'currency_save' ] );
        add_action( 'save_post',      [ $this, 'formula_save' ] );

        // —— CHART SECTION DISABLED (not used for now) ——
        // add_action( 'save_post',   [ $this, 'chart_save' ] );
    }

    public function register() {
        // ----- Currency (CPT: mnswmc) -----
        add_meta_box(
            'mns_navasan_plus_currency_options',
            __( 'Currency Options', 'mns-navasan-plus' ),
            [ $this, 'currency_output' ],
            'mnswmc',
            'normal',
            'default',
            [ 'meta' => 'options' ]
        );

        // Chart metabox is optional (behind a filter)
        $enable_chart = (bool) apply_filters( 'mnsnp/enable_currency_chart_metabox', false );
        if ( $enable_chart ) {
            add_meta_box(
                'mns_navasan_plus_currency_chart',
                __( 'Currency Chart', 'mns-navasan-plus' ),
                [ $this, 'currency_output' ],
                'mnswmc',
                'normal',
                'default',
                [ 'meta' => 'chart' ]
            );
        }

        // ----- Formula (CPT: mnswmc-formula) -----
        add_meta_box(
            'mns_navasan_plus_formula_variables',
            __( 'Formula', 'mns-navasan-plus' ),
            [ $this, 'formula_output' ],
            'mnswmc-formula',
            'normal',
            'default',
            [ 'meta' => 'variables' ]
        );

        add_meta_box(
            'mns_navasan_plus_formula_components',
            __( 'Formula Components', 'mns-navasan-plus' ),
            [ $this, 'formula_output' ],
            'mnswmc-formula',
            'normal',
            'default',
            [ 'meta' => 'components' ]
        );

        // ----- Chart (CPT: mnswmc-chart) — DISABLED for now -----
        /*
        add_meta_box(
            'mns_navasan_plus_chart_options',
            __( 'Chart Options', 'mns-navasan-plus' ),
            [ $this, 'chart_output' ],
            'mnswmc-chart',
            'normal',
            'default',
            [ 'meta' => 'options' ]
        );
        add_meta_box(
            'mns_navasan_plus_chart_items',
            __( 'Chart Items', 'mns-navasan-plus' ),
            [ $this, 'chart_output' ],
            'mnswmc-chart',
            'normal',
            'default',
            [ 'meta' => 'items' ]
        );
        */
    }

    // -------------------- Outputs --------------------

    public function currency_output( $post, $args ) {
        // Enqueue admin assets on currency editor (for consistent UI)
        wp_enqueue_style( 'mns-navasan-plus-admin' );
        wp_enqueue_script( 'mns-navasan-plus-admin' );

        $id = (int) $post->ID;

        $currency = [
            'rate_symbol'       => DB::instance()->read_post_meta( $id, 'currency_rate_symbol', '' ),
            'value'             => DB::instance()->read_post_meta( $id, 'currency_value', 1 ),
            'calculation_type'  => DB::instance()->read_post_meta( $id, 'currency_calculation_type', '' ),
            'formula_text'      => DB::instance()->read_post_meta( $id, 'currency_formula_text', '' ),
            'profit'            => DB::instance()->read_post_meta( $id, 'currency_profit', 0.00 ),
            'fee'               => DB::instance()->read_post_meta( $id, 'currency_fee', 0.00 ),
            'ratio'             => DB::instance()->read_post_meta( $id, 'currency_ratio', 1.00 ),
            'fixed'             => DB::instance()->read_post_meta( $id, 'currency_fixed', 0 ),
            'update_type'       => DB::instance()->read_post_meta( $id, 'currency_update_type', 'none' ),
            'relation'          => DB::instance()->read_post_meta( $id, 'currency_relation', '' ),
            'connection'        => DB::instance()->read_post_meta( $id, 'currency_connection', '' ),
            'operation'         => DB::instance()->read_post_meta( $id, 'currency_operation', '' ),
            'calculation_order' => DB::instance()->read_post_meta( $id, 'currency_calculation_order', '' ),
            'lowest_rate'       => DB::instance()->read_post_meta( $id, 'currency_lowest_rate', 0 ),
        ];

        $meta = $args['args']['meta'] ?? 'options';
        if ( $meta === 'options' ) {
            Snippets::load_template( 'metaboxes/currency', [ 'currency' => $currency ] );
        } else {
            Snippets::load_template( 'metaboxes/currency-chart', [ 'currency' => $currency ] );
        }
    }

    public function formula_output( $post, $args ) {
        // Enqueue only on formula editor: parser + our admin UI
        wp_enqueue_style( 'mns-navasan-plus-admin' );

        // Ensure formula-parser is registered in admin too (fallback if not registered by Loader)
        if ( ! wp_script_is( 'mns-navasan-plus-formula-parser', 'registered' ) ) {
            $use_min = ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );
            $rel     = $use_min ? 'assets/js/formula-parser.min.js' : 'assets/js/formula-parser.js';
            $src     = \MNS\NavasanPlus\Helpers::plugin_url( $rel );
            wp_register_script(
                'mns-navasan-plus-formula-parser',
                $src,
                [],
                \MNS\NavasanPlus\Plugin::instance()->version(),
                true
            );
        }
        if ( ! wp_script_is( 'mns-navasan-plus-formula-parser', 'enqueued' ) ) {
            wp_enqueue_script( 'mns-navasan-plus-formula-parser' );
        }

        wp_enqueue_script( 'mns-navasan-plus-admin' );

        $id = (int) $post->ID;

        $formula = [
            // legacy key 'formul' (we keep saving into 'formula_formul')
            'formul'             => DB::instance()->read_post_meta( $id, 'formula_formul', '' ),
            'variables_counter'  => (int) DB::instance()->read_post_meta( $id, 'formula_variables_counter', 1 ),
            'variables'          => DB::instance()->read_post_meta( $id, 'formula_variables', [] ),
            'components_counter' => (int) DB::instance()->read_post_meta( $id, 'formula_components_counter', 1 ),
            'components'         => DB::instance()->read_post_meta( $id, 'formula_components', [] ),
        ];

        $meta = $args['args']['meta'] ?? 'variables';
        if ( $meta === 'variables' ) {
            Snippets::load_template( 'metaboxes/formula', [ 'formula' => $formula ] );
        } else {
            Snippets::load_template( 'metaboxes/formula-components', [ 'formula' => $formula ] );
        }
    }

    // -------------------- Saves --------------------

    public function currency_save( $post_id ) {
        if ( ! $this->is_valid_save( $post_id, 'mnswmc', '_mns_navasan_plus_currency_nonce', 'mns_navasan_plus_currency' ) ) {
            return;
        }

        $db = DB::instance();

        // rate symbol
        if ( isset( $_POST['_mns_navasan_plus_currency_rate_symbol'] ) ) {
            $val = sanitize_text_field( wp_unslash( $_POST['_mns_navasan_plus_currency_rate_symbol'] ) );
            $db->update_post_meta( $post_id, 'currency_rate_symbol', $val );
            // Optional: mirror for legacy readers
            // $db->update_post_meta( $post_id, 'currency_symbol', $val );
        } else {
            $db->delete_post_meta( $post_id, 'currency_rate_symbol' );
        }

        // value (>=1)
        $value = Helpers::sanitize_number( wp_unslash( $_POST['_mns_navasan_plus_currency_value'] ?? '' ) );
        $db->update_post_meta( $post_id, 'currency_value', $value > 0 ? $value : 1 );

        // calculation type
        if ( isset( $_POST['_mns_navasan_plus_currency_calculation_type'] ) ) {
            $db->update_post_meta(
                $post_id,
                'currency_calculation_type',
                sanitize_text_field( wp_unslash( $_POST['_mns_navasan_plus_currency_calculation_type'] ) )
            );
        } else {
            $db->delete_post_meta( $post_id, 'currency_calculation_type' );
        }

        // formula text
        if ( isset( $_POST['_mns_navasan_plus_currency_formula_text'] ) ) {
            $db->update_post_meta(
                $post_id,
                'currency_formula_text',
                sanitize_text_field( wp_unslash( $_POST['_mns_navasan_plus_currency_formula_text'] ) )
            );
        } else {
            $db->delete_post_meta( $post_id, 'currency_formula_text' );
        }

        // profit/fee (0..100)
        $profit = Helpers::sanitize_number( wp_unslash( $_POST['_mns_navasan_plus_currency_profit'] ?? 0 ) );
        $fee    = Helpers::sanitize_number( wp_unslash( $_POST['_mns_navasan_plus_currency_fee'] ?? 0 ) );
        $db->update_post_meta( $post_id, 'currency_profit', max( 0, min( 100, $profit ) ) );
        $db->update_post_meta( $post_id, 'currency_fee',    max( 0, min( 100, $fee ) ) );

        // ratio (>0)
        $ratio = Helpers::sanitize_number( wp_unslash( $_POST['_mns_navasan_plus_currency_ratio'] ?? 1 ) );
        $db->update_post_meta( $post_id, 'currency_ratio', $ratio > 0 ? $ratio : 1 );

        // fixed (>=0)
        $fixed = Helpers::sanitize_number( wp_unslash( $_POST['_mns_navasan_plus_currency_fixed'] ?? 0 ) );
        $db->update_post_meta( $post_id, 'currency_fixed', max( 0, $fixed ) );

        // update type
        if ( isset( $_POST['_mns_navasan_plus_currency_update_type'] ) ) {
            $db->update_post_meta(
                $post_id,
                'currency_update_type',
                sanitize_text_field( wp_unslash( $_POST['_mns_navasan_plus_currency_update_type'] ) )
            );
        } else {
            $db->update_post_meta( $post_id, 'currency_update_type', 'none' );
        }

        // misc texts
        foreach ( [ 'relation', 'connection', 'operation', 'calculation_order' ] as $k ) {
            $post_key = '_mns_navasan_plus_currency_' . $k;
            if ( isset( $_POST[ $post_key ] ) ) {
                $db->update_post_meta( $post_id, 'currency_' . $k, sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ) );
            } else {
                $db->delete_post_meta( $post_id, 'currency_' . $k );
            }
        }

        // lowest rate
        $lowest = Helpers::sanitize_number( wp_unslash( $_POST['_mns_navasan_plus_currency_lowest_rate'] ?? 0 ) );
        $db->update_post_meta( $post_id, 'currency_lowest_rate', max( 0, $lowest ) );
    }

    public function formula_save( $post_id ) {
        // Validate: post type + caps + any accepted nonce (new or legacy)
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;
        if ( get_post_type( $post_id ) !== 'mnswmc-formula' ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $nonce_ok =
            ( isset( $_POST['_mns_navasan_plus_formula_nonce'] ) && wp_verify_nonce( $_POST['_mns_navasan_plus_formula_nonce'], 'mns_navasan_plus_formula' ) )
            ||
            ( isset( $_POST['_mnsnp_formula_nonce'] ) && wp_verify_nonce( $_POST['_mnsnp_formula_nonce'], 'mnsnp_save_formula' ) );

        if ( ! $nonce_ok ) return;

        $db = DB::instance();

        // ---- Expression: support both keys; save to 'formula_formul'
        $expr_in = $this->post_val( '_mns_navasan_plus_formula_expression', 'mns_navasan_plus_formula_expression', null );
        if ( $expr_in === null ) {
            $expr_in = $this->post_val( '_mns_navasan_plus_formula_formul', 'mns_navasan_plus_formula_formul', '' );
        }
        $expr = sanitize_textarea_field( trim( wp_unslash( (string) $expr_in ) ) );
        if ( $expr !== '' ) {
            $db->update_post_meta( $post_id, 'formula_formul', $expr );
        } else {
            $db->delete_post_meta( $post_id, 'formula_formul' );
        }

        // ---- Variables counter
        $vc_in = $this->post_val( '_mns_navasan_plus_formula_variables_counter', 'mns_navasan_plus_formula_variables_counter', 1 );
        $vc    = max( 1, (int) $vc_in );
        $db->update_post_meta( $post_id, 'formula_variables_counter', $vc );

        // ---- Variables (support both keys)
        $vars_in = $this->post_val( '_mns_navasan_plus_formula_variables', 'mns_navasan_plus_formula_variables', [] );
        $vars_in = is_array( $vars_in ) ? wp_unslash( $vars_in ) : [];
        $vars    = [];

        foreach ( $vars_in as $code => $vals ) {
            $safe_code = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $code );
            if ( $safe_code === '' ) continue;

            // type & currency_id (posted)
            $type = isset( $vals['type'] ) && in_array( $vals['type'], [ 'custom', 'currency' ], true )
                ? $vals['type'] : 'custom';
            $currency_id = isset( $vals['currency_id'] ) ? (int) $vals['currency_id'] : 0;
            if ( $type === 'currency' && $currency_id <= 0 ) {
                // اگر currency انتخاب نشده بود، به custom برگرد
                $type = 'custom';
            }

            $name         = sanitize_text_field( $vals['name'] ?? '' );
            $unit         = Helpers::sanitize_number( $vals['unit'] ?? 1 );
            $unit_symbol  = sanitize_text_field( $vals['unit_symbol']  ?? Helpers::get_currency_symbol() );
            $value        = Helpers::sanitize_number( $vals['value'] ?? ( $type === 'currency' ? 1 : 0 ) );
            $value_symbol = sanitize_text_field( $vals['value_symbol'] ?? '' );

            $vars[ $safe_code ] = [
                'type'         => $type,
                'currency_id'  => $currency_id,
                'name'         => $name,
                'unit'         => $unit,
                'unit_symbol'  => $unit_symbol,
                'value'        => $value,
                'value_symbol' => $value_symbol,
            ];
        }
        $db->update_post_meta( $post_id, 'formula_variables', $vars );

        // ---- Components counter
        $cc_in = $this->post_val( '_mns_navasan_plus_formula_components_counter', 'mns_navasan_plus_formula_components_counter', 1 );
        $cc    = max( 1, (int) $cc_in );
        $db->update_post_meta( $post_id, 'formula_components_counter', $cc );

        // ---- Components (support both keys)
        $comps_in = $this->post_val( '_mns_navasan_plus_formula_components', 'mns_navasan_plus_formula_components', [] );
        $comps_in = is_array( $comps_in ) ? wp_unslash( $comps_in ) : [];
        $comps    = [];

        foreach ( $comps_in as $i => $vals ) {
            $comps[ (int) $i ] = [
                // legacy keys we already use in templates/admin.js
                'name'   => sanitize_text_field( $vals['name']   ?? '' ),
                'text'   => sanitize_textarea_field( $vals['text'] ?? '' ),
                'symbol' => sanitize_text_field( $vals['symbol'] ?? '' ),
            ];
        }
        ksort( $comps );
        $db->update_post_meta( $post_id, 'formula_components', $comps );
    }

    // CHART save currently unused
    /*
    public function chart_save( $post_id ) {
        if ( ! $this->is_valid_save( $post_id, 'mnswmc-chart', '_mns_navasan_plus_chart_nonce', 'mns_navasan_plus_chart' ) ) {
            return;
        }

        $db = DB::instance();

        $items = $_POST['_mns_navasan_plus_chart_items'] ?? [];
        $db->update_post_meta( $post_id, 'chart_items', $this->deep_sanitize_array( $items ) );

        $ic = (int) ( $_POST['_mns_navasan_plus_chart_items_counter'] ?? 1 );
        $db->update_post_meta( $post_id, 'chart_items_counter', max( 1, $ic ) );

        $opts = $_POST['_mns_navasan_plus_chart_options'] ?? [];
        $db->update_post_meta( $post_id, 'chart_options', $this->deep_sanitize_array( $opts ) );
    }
    */

    // -------------------- Helpers --------------------

    private function is_valid_save( int $post_id, string $post_type, string $nonce_field, string $nonce_action ): bool {
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return false;
        if ( get_post_type( $post_id ) !== $post_type ) return false;
        if ( ! isset( $_POST[ $nonce_field ] ) || ! wp_verify_nonce( $_POST[ $nonce_field ], $nonce_action ) ) return false;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return false;
        return true;
    }

    /**
     * Read a POST key with optional alternative name (to support both with/without leading underscore).
     * @param string      $key    Primary key, e.g. '_mns_navasan_plus_formula_formul'
     * @param string|null $alt    Alternative key, e.g. 'mns_navasan_plus_formula_formul'
     * @param mixed       $default
     * @return mixed
     */
    private function post_val( $key, $alt = null, $default = null ) {
        if ( isset( $_POST[ $key ] ) ) {
            return $_POST[ $key ];
        }
        if ( $alt && isset( $_POST[ $alt ] ) ) {
            return $_POST[ $alt ];
        }
        return $default;
    }

    /**
     * Shallow recursive sanitizer for arbitrary arrays (strings/numbers/booleans).
     * Leaves arrays/objects structure but sanitizes scalars.
     */
    private function deep_sanitize_array( $value ) {
        if ( is_array( $value ) ) {
            $out = [];
            foreach ( $value as $k => $v ) {
                $out[ is_string( $k ) ? sanitize_key( $k ) : $k ] = $this->deep_sanitize_array( $v );
            }
            return $out;
        }
        if ( is_scalar( $value ) ) {
            // try numeric first
            if ( is_numeric( $value ) ) {
                return Helpers::sanitize_number( wp_unslash( $value ) );
            }
            return sanitize_text_field( wp_unslash( (string) $value ) );
        }
        return $value;
    }
}

add_action('save_post_mnswmc-formula', function($post_id, $post){
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! $post || $post->post_type !== 'mnswmc-formula' ) return;
    if ( ! current_user_can('manage_woocommerce') ) return;
    if ( empty($_POST['_mns_navasan_plus_formula_nonce']) 
      || ! wp_verify_nonce($_POST['_mns_navasan_plus_formula_nonce'], 'mns_navasan_plus_formula') ) return;

    $db = \MNS\NavasanPlus\DB::instance();

    $expr = isset($_POST['_mns_navasan_plus_formula_expression'])
        ? wp_kses_post( wp_unslash($_POST['_mns_navasan_plus_formula_expression']) )
        : '';
    $db->update_post_meta($post_id, 'formula_expression', $expr);

    $vars_in  = $_POST['_mns_navasan_plus_formula_variables'] ?? [];
    $vars_out = [];
    if ( is_array($vars_in) ) {
        foreach ($vars_in as $code => $v){
            $code = sanitize_key($code);
            if ($code === '') continue;
            $vars_out[$code] = [
                'name'         => sanitize_text_field($v['name'] ?? ''),
                'type'         => ( ($v['type'] ?? 'custom') === 'currency' ) ? 'currency' : 'custom',
                'currency_id'  => (int)($v['currency_id'] ?? 0),
                'unit'         => (float)($v['unit'] ?? 0),
                'unit_symbol'  => sanitize_text_field($v['unit_symbol'] ?? ''),
                'value'        => (float)($v['value'] ?? 0),
                'value_symbol' => sanitize_text_field($v['value_symbol'] ?? ''),
            ];
        }
    }
    $db->update_post_meta($post_id, 'formula_variables', $vars_out);

    $comps_in  = $_POST['_mns_navasan_plus_formula_components'] ?? [];
    $comps_out = [];
    if ( is_array($comps_in) ) {
        foreach ($comps_in as $i => $c){
            $name   = sanitize_text_field($c['name'] ?? ($c['label'] ?? ''));
            $text   = wp_kses_post( wp_unslash($c['text'] ?? ($c['expression'] ?? '')) );
            $symbol = sanitize_text_field($c['symbol'] ?? '');
            if ($name === '' && trim((string)$text) === '' && $symbol === '') continue;

            $comps_out[(int)$i] = [
                'label'      => $name,
                'expression' => $text,
                'symbol'     => $symbol,
            ];
        }
        ksort($comps_out);
    }
    $db->update_post_meta($post_id, 'formula_components', $comps_out);
}, 10, 2);