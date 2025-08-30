<?php
namespace MNS\NavasanPlus\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\DB;
use MNS\NavasanPlus\Templates\Classes\Snippets;

/**
 * Admin MetaBoxes (Currencies / Formulas / Product UI)
 */
final class MetaBoxes {

    /** جلوگیری از رجیستر دوباره‌ی هوک‌ها */
    private static bool $booted = false;

    /** جلوگیری از دوبار چاپ‌شدن UI ساده‌ی محصول در همان درخواست */
    private static bool $printed_simple_box = false;

    public function run(): void {
        // اگر قبلاً رجیستر شده، دوباره اجرا نکن
        if ( self::$booted ) {
            return;
        }
        self::$booted = true;

        // Register meta boxes
        add_action( 'add_meta_boxes', [ $this, 'add_boxes' ] );

        // Save handlers
        add_action( 'save_post_mnswmc',         [ $this, 'save_currency' ], 10, 2 );
        add_action( 'save_post_mnswmc-formula', [ $this, 'save_formula'  ], 10, 2 );

        // WooCommerce product UI (render only)
        // IMPORTANT: UI for product fields must NOT be hooked here. Use Admin\MetaBoxes only.
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'render_product_fields_simple' ], 25 );
        add_action( 'woocommerce_variation_options_pricing',            [ $this, 'render_product_fields_variation' ], 10, 3 );

        // Ensure parser/admin scripts on "Formula" edit screen
        add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_formula_assets' ] );
    }

    /**
     * Enqueue Formula Parser + admin JS/CSS on formula editor screen only
     */
    public function maybe_enqueue_formula_assets(): void {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || $screen->post_type !== 'mnswmc-formula' ) {
            return;
        }

        // این هندل‌ها در Loader ثبت شده‌اند:
        // - mns-navasan-plus-admin (JS/CSS)
        // - mns-navasan-plus-formula-parser (JS)
        if ( wp_script_is( 'mns-navasan-plus-formula-parser', 'registered' ) ) {
            wp_enqueue_script( 'mns-navasan-plus-formula-parser' );
        }
        if ( wp_script_is( 'mns-navasan-plus-admin', 'registered' ) ) {
            wp_enqueue_script( 'mns-navasan-plus-admin' );
        }
        if ( wp_style_is( 'mns-navasan-plus-admin', 'registered' ) ) {
            wp_enqueue_style( 'mns-navasan-plus-admin' );
        }
    }

    // ---------------------------------------------------------------------
    // Meta boxes
    // ---------------------------------------------------------------------

    public function add_boxes(): void {
        // Currency
        add_meta_box(
            'mnsnp_currency_box',
            __( 'Currency', 'mns-navasan-plus' ),
            [ $this, 'currency_output' ],
            'mnswmc',
            'normal',
            'high'
        );

        // Formula (variables + expression)
        add_meta_box(
            'mnsnp_formula_box',
            __( 'Formula', 'mns-navasan-plus' ),
            [ $this, 'formula_output' ],
            'mnswmc-formula',
            'normal',
            'high'
        );

        // Formula components (breakdown rows)
        add_meta_box(
            'mnsnp_formula_components_box',
            __( 'Formula Components', 'mns-navasan-plus' ),
            [ $this, 'formula_components_output' ],
            'mnswmc-formula',
            'normal',
            'default'
        );

        // نمودار (غیرفعال)
        // add_meta_box(
        //     'mnsnp_currency_chart_box',
        //     __( 'Currency Chart', 'mns-navasan-plus' ),
        //     [ $this, 'currency_chart_output' ],
        //     'mnswmc',
        //     'side',
        //     'low'
        // );
    }

    // Currency box
    public function currency_output( \WP_Post $post ): void {
        Snippets::load_template( 'metaboxes/currency', [
            'post_id' => $post->ID,
        ] );
    }

    // Formula box (expression + variables)
    public function formula_output( \WP_Post $post ): void {
        $db = DB::instance();

        $vars   = (array) get_post_meta( $post->ID, $db->full_meta_key( 'formula_variables' ), true );
        $expr   = (string) get_post_meta( $post->ID, $db->full_meta_key( 'formula_expression' ), true );
        $legacy = (string) get_post_meta( $post->ID, $db->full_meta_key( 'formul' ), true ); // سازگاری قدیمی

        $formula = [
            'variables'          => $vars,
            'variables_counter'  => (int) get_post_meta( $post->ID, $db->full_meta_key( 'formula_variables_counter' ), true ),
            'expression'         => $expr,
            'formul'             => $legacy,
        ];

        // لیست ارزها برای دکمه "افزودن متغیر ارزی"
        $currency_posts = get_posts( [
            'post_type'        => 'mnswmc',
            'posts_per_page'   => -1,
            'post_status'      => 'publish',
            'orderby'          => 'title',
            'order'            => 'ASC',
            'fields'           => 'ids',
            'suppress_filters' => true,
        ] );

        $currencies = [];
        foreach ( $currency_posts as $cid ) {
            $cid    = (int) $cid;
            $label  = get_the_title( $cid );
            $rate   = get_post_meta( $cid, $db->full_meta_key( 'currency_value' ), true );
            $symbol = get_post_meta( $cid, $db->full_meta_key( 'currency_rate_symbol' ), true );

            $currencies[] = [
                'id'     => $cid,
                'label'  => (string) $label,
                'rate'   => ( $rate === '' ? 0 : (float) $rate ),
                'symbol' => (string) $symbol,
            ];
        }

        Snippets::load_template( 'metaboxes/formula', [
            'formula'    => $formula,
            'currencies' => $currencies, // تمپلیت اگر نبود، خودش fallback دارد
        ] );
    }

    // Formula components box
    public function formula_components_output( \WP_Post $post ): void {
        $db = DB::instance();
        $components = (array) get_post_meta( $post->ID, $db->full_meta_key( 'formula_components' ), true );

        Snippets::load_template( 'metaboxes/formula-components', [
            'components' => $components,
        ] );
    }

    // ---------------------------------------------------------------------
    // Save handlers
    // ---------------------------------------------------------------------

    public function save_currency( int $post_id, \WP_Post $post ): void {
        if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! $post || $post->post_type !== 'mnswmc' ) return;
        if ( ! current_user_can( 'manage_woocommerce' ) ) return;

        $db = DB::instance();

        // نرخ فعلی
        if ( isset( $_POST['mnswmc_currency_value'] ) ) {
            $rate = wc_format_decimal( wp_unslash( $_POST['mnswmc_currency_value'] ), 6 );
            $db->update_post_meta( $post_id, 'currency_value', $rate );
        }

        // نماد نرخ (rate symbol)
        if ( isset( $_POST['mnswmc_currency_rate_symbol'] ) ) {
            $sym = sanitize_text_field( wp_unslash( $_POST['mnswmc_currency_rate_symbol'] ) );
            $db->update_post_meta( $post_id, 'currency_rate_symbol', $sym );

            // برای سازگاری با تمپلیت‌های خیلی قدیمی:
            // $db->update_post_meta( $post_id, 'currency_symbol', $sym );
        }
    }

    public function save_formula( int $post_id, \WP_Post $post ): void {
        if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! $post || $post->post_type !== 'mnswmc-formula' ) return;
        if ( ! current_user_can( 'manage_woocommerce' ) ) return;

        // Nonce
        if ( empty( $_POST['_mns_navasan_plus_formula_nonce'] )
          || ! wp_verify_nonce( $_POST['_mns_navasan_plus_formula_nonce'], 'mns_navasan_plus_formula' ) ) {
            return;
        }

        $db = DB::instance();

        // Expression
        $expr = isset( $_POST['_mns_navasan_plus_formula_expression'] )
            ? wp_kses_post( wp_unslash( $_POST['_mns_navasan_plus_formula_expression'] ) )
            : '';
        $db->update_post_meta( $post_id, 'formula_expression', $expr );

        // Variables (با role)
        $vars_in  = $_POST['_mns_navasan_plus_formula_variables'] ?? [];
        $vars_out = [];

        if ( is_array( $vars_in ) ) {
            foreach ( $vars_in as $code => $v ) {
                $code = sanitize_key( $code );
                if ( $code === '' ) continue;

                $role = isset( $v['role'] ) ? (string) $v['role'] : 'none';
                if ( ! in_array( $role, [ 'none', 'profit', 'charge' ], true ) ) {
                    $role = 'none';
                }

                $vars_out[ $code ] = [
                    'name'         => sanitize_text_field( $v['name'] ?? '' ),
                    'type'         => ( ( $v['type'] ?? 'custom' ) === 'currency' ) ? 'currency' : 'custom',
                    'currency_id'  => (int) ( $v['currency_id'] ?? 0 ),
                    'unit'         => ( $v['unit'] !== '' && $v['unit'] !== null ) ? (float) $v['unit'] : '',
                    'unit_symbol'  => sanitize_text_field( $v['unit_symbol'] ?? '' ),
                    'value'        => ( $v['value'] !== '' && $v['value'] !== null ) ? (float) $v['value'] : '',
                    'value_symbol' => sanitize_text_field( $v['value_symbol'] ?? '' ),
                    'role'         => $role,
                ];
            }
        }
        $db->update_post_meta( $post_id, 'formula_variables', $vars_out );

        // Components
        // --- Components ---
        // Components
        $comps_in  = $_POST['_mns_navasan_plus_formula_components'] ?? [];
        $comps_out = [];

        if ( is_array( $comps_in ) ) {
            foreach ( $comps_in as $i => $c ) {
                $name   = sanitize_text_field( $c['name'] ?? ( $c['label'] ?? '' ) );
                $text   = wp_kses_post( wp_unslash( $c['text'] ?? ( $c['expression'] ?? '' ) ) );
                $symbol = sanitize_text_field( $c['symbol'] ?? '' );

                // اگر کل ردیف خالی بود، رد شو
                if ( $name === '' && trim( (string) $text ) === '' && $symbol === '' ) {
                    continue;
                }

                // اگر اندیس عددی بود همون رو نگه دار؛ اگر نبود، یک اندیس ترتیبی یکتا بساز
                $idx = is_numeric( $i ) ? (int) $i : count( $comps_out );
                $comps_out[ $idx ] = [
                    'label'      => $name,
                    'expression' => $text,
                    'symbol'     => $symbol,
                ];
            }

            // مرتب‌سازی بر اساس اندیس تا ترتیب پایدار بماند
            ksort( $comps_out, SORT_NUMERIC );
        }

        DB::instance()->update_post_meta( $post_id, 'formula_components', $comps_out );

        // شمارنده (اختیاری – اگر فرستاده می‌شود)
        if ( isset( $_POST['_mns_navasan_plus_formula_variables_counter'] ) ) {
            $db->update_post_meta(
                $post_id,
                'formula_variables_counter',
                absint( wp_unslash( $_POST['_mns_navasan_plus_formula_variables_counter'] ) )
            );
        }
    }

    // ---------------------------------------------------------------------
    // WooCommerce product fields renderers (UI فقط)
    // ---------------------------------------------------------------------

    public function render_product_fields_simple(): void {
        // اگر قبلاً چاپ شده، دوباره چاپ نکن
        if ( self::$printed_simple_box ) {
            return;
        }
        self::$printed_simple_box = true;

        $product = function_exists( 'wc_get_product' ) && get_the_ID() ? wc_get_product( get_the_ID() ) : null;
        if ( ! $product ) return;

        echo '<div class="options_group mns-navasan-plus_simple_product_fields">';
        Snippets::load_template( 'metaboxes/product', [
            'product' => $product,
        ] );
        echo '</div>';
    }

    /**
     * @param int                      $loop
     * @param array                    $variation_data
     * @param \WC_Product_Variation    $variation
     */
    public function render_product_fields_variation( $loop, $variation_data, $variation ): void {
        if ( ! $variation instanceof \WC_Product_Variation ) return;

        echo '<div class="mns-navasan-plus_variation_product_fields">';
        Snippets::load_template( 'metaboxes/product', [
            'product'        => $variation,
            'loop'           => (int) $loop,
            'variation_data' => $variation_data,
        ] );
        echo '</div>';
    }
}