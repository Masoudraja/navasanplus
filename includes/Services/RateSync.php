<?php
namespace MNS\NavasanPlus\Services;

use MNS\NavasanPlus\DB;

if ( ! defined( 'ABSPATH' ) ) exit;

final class RateSync {

    public const CRON_HOOK = 'mnsnp_sync_rates_event';

    /** حداکثر طول تاریخچهٔ پیش‌فرض */
    private const DEFAULT_HISTORY_MAX = 200;

    /** کلید لاک برای جلوگیری از اجرای همزمان */
    private const LOCK_KEY = 'mnsnp_sync_lock';

    /**
     * Boot: ثبت کران/هوک‌ها
     */
    public static function boot(): void {
        add_filter( 'cron_schedules', [ __CLASS__, 'cron_schedules' ] );
        add_action( self::CRON_HOOK,   [ __CLASS__, 'cron_runner' ] );

        // وقتی تنظیمات ذخیره می‌شود، زمان‌بندی را به‌روز کن
        add_action( 'update_option_mns_navasan_plus_options', function( $old, $new ) {
            self::maybe_reschedule( is_array( $new ) ? $new : [] );
        }, 10, 2 );

        // اطمینان از زمان‌بندی (بعد از هر لود ادمین)
        add_action( 'admin_init', [ __CLASS__, 'ensure_scheduled' ] );
    }

    /**
     * تعریف بازه‌ی زمانی کران از روی تنظیمات
     */
    public static function cron_schedules( array $schedules ): array {
        $opts    = get_option( 'mns_navasan_plus_options', [] );
        $enabled = ! empty( $opts['sync_enable'] );
        $minutes = max( 1, (int) ( $opts['sync_interval'] ?? 10 ) );

        if ( $enabled ) {
            $key = self::interval_key( $minutes );
            $schedules[ $key ] = [
                'interval' => $minutes * 60,
                'display'  => sprintf( __( 'Every %d minutes (Navasan Plus)', 'mns-navasan-plus' ), $minutes ),
            ];
        }

        return $schedules;
    }

    private static function interval_key( int $minutes ): string {
        return 'mnsnp_every_' . $minutes . '_minutes';
    }

    /**
     * اگر لازم است زمان‌بندی را ایجاد/به‌روزرسانی کن
     */
    public static function ensure_scheduled(): void {
        $opts    = get_option( 'mns_navasan_plus_options', [] );
        $enabled = ! empty( $opts['sync_enable'] );
        $minutes = max( 1, (int) ( $opts['sync_interval'] ?? 10 ) );
        $hook    = self::CRON_HOOK;

        // همهٔ رخدادهای قبلی این هوک را پاک کن
        wp_clear_scheduled_hook( $hook );

        if ( $enabled ) {
            $key = self::interval_key( $minutes );
            if ( ! wp_next_scheduled( $hook ) ) {
                wp_schedule_event( time() + 60, $key, $hook );
            }
        }
    }

    /**
     * فراخوان کران: اجرای sync با پارامترهای پیش‌فرض
     */
    public static function cron_runner(): void {
        self::sync();
    }

    /**
     * Reschedule هنگام ذخیره تنظیمات
     */
    public static function maybe_reschedule( array $opts ): void {
        self::ensure_scheduled();
    }

