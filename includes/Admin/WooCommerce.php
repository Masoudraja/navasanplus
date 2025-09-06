<?php
namespace MNS\NavasanPlus\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use MNS\NavasanPlus\Services\PriceCalculator;
use MNS\NavasanPlus\DB;
use MNS\NavasanPlus\Templates\Classes\Snippets;

/**
 * WooCommerce integration for MNS Navasan Plus
 *
 * - Override WC prices with calculated prices (product/variation + matrix cache)
 * - Show & save product/variation fields
 * - Add WC_Order macro: get_currency_rate( $currency_id )
 */
final class WooCommerce {

    /** جلوگیری از حلقه‌های بازگشتی هنگام محاسبه قیمت */
    private static array $calc_guard = [];

    /** فیلدهای تخفیف (suffix بدون پیشوند) */
    private const DISCOUNT_FIELDS = [
        'discount_profit_percentage',
        'discount_profit_fixed',
        'discount_charge_percentage',
        'discount_charge_fixed',
    ];

    public function run(): void {
        // ---- UI: نمایش فیلدها در تب General و باکس قیمت وارییشن
        // add_action( 'woocommerce_product_options_general_product_data', [ $this, 'render_product_fields_simple' ], 25 );
        // add_action( 'woocommerce_variation_options_pricing',            [ $this, 'render_product_fields_variation' ], 10, 3 );

        // ---- ذخیره‌ی فیلدهای محصول و ورییشن‌ها
        add_action( 'woocommerce_admin_process_product_object',        [ $this, 'save_product_object' ] );
        add_action( 'woocommerce_save_product_variation',              [ $this, 'save_product_variation' ], 10, 2 );

        // ---- قیمت محصولات
        add_filter( 'woocommerce_product_get_price',                   [ $this, 'filter_product_price' ], 999, 2 );
        add_filter( 'woocommerce_product_get_regular_price',           [ $this, 'filter_product_price' ], 999, 2 );
        add_filter( 'woocommerce_product_get_sale_price',              [ $this, 'filter_product_price' ], 999, 2 );

        // ---- قیمت ورییشن‌ها
        add_filter( 'woocommerce_product_variation_get_price',         [ $this, 'filter_product_price' ], 999, 2 );
        add_filter( 'woocommerce_product_variation_get_regular_price', [ $this, 'filter_product_price' ], 999, 2 );
        add_filter( 'woocommerce_product_variation_get_sale_price',    [ $this, 'filter_product_price' ], 999, 2 );

        // ---- کش ماتریس قیمت ورییشن‌ها
        add_filter( 'woocommerce_variation_prices_price',              [ $this, 'filter_variation_prices_matrix' ], 999, 3 );
        add_filter( 'woocommerce_variation_prices_regular_price',      [ $this, 'filter_variation_prices_matrix' ], 999, 3 );
        add_filter( 'woocommerce_variation_prices_sale_price',         [ $this, 'filter_variation_prices_matrix' ], 999, 3 );

        // ---- ماکروی سفارش
        add_action( 'init',                                            [ $this, 'add_order_macros' ] );
    }

    // ---------------------------------------------------------------------
    // UI: Render fields
    // ---------------------------------------------------------------------

    /** فیلدهای محصول ساده در تب General */
    public function render_product_fields_simple(): void {
        global $post, $product_object;

        $product = ( $product_object instanceof \WC_Product )
            ? $product_object
            : ( $post ? wc_get_product( $post->ID ) : null );

        if ( ! $product instanceof \WC_Product ) {
            return;
        }

        // اطمینان از لود استایل/اسکریپت ادمین خودمان
        wp_enqueue_style( 'mns-navasan-plus-admin' );
        wp_enqueue_script( 'mns-navasan-plus-admin' );

        // تمپلیت فیلدها
        Snippets::load_template( 'metaboxes/product', [
            'product' => $product,
        ] );
    }

    /**
     * فیلدهای ورییشن (داخل Pricing هر ورییشن)
     *
     * @param int        $loop
     * @param array      $variation_data
     * @param \WC_Product_Variation $variation
     */
    public function render_product_fields_variation( int $loop, array $variation_data, $variation ): void {
        if ( ! $variation instanceof \WC_Product_Variation ) {
            return;
        }

        wp_enqueue_style( 'mns-navasan-plus-admin' );
        wp_enqueue_script( 'mns-navasan-plus-admin' );

        Snippets::load_template( 'metaboxes/product', [
            'product' => $variation,
            'loop'    => $loop,
        ] );
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /** خواندن مقدار بولی ذخیره‌شده به صورت yes/no (با fallback به والد برای ورییشن) */
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
        return function_exists( 'wc_string_to_bool' )
            ? wc_string_to_bool( (string) $raw )
            : ( $raw === 'yes' || $raw === '1' || $raw === 1 || $raw === true );
    }

    /** هِلپر: خواندن مقدار ورییشن از POST هم با کلید پایه و هم با پیشوند _variable */
    private function vpost( string $base_key, int $i ) {
        if ( isset( $_POST[ $base_key ][ $i ] ) ) {
            return wp_unslash( $_POST[ $base_key ][ $i ] );
        }
        $with_prefix = '_variable' . $base_key;
        if ( isset( $_POST[ $with_prefix ][ $i ] ) ) {
            return wp_unslash( $_POST[ $with_prefix ][ $i ] );
        }
        return null;
    }

    // ---------------------------------------------------------------------
    // قیمت
    // ---------------------------------------------------------------------

    public function filter_product_price( $price, $product ) {
        if ( ! $product instanceof \WC_Product ) {
            return $price;
        }

        $pid = (int) $product->get_id();

        // فقط اگر قابلیت قیمت‌گذاری داینامیک برای این محصول فعال باشد
        if ( ! $this->product_meta_bool( $product, 'active', true ) ) {
            return $price;
        }

        // جلوگیری از لوپ
        if ( isset( self::$calc_guard[ $pid ] ) ) {
            return $price;
        }
        self::$calc_guard[ $pid ] = true;

        try {
            $calc = $this->calculate_final_price( $product );
            if ( $calc !== null ) {
                $price = max( 0, (float) $calc );
            }
        } catch ( \Throwable $e ) {
            // error_log('[MNSNP] price calc error: ' . $e->getMessage());
        } finally {
            unset( self::$calc_guard[ $pid ] );
        }

        return $price;
    }

    public function filter_variation_prices_matrix( $price, $variation, $parent ) {
        if ( ! $variation instanceof \WC_Product_Variation ) {
            return $price;
        }
        return $this->filter_product_price( $price, $variation );
    }

    protected function calculate_final_price( \WC_Product $product ): ?float {
        if ( class_exists( PriceCalculator::class ) && method_exists( PriceCalculator::class, 'instance' ) ) {
            $result = PriceCalculator::instance()->calculate( (int) $product->get_id() );

            if ( is_numeric( $result ) ) {
                return (float) $result;
            }
            if ( is_array( $result ) ) {
                if ( isset( $result['price'] ) ) {
                    return (float) $result['price'];
                }
                if ( isset( $result['profit'], $result['charge'] ) ) {
                    return (float) $result['profit'] + (float) $result['charge'];
                }
            }
        }
        return null;
    }

    /** مقداردهی «قیمت عادی» ووکامرس از خروجی PriceCalculator (Simple) */
    private function fill_wc_regular_price_from_calc( \WC_Product $product ): void {
        try {
            if ( ! $this->product_meta_bool( $product, 'active', true ) ) {
                return;
            }
            if ( class_exists( PriceCalculator::class ) ) {
                // Calculator امروزی می‌تواند خودِ آبجکت را هم بگیرد
                $calc = PriceCalculator::instance()->calculate( $product );
                if ( is_numeric( $calc ) && $calc > 0 ) {
                    $val = wc_format_decimal( $calc, 0 ); // ⬅️ ذخیره بدون اعشار
                    $product->set_regular_price( $val );
                    $product->set_price( $val );
                }
            }
        } catch ( \Throwable $e ) {}
    }

    // ---------------------------------------------------------------------
    // ذخیره‌ی فیلدهای محصول (ساده/والدِ ورییشن)
    // ---------------------------------------------------------------------

    public function save_product_object( \WC_Product $product ): void {
        if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
            return;
        }

        $db = DB::instance();

        // --- تخفیف‌ها ---
        foreach ( self::DISCOUNT_FIELDS as $suffix ) {
            $post_key = 'mns_' . $suffix; // مثال: mns_discount_profit_percentage
            $meta_key = $db->full_meta_key( $suffix );
            if ( isset( $_POST[ $post_key ] ) ) {
                $raw = wp_unslash( $_POST[ $post_key ] );
                $val = ( $suffix === 'discount_profit_percentage' || $suffix === 'discount_charge_percentage' )
                    ? wc_format_decimal( $raw, 4 )
                    : wc_format_decimal( $raw, 2 );
                $product->update_meta_data( $meta_key, $val );
            }
        }

        // --- چک‌باکس‌ها: active, price_alert (yes/no) ---
        foreach ( [ 'active', 'price_alert' ] as $key ) {
            $post_key = "_mns_navasan_plus_{$key}";
            $val      = isset( $_POST[ $post_key ] ) ? 'yes' : 'no';
            $product->update_meta_data( $db->full_meta_key( $key ), $val );
        }

        // --- فیلدهای متنی/انتخابی ---
        foreach ( [ 'dependence_type', 'rounding_type', 'rounding_side', 'profit_type' ] as $key ) {
            $post_key = "_mns_navasan_plus_{$key}";
            if ( isset( $_POST[ $post_key ] ) ) {
                $product->update_meta_data(
                    $db->full_meta_key( $key ),
                    sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) )
                );
            }
        }

        // --- عدد صحیح: currency_id ---
        if ( isset( $_POST['_mns_navasan_plus_currency_id'] ) ) {
            $product->update_meta_data(
                $db->full_meta_key( 'currency_id' ),
                absint( wp_unslash( $_POST['_mns_navasan_plus_currency_id'] ) )
            );
        }

        // --- اعشاری‌ها ---
        $decimals = [
            'profit_value'   => 3,
            'rounding_value' => 3,
            'ceil_price'     => 3,
            'floor_price'    => 3,
        ];
        foreach ( $decimals as $key => $precision ) {
            $post_key = "_mns_navasan_plus_{$key}";
            if ( isset( $_POST[ $post_key ] ) ) {
                $product->update_meta_data(
                    $db->full_meta_key( $key ),
                    wc_format_decimal( wp_unslash( $_POST[ $post_key ] ), $precision )
                );
            }
        }

        // --- قیمت‌های دستی (اختیاریِ ادمین) ---
        if ( isset( $_POST['_mns_navasan_plus_regular_price'] ) ) {
            $product->set_regular_price( wc_format_decimal( wp_unslash( $_POST['_mns_navasan_plus_regular_price'] ), 6 ) );
        }
        if ( isset( $_POST['_mns_navasan_plus_sale_price'] ) ) {
            $product->set_sale_price( wc_format_decimal( wp_unslash( $_POST['_mns_navasan_plus_sale_price'] ), 6 ) );
        }

        // --- فرمول انتخابی ---
        if ( isset( $_POST['_mns_navasan_plus_formula_id'] ) ) {
            $product->update_meta_data(
                $db->full_meta_key( 'formula_id' ),
                absint( wp_unslash( $_POST['_mns_navasan_plus_formula_id'] ) )
            );
        }

        // --- مقادیر متغیرهای فرمول (آرایه‌ای) ---
        if ( isset( $_POST['_mns_navasan_plus_formula_variables'] ) && is_array( $_POST['_mns_navasan_plus_formula_variables'] ) ) {
            $vars = [];
            foreach ( $_POST['_mns_navasan_plus_formula_variables'] as $fid => $codes ) {
                $fid = (int) $fid;
                foreach ( (array) $codes as $code => $vals ) {
                    $code = sanitize_key( $code );
                    $vars[ $fid ][ $code ] = [
                        'regular' => isset( $vals['regular'] ) ? wc_format_decimal( wp_unslash( $vals['regular'] ), 6 ) : '',
                        'sale'    => isset( $vals['sale'] )    ? wc_format_decimal( wp_unslash( $vals['sale'] ), 6 )    : '',
                    ];
                }
            }
            $product->update_meta_data( $db->full_meta_key( 'formula_variables' ), $vars );
        }

        // --- پر کردن قیمت عادی از محاسبه (با محاسبه روی همین آبجکت) ---
        $this->fill_wc_regular_price_from_calc( $product );

        // ذخیرهٔ نهایی
        $product->save();
    }

    // ---------------------------------------------------------------------
    // ذخیره‌ی فیلدهای ورییشن
    // ---------------------------------------------------------------------

    public function save_product_variation( int $variation_id, int $i ): void {
        if ( ! current_user_can( 'edit_product', $variation_id ) ) {
            return;
        }

        $db = DB::instance();

        // --- تخفیف‌ها ---
        foreach ( self::DISCOUNT_FIELDS as $suffix ) {
            $post_key = 'mns_' . $suffix;
            $meta_key = $db->full_meta_key( $suffix );

            $val_raw = null;
            if ( isset( $_POST[ $post_key ][ $i ] ) ) {
                $val_raw = wp_unslash( $_POST[ $post_key ][ $i ] );
            } elseif ( isset( $_POST[ '_variable' . $post_key ][ $i ] ) ) {
                $val_raw = wp_unslash( $_POST[ '_variable' . $post_key ][ $i ] );
            }
            if ( $val_raw !== null ) {
                $val = ( $suffix === 'discount_profit_percentage' || $suffix === 'discount_charge_percentage' )
                    ? wc_format_decimal( $val_raw, 4 )
                    : wc_format_decimal( $val_raw, 2 );
                update_post_meta( $variation_id, $meta_key, $val );
            }
        }

        // --- چک‌باکس‌ها: active, price_alert (yes/no) ---
        foreach ( [ 'active', 'price_alert' ] as $key ) {
            $base   = "_mns_navasan_plus_{$key}";
            $exists = isset( $_POST[ $base ][ $i ] ) || isset( $_POST[ '_variable' . $base ][ $i ] );
            update_post_meta( $variation_id, $db->full_meta_key( $key ), $exists ? 'yes' : 'no' );
        }

        // --- متنی/انتخابی ---
        foreach ( [ 'dependence_type', 'rounding_type', 'rounding_side', 'profit_type' ] as $key ) {
            $base = "_mns_navasan_plus_{$key}";
            $raw  = $this->vpost( $base, $i );
            if ( $raw !== null ) {
                update_post_meta(
                    $variation_id,
                    $db->full_meta_key( $key ),
                    sanitize_text_field( $raw )
                );
            }
        }

        // --- عدد صحیح: currency_id ---
        $raw = $this->vpost( '_mns_navasan_plus_currency_id', $i );
        if ( $raw !== null ) {
            update_post_meta(
                $variation_id,
                $db->full_meta_key( 'currency_id' ),
                absint( $raw )
            );
        }

        // --- اعشاری‌ها ---
        $decimals = [
            'profit_value'   => 6,
            'rounding_value' => 6,
            'ceil_price'     => 6,
            'floor_price'    => 6,
        ];
        foreach ( $decimals as $key => $precision ) {
            $base = "_mns_navasan_plus_{$key}";
            $raw  = $this->vpost( $base, $i );
            if ( $raw !== null ) {
                update_post_meta(
                    $variation_id,
                    $db->full_meta_key( $key ),
                    wc_format_decimal( $raw, $precision )
                );
            }
        }

        // --- قیمت‌های ورییشن (اختیاری) ---
        $raw = $this->vpost( '_mns_navasan_plus_regular_price', $i );
        if ( $raw !== null ) {
            update_post_meta( $variation_id, '_regular_price', wc_format_decimal( $raw, 6 ) );
        }
        $raw = $this->vpost( '_mns_navasan_plus_sale_price', $i );
        if ( $raw !== null ) {
            update_post_meta( $variation_id, '_sale_price', wc_format_decimal( $raw, 6 ) );
        }

        // --- فرمول انتخابی ---
        $raw = $this->vpost( '_mns_navasan_plus_formula_id', $i );
        if ( $raw !== null ) {
            update_post_meta(
                $variation_id,
                $db->full_meta_key( 'formula_id' ),
                absint( $raw )
            );
        }

        // --- مقادیر متغیرهای فرمول ---
        $vars_payload = null;
        if ( isset( $_POST['_mns_navasan_plus_formula_variables'] ) && is_array( $_POST['_mns_navasan_plus_formula_variables'] ) ) {
            $vars_payload = $_POST['_mns_navasan_plus_formula_variables'];
        } elseif ( isset( $_POST['_variable_mns_navasan_plus_formula_variables'] ) && is_array( $_POST['_variable_mns_navasan_plus_formula_variables'] ) ) {
            $vars_payload = $_POST['_variable_mns_navasan_plus_formula_variables'];
        }

        if ( is_array( $vars_payload ) ) {
            $vars = [];
            foreach ( $vars_payload as $fid => $codes ) {
                $fid = (int) $fid;
                foreach ( (array) $codes as $code => $vals ) {
                    $code = sanitize_key( $code );

                    $reg = is_array( $vals['regular'] ?? null ) ? ( $vals['regular'][ $i ] ?? '' ) : ( $vals['regular'] ?? '' );
                    $sal = is_array( $vals['sale'] ?? null )    ? ( $vals['sale'][ $i ]    ?? '' ) : ( $vals['sale']    ?? '' );

                    $vars[ $fid ][ $code ] = [
                        'regular' => wc_format_decimal( wp_unslash( $reg ), 6 ),
                        'sale'    => wc_format_decimal( wp_unslash( $sal ), 6 ),
                    ];
                }
            }
            update_post_meta( $variation_id, $db->full_meta_key( 'formula_variables' ), $vars );
        }

        // --- پر کردن قیمت عادی ورییشن از محاسبه (با محاسبه روی همین آبجکت) ---
        // --- پر کردن قیمت عادی ورییشن از محاسبه ---
        try {
            $v = wc_get_product( $variation_id );
            if ( $v instanceof \WC_Product_Variation && $this->product_meta_bool( $v, 'active', true ) ) {
                if ( class_exists( PriceCalculator::class ) ) {
                    $calc = PriceCalculator::instance()->calculate( $v );
                    if ( is_numeric( $calc ) && $calc > 0 ) {
                        $val = wc_format_decimal( $calc, 0 ); // ⬅️ بدون اعشار
                        update_post_meta( $variation_id, '_regular_price', $val );
                        update_post_meta( $variation_id, '_price',         $val );
                    }
                }
            }
        } catch ( \Throwable $e ) {}
    }

    // ---------------------------------------------------------------------
    // ماکروهای سفارش
    // ---------------------------------------------------------------------

    /** WC_Order::get_currency_rate( $currency_id ) */
    public function add_order_macros(): void {
        if ( method_exists( 'WC_Order', 'macro' ) && ! method_exists( 'WC_Order', 'get_currency_rate' ) ) {
            \WC_Order::macro( 'get_currency_rate', function( $currency_id ) {
                /** @var \WC_Order $this */
                $currency_id = (int) $currency_id;
                $key = \MNS\NavasanPlus\DB::instance()->full_meta_key( 'currency_' . $currency_id . '_rate' );

                foreach ( $this->get_items( 'fee' ) as $item ) {
                    $rate = $item->get_meta( $key, true );
                    if ( $rate !== '' ) {
                        return (float) $rate;
                    }
                }
                foreach ( $this->get_items() as $item ) {
                    $rate = $item->get_meta( $key, true );
                    if ( $rate !== '' ) {
                        return (float) $rate;
                    }
                }
                return 0.0;
            } );
        }
    }
}