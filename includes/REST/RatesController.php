<?php
namespace MNS\NavasanPlus\REST;

use MNS\NavasanPlus\DB;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * POST /wp-json/mnsnp/v1/rates
 * Payload:
 * { "rates": [ { "id": 123, "rate": 456.78, "time": 1710000000 }, ... ] }
 * Auth: ?token=...  یا  Header: X-API-TOKEN
 */
final class RatesController {

    /** سقف پیش‌فرض تاریخچه (قابل override با فیلتر) */
    private int $default_history_cap = 500;

    public function boot(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route(
            'mnsnp/v1',
            '/rates',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_post' ],
                'permission_callback' => [ $this, 'permission' ],
                'args'                => [
                    'rates' => [
                        'required' => true,
                        'type'     => 'array',
                    ],
                ],
            ]
        );
    }

    /** خواندن توکن از Settings (اگه موجود بود) با سازگاری عقب‌رو */
    private function get_token(): string {
        // ترجیح: Settings::get_rest_api_main_token()
        if ( class_exists( '\MNS\NavasanPlus\Admin\Settings' )
          && method_exists( '\MNS\NavasanPlus\Admin\Settings', 'get_rest_api_main_token' ) ) {
            $tok = (string) \MNS\NavasanPlus\Admin\Settings::get_rest_api_main_token();
            if ( $tok !== '' ) return $tok;
        }

        // فالبک: از DB/options بخون
        $db  = DB::instance();
        $tok = (string) $db->read_option( 'rest_api_main_token', '' );
        if ( $tok !== '' ) return $tok;

        $tok = (string) $db->read_option( 'rest_main_token', '' );
        if ( $tok !== '' ) return $tok;

        $opts = (array) get_option( 'mns_navasan_plus_options', [] );
        if ( ! empty( $opts['rest_api_main_token'] ) ) return (string) $opts['rest_api_main_token'];
        if ( ! empty( $opts['rest_main_token'] ) )     return (string) $opts['rest_main_token'];

        return '';
    }

    /** Permission: تطبیق توکن */
    public function permission( WP_REST_Request $request ) {
        $provided = (string) ( $request->get_param('token') ?: $request->get_header('X-API-TOKEN') );
        $valid    = $this->get_token();

        if ( $valid === '' ) {
            return new WP_Error( 'mnsnp_token_missing', __( 'API token is not configured.', 'mns-navasan-plus' ), [ 'status' => 403 ] );
        }
        if ( ! hash_equals( $valid, $provided ) ) {
            return new WP_Error( 'mnsnp_forbidden', __( 'Invalid API token.', 'mns-navasan-plus' ), [ 'status' => 403 ] );
        }
        return true;
    }

    /** هندل POST /rates */
    public function handle_post( WP_REST_Request $request ) {
        $json  = $request->get_json_params();
        $rates = $json['rates'] ?? $request->get_param('rates');

        if ( ! is_array( $rates ) ) {
            return new WP_Error( 'mnsnp_bad_request', __( 'Invalid payload: rates must be array.', 'mns-navasan-plus' ), [ 'status' => 400 ] );
        }

        $db   = DB::instance();

        // سقف تاریخچه (قابل سفارشی‌سازی با فیلتر)
        $history_cap = (int) apply_filters(
            'mnsnp/rates_history_cap',
            (int) apply_filters( 'mnsnp/history_cap', $this->default_history_cap )
        );
        if ( $history_cap < 10 ) { $history_cap = 10; }

        $report = [ 'updated' => 0, 'skipped' => 0, 'ids' => [] ];
        $now    = time();

        foreach ( $rates as $item ) {
            $cid  = isset( $item['id'] )   ? (int) $item['id']   : 0;
            $rate = isset( $item['rate'] ) ? $item['rate']       : null;
            $t    = isset( $item['time'] ) ? (int) $item['time'] : $now;

            // اعتبارسنجی پایه
            if ( $cid <= 0 || ! is_numeric( $rate ) ) { $report['skipped']++; continue; }
            $rate = (float) $rate;
            if ( ! is_finite( $rate ) || $rate < 0 ) { $report['skipped']++; continue; }

            // پست باید وجود داشته باشه (از نوشتن روی ID نامعتبر جلوگیری می‌کنیم)
            $post = get_post( $cid );
            if ( ! $post ) { $report['skipped']++; continue; }

            // زمان معقول: اگر نامعتبر/خیلی آینده، همین الان
            if ( $t < 1 || $t > $now + 86400 ) { $t = $now; }

            // ذخیره نرخ جاری + زمان آخرین آپدیت
            $db->update_post_meta( $cid, 'currency_value',       $rate );
            $db->update_post_meta( $cid, 'currency_update_time', $t );

            // تاریخچه با سقف
            $hist = $db->read_post_meta( $cid, 'currency_history', [] );
            if ( ! is_array( $hist ) ) { $hist = []; }
            $hist[ $t ] = $rate;

            if ( count( $hist ) > $history_cap ) {
                ksort( $hist, SORT_NUMERIC );
                $hist = array_slice( $hist, -$history_cap, null, true );
            }
            $db->update_post_meta( $cid, 'currency_history', $hist );

            // رویداد برای توسعه‌دهندگان
            do_action( 'mnsnp/rate_updated', $cid, $rate, $t, $hist );

            $report['updated']++;
            $report['ids'][] = $cid;
        }

        // یکتا کردن شناسه‌ها
        $report['ids'] = array_values( array_unique( $report['ids'] ) );

        /**
         * بعد از اتمام همه‌ی آیتم‌ها (برای لاگ/کش/تریگرهای جانبی)
         */
        do_action( 'mnsnp/rates_bulk_updated', $report );

        return new WP_REST_Response(
            [ 'ok' => true, 'message' => 'Rates updated', 'report' => $report ],
            200
        );
    }
}