    /**
     * همگام‌سازی نرخ‌ها از سرویس انتخاب‌شده و بروزرسانی CPT ارزها
     *
     * @param array $args {
     *   @type string|null $service      کلید سرویس (مثلاً 'tabangohar')؛ اگر null باشد از تنظیمات خوانده می‌شود
     *   @type bool        $create_new   اگر ارز متناظر پیدا نشد، بسازد؟ (پیش‌فرض: true)
     *   @type int         $history_max  طول آرایه‌ی history (پیش‌فرض: 200)
     * }
     * @return array گزارش
     */
    public static function sync( array $args = [] ): array {
        // قفل ساده برای جلوگیری از اجرای همزمان
        if ( get_transient( self::LOCK_KEY ) ) {
            return [ 'ok' => false, 'error' => 'Sync already running.' ];
        }
        set_transient( self::LOCK_KEY, 1, 5 * MINUTE_IN_SECONDS );

        try {
            $args = wp_parse_args( $args, [
                'service'     => null,
                'create_new'  => true,
                'history_max' => self::DEFAULT_HISTORY_MAX,
            ] );

            $opts        = get_option( 'mns_navasan_plus_options', [] );
            $service_key = $args['service'] ?: ( $opts['api_service'] ?? 'tabangohar' );

            // سرویس را بساز
            switch ( $service_key ) {
                case 'tabangohar':
                    $svc_class = '\MNS\NavasanPlus\Webservices\Rates\TabanGohar';
                    break;
                default:
                    self::remember_last_sync( false, 'Unknown service: ' . $service_key );
                    return [ 'ok' => false, 'error' => 'Unknown service: ' . $service_key ];
            }
            if ( ! class_exists( $svc_class ) ) {
                self::remember_last_sync( false, 'Service class not found.' );
                return [ 'ok' => false, 'error' => 'Service class not found.' ];
            }
            $svc = new $svc_class();

            // دریافت نرخ‌ها
            $res = $svc->retrieve();
            if ( is_wp_error( $res ) ) {
                self::remember_last_sync( false, $res->get_error_message() );
                return [ 'ok' => false, 'error' => $res->get_error_message() ];
            }
            if ( ! is_array( $res ) || ! $res ) {
                self::remember_last_sync( false, 'Empty response.' );
                return [ 'ok' => false, 'error' => 'Empty response.' ];
            }

            $report = [
                'ok'       => true,
                'updated'  => 0,
                'created'  => 0,
                'skipped'  => 0,
                'errors'   => [],
                'total'    => count( $res ),
            ];

            // ترتیب جست‌وجوی متا برای یافتن ارز موجود (سازگار با داده‌های مهاجرتی)
            $db        = DB::instance();
            $meta_keys = [
                $db->full_meta_key( 'currency_rate_symbol' ), // کلید جدید پیشنهادی
                $db->full_meta_key( 'currency_code' ),        // از Migrator
                $db->full_meta_key( 'currency_symbol' ),      // از Migrator
            ];
            $meta_keys = apply_filters( 'mnsnp/rate_sync/currency_lookup_meta_keys', $meta_keys );

            $now = time();

            // اجازه بده هم آرایهٔ انجمنی و هم اندیسی رو بخونیم
            foreach ( $res as $k => $row ) {
                if ( is_array( $row ) ) {
                    $code  = sanitize_text_field( (string) ( $row['code']  ?? $k ) );
                    $name  = sanitize_text_field( (string) ( $row['name']  ?? $code ) );
                    $price = (float) ( $row['price'] ?? 0 );
                } else {
                    // اگر سرویس مقدار ساده بده (نادر)
                    $code  = sanitize_text_field( (string) $k );
                    $name  = $code;
                    $price = (float) $row;
                }

                if ( $price <= 0 ) {
                    $report['skipped']++;
                    continue;
                }

                // پیدا کردن پست ارز با جست‌وجو روی چند متای ممکن
                $pid = 0;
                foreach ( $meta_keys as $mkey ) {
                    $q = get_posts( [
                        'post_type'      => 'mnswmc',
                        'posts_per_page' => 1,
                        'fields'         => 'ids',
                        'post_status'    => 'any',
                        'meta_query'     => [
                            [
                                'key'   => $mkey,
                                'value' => $code,
                            ],
                        ],
                        'suppress_filters' => true,
                    ] );
                    if ( ! empty( $q ) ) { $pid = (int) $q[0]; break; }
                }

                // تلاش دوم: جست‌وجو با s (عنوان/محتوا)
                if ( ! $pid ) {
                    $q = get_posts( [
                        'post_type'      => 'mnswmc',
                        'posts_per_page' => 1,
                        'fields'         => 'ids',
                        'post_status'    => 'any',
                        's'              => $name,
                        'suppress_filters' => true,
                    ] );
                    if ( ! empty( $q ) ) {
                        $pid = (int) $q[0];
                    }
                }

                // ایجاد در صورت عدم یافتن
                if ( ! $pid ) {
                    if ( ! $args['create_new'] ) {
                        $report['skipped']++;
                        continue;
                    }
                    $pid = wp_insert_post( [
                        'post_type'   => 'mnswmc',
                        'post_title'  => $name,
                        'post_status' => 'publish',
                    ], true );
                    if ( is_wp_error( $pid ) ) {
                        $report['errors'][] = $pid->get_error_message();
                        $report['skipped']++;
                        continue;
                    }
                    $report['created']++;

                    // ست‌کردن چند متا برای سازگاری‌های آتی
                    $db->update_post_meta( $pid, 'currency_rate_symbol', $code );
                    $db->update_post_meta( $pid, 'currency_code',        $code );
                }

                // بروزرسانی متاهای نرخ
                $db->update_post_meta( $pid, 'currency_value',       $price );
                $db->update_post_meta( $pid, 'currency_update_time', $now );

                // تاریخچهٔ انجمنی: time => rate  (هماهنگ با سایر بخش‌ها)
                $history = $db->read_post_meta( $pid, 'currency_history', [] );
                if ( ! is_array( $history ) ) $history = [];
                $history[ $now ] = $price;

                $cap = (int) $args['history_max'];
                if ( $cap > 0 && count( $history ) > $cap ) {
                    ksort( $history );                               // مرتب بر اساس زمان
                    $history = array_slice( $history, -$cap, null, true ); // حفظ کلیدها
                }
                $db->update_post_meta( $pid, 'currency_history', $history );

                /**
                 * اکشن بعد از بروزرسانی نرخ یک ارز
                 * @param int    $currency_id
                 * @param string $code
                 * @param float  $price
                 */
                do_action( 'mnsnp/rate_sync/updated_currency', $pid, $code, $price );

                $report['updated']++;
            }

            self::remember_last_sync( true, sprintf( 'Updated:%d Created:%d Skipped:%d', $report['updated'], $report['created'], $report['skipped'] ) );
            return $report;

        } finally {
            delete_transient( self::LOCK_KEY );
        }
    }

    private static function remember_last_sync( bool $ok, string $msg ): void {
        update_option( 'mns_navasan_plus_last_sync', [
            'time' => time(),
            'ok'   => $ok,
            'msg'  => sanitize_text_field( $msg ),
        ], false );
    }
}