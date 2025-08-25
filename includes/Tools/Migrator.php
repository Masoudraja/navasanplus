<?php
namespace MNS\NavasanPlus\Tools;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * مهاجرت از «نوسان» (mnswmc) به «نوسان پلاس»
 */
final class Migrator {

    /** مسیر افزونهٔ قدیمی برای غیرفعالسازی (در صورت نیاز با فیلتر override کن) */
    public const OLD_PLUGIN_BASENAME = 'mns-woocommerce-rate-based-products/mns-woocommerce-rate-based-products.php';

    /**
     * اجرای مهاجرت
     * @param array $args {
     *   @type bool $dry                    فقط گزارش (بدون نوشتن) — پیش‌فرض: true
     *   @type bool $deactivate_old         بعد از موفقیت، افزونهٔ قدیمی غیرفعال شود — پیش‌فرض: false
     *   @type bool $delete_old_opts        بعد از کپی، options قدیمی حذف شوند — پیش‌فرض: false
     *   @type bool $aggregate_formula_vars تجمیع متغیرهای فرمول پراکنده به متای آرایه‌ای جدید — پیش‌فرض: true
     * }
     * @return array گزارش
     */
    public static function run( array $args = [] ): array {
        global $wpdb;

        $args = wp_parse_args( $args, [
            'dry'                    => true,
            'deactivate_old'         => false,
            'delete_old_opts'        => false,
            'aggregate_formula_vars' => true,
        ] );

        $report = [
            'currencies_scanned'   => 0,
            'currencies_moved'     => 0,
            'posts_scanned'        => 0, // products + variations
            'posts_moved'          => 0,
            'products_scanned'     => 0, // فقط products
            'products_moved'       => 0,
            'variations_scanned'   => 0,
            'variations_moved'     => 0,
            'options_scanned'      => 0,
            'options_moved'        => 0,
            'products_aggregated'  => 0, // چند آیتم تجمیع شد
            'errors'               => [],
        ];

        // -------------------------
        // 1) Currencies (CPT: mnswmc)
        // -------------------------
        $currencies = get_posts([
            'post_type'        => 'mnswmc',
            'posts_per_page'   => -1,
            'fields'           => 'ids',
            'post_status'      => 'any',
            'suppress_filters' => true,
        ]);

        $cmap = [
            '_mnswmc_currency_value'       => '_mns_navasan_plus_currency_value',
            '_mnswmc_currency_history'     => '_mns_navasan_plus_currency_history',
            '_mnswmc_currency_symbol'      => '_mns_navasan_plus_currency_symbol',
            '_mnswmc_currency_code'        => '_mns_navasan_plus_currency_code',
            '_mnswmc_currency_update_time' => '_mns_navasan_plus_currency_update_time',
        ];

        foreach ( $currencies as $pid ) {
            $report['currencies_scanned']++;
            foreach ( $cmap as $old => $new ) {
                $v = get_post_meta( $pid, $old, true );
                if ( $v === '' && $old !== '_mnswmc_currency_history' ) continue;
                if ( ! $args['dry'] ) update_post_meta( $pid, $new, $v );
                $report['currencies_moved']++;
            }
        }

        // -------------------------
        // 2) Products + Variations
        // -------------------------
        $post_types = apply_filters( 'mnsnp/migrator/post_types', [ 'product', 'product_variation' ] );

        $posts = get_posts([
            'post_type'        => $post_types,
            'posts_per_page'   => -1,
            'fields'           => 'ids',
            'post_status'      => 'any',
            'suppress_filters' => true,
        ]);

        // نگاشت کلیدهای اصلی
        $pmap = [
            '_mnswmc_active'          => '_mns_navasan_plus_active',
            '_mnswmc_dependence_type' => '_mns_navasan_plus_dependence_type',
            '_mnswmc_currency_id'     => '_mns_navasan_plus_currency_id',
            '_mnswmc_profit_type'     => '_mns_navasan_plus_profit_type',
            '_mnswmc_profit_value'    => '_mns_navasan_plus_profit_value',
            '_mnswmc_rounding_type'   => '_mns_navasan_plus_rounding_type',
            '_mnswmc_rounding_value'  => '_mns_navasan_plus_rounding_value',
            '_mnswmc_rounding_side'   => '_mns_navasan_plus_rounding_side',
            '_mnswmc_ceil_price'      => '_mns_navasan_plus_ceil_price',
            '_mnswmc_floor_price'     => '_mns_navasan_plus_floor_price',
            '_mnswmc_formula_id'      => '_mns_navasan_plus_formula_id',
        ];
        $pmap = apply_filters( 'mnsnp/migrator/product_meta_map', $pmap );

        foreach ( $posts as $pid ) {
            $report['posts_scanned']++;

            $post_type = get_post_type( $pid );
            if ( $post_type === 'product' ) {
                $report['products_scanned']++;
            } elseif ( $post_type === 'product_variation' ) {
                $report['variations_scanned']++;
            }

            // 2-الف) نگاشت کلیدهای مشخص
            foreach ( $pmap as $old => $new ) {
                $v = get_post_meta( $pid, $old, true );
                if ( $v === '' ) continue;
                if ( ! $args['dry'] ) update_post_meta( $pid, $new, $v );
                $report['posts_moved']++;
                if ( $post_type === 'product' ) $report['products_moved']++;
                else $report['variations_moved']++;
            }

            // 2-ب) نگاشت جنریک: _mnswmc_formula_* → _mns_navasan_plus_formula_*
            $all = get_post_meta( $pid );
            foreach ( $all as $key => $vals ) {
                if ( strpos( $key, '_mnswmc_formula_' ) === 0 ) {
                    $new_key = '_mns_navasan_plus_' . substr( $key, strlen('_mnswmc_') );
                    $v = get_post_meta( $pid, $key, true );
                    if ( ! $args['dry'] ) update_post_meta( $pid, $new_key, $v );
                    $report['posts_moved']++;
                    if ( $post_type === 'product' ) $report['products_moved']++;
                    else $report['variations_moved']++;
                }
            }

            // 2-پ) تجمیع متغیرهای فرمول به متای آرایه‌ای جدید (UI/محاسبه بهتر)
            if ( $args['aggregate_formula_vars'] ) {
                $agg = [];

                // هم الگوی قدیمی و هم جدید را بخوانیم تا در dry-run هم کار کند
                foreach ( $all as $key => $vals ) {
                    // جدید: _mns_navasan_plus_formula_{fid}_{code}_{regular|sale}
                    if ( preg_match( '/^_mns_navasan_plus_formula_(\d+)_([A-Za-z0-9_]+)_(regular|sale)$/', $key, $m ) ) {
                        $fid  = (int) $m[1];
                        $code = (string) $m[2];
                        $kind = (string) $m[3];
                        $val  = get_post_meta( $pid, $key, true );
                        $agg[ $fid ][ $code ][ $kind ] = is_numeric( $val ) ? (float) $val : $val;
                        continue;
                    }
                    // قدیمی: _mnswmc_formula_{fid}_{code}_{regular|sale}
                    if ( preg_match( '/^_mnswmc_formula_(\d+)_([A-Za-z0-9_]+)_(regular|sale)$/', $key, $m ) ) {
                        $fid  = (int) $m[1];
                        $code = (string) $m[2];
                        $kind = (string) $m[3];
                        $val  = get_post_meta( $pid, $key, true );
                        // اگر قبلاً از جدید نداشتیم، از قدیمی پر کن
                        if ( ! isset( $agg[ $fid ][ $code ][ $kind ] ) ) {
                            $agg[ $fid ][ $code ][ $kind ] = is_numeric( $val ) ? (float) $val : $val;
                        }
                    }
                }

                if ( ! empty( $agg ) ) {
                    if ( ! $args['dry'] ) {
                        update_post_meta( $pid, '_mns_navasan_plus_formula_variables', $agg );
                    }
                    $report['products_aggregated'] += count( $agg );
                }
            }
        }

        // -------------------------
        // 3) Options: mnswmc_* → mns_navasan_plus_*
        // -------------------------
        $rows = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'mnswmc\_%' ESCAPE '\\\\'" );
        foreach ( (array) $rows as $row ) {
            $report['options_scanned']++;
            $new = 'mns_navasan_plus_' . substr( $row->option_name, strlen('mnswmc_') );
            $val = maybe_unserialize( $row->option_value );

            if ( ! $args['dry'] ) {
                // اگر هنوز وجود ندارد، add_option با autoload=no
                if ( get_option( $new, null ) === null ) {
                    add_option( $new, $val, '', 'no' );
                } else {
                    update_option( $new, $val );
                }
                if ( ! empty( $args['delete_old_opts'] ) ) {
                    delete_option( $row->option_name );
                }
            }
            $report['options_moved']++;
        }

        // -------------------------
        // 4) غیرفعال کردن افزونهٔ قدیمی
        // -------------------------
        if ( ! $args['dry'] && ! empty( $args['deactivate_old'] ) ) {
            if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'deactivate_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $old = apply_filters( 'mnsnp/migrator/old_plugin', self::OLD_PLUGIN_BASENAME );
            if ( $old && is_plugin_active( $old ) ) {
                deactivate_plugins( $old, true );
            }
        }

        return $report;
    }
}