<?php
namespace MNS\NavasanPlus\Services;

use MNS\NavasanPlus\DB;

if ( ! defined( 'ABSPATH' ) ) exit;

final class RateSync {

    public const CRON_HOOK = 'mnsnp_sync_rates_event';
    private const DEFAULT_HISTORY_MAX = 200;
    private const LOCK_KEY = 'mnsnp_sync_lock';

    /**
     * Boot: Hook into WordPress.
     */
    public static function boot(): void {
        add_filter( 'cron_schedules', [ __CLASS__, 'cron_schedules' ] );
        add_action( self::CRON_HOOK,  [ __CLASS__, 'cron_runner' ] );

        // Only reschedule when the settings are actually saved.
        add_action( 'update_option_mns_navasan_plus_options', [ __CLASS__, 'ensure_scheduled' ], 10, 0 );
        // Note: You should also call `ensure_scheduled()` once in your plugin's activation hook.
    }
    
    /**
     * Unschedule events on deactivation.
     */
    public static function unschedule(): void {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * Define custom cron interval based on plugin settings.
     */
    public static function cron_schedules( array $schedules ): array {
        $opts    = get_option( 'mns_navasan_plus_options', [] );
        $enabled = ! empty( $opts['sync_enable'] );
        $minutes = max( 1, (int) ( $opts['sync_interval'] ?? 10 ) );

        if ( $enabled ) {
            $key = self::interval_key( $minutes );
            if ( ! isset( $schedules[ $key ] ) ) {
                $schedules[ $key ] = [
                    'interval' => $minutes * 60,
                    'display'  => sprintf( __( 'Every %d minutes (Navasan Plus)', 'mns-navasan-plus' ), $minutes ),
                ];
            }
        }
        return $schedules;
    }

    private static function interval_key( int $minutes ): string {
        return 'mnsnp_every_' . $minutes . '_minutes';
    }

    /**
     * Create or update the schedule if needed.
     */
    public static function ensure_scheduled(): void {
        $opts    = get_option( 'mns_navasan_plus_options', [] );
        $enabled = ! empty( $opts['sync_enable'] );
        $minutes = max( 1, (int) ( $opts['sync_interval'] ?? 10 ) );
        $hook    = self::CRON_HOOK;

        // First, clear any existing instances of this hook to avoid duplicates.
        wp_clear_scheduled_hook( $hook );

        // If enabled, schedule the new event.
        if ( $enabled ) {
            $key = self::interval_key( $minutes );
            wp_schedule_event( time() + 60, $key, $hook );
        }
    }

    /**
     * The main function called by the cron job.
     */
    public static function cron_runner(): void {
        self::sync();
    }

    /**
     * Syncs rates from the selected service and updates currency CPTs.
     */
    public static function sync( array $args = [] ): array {
        if ( get_transient( self::LOCK_KEY ) ) {
            return [ 'ok' => false, 'error' => 'Sync already running.' ];
        }
        set_transient( self::LOCK_KEY, 1, 5 * MINUTE_IN_SECONDS );

        try {
            $args = wp_parse_args( $args, [
                'service'     => null,
                'create_new'  => true,
                'history_max' => self::DEFAULT_HISTORY_MAX,
            ]);

            $opts        = get_option( 'mns_navasan_plus_options', [] );
            $service_key = $args['service'] ?: ( $opts['api_service'] ?? 'tabangohar' );

            $svc_class = '\MNS\NavasanPlus\Webservices\Rates\TabanGohar'; // Assuming only one for now
            if ( ! class_exists( $svc_class ) ) {
                throw new \Exception( 'Service class not found.' );
            }
            $svc = new $svc_class();
            $res = $svc->retrieve();

            if ( is_wp_error( $res ) ) {
                throw new \Exception( $res->get_error_message() );
            }
            if ( ! is_array( $res ) || ! $res ) {
                throw new \Exception( 'Empty or invalid response from webservice.' );
            }

            $report = [ 'ok' => true, 'updated' => 0, 'created' => 0, 'skipped' => 0, 'errors' => [], 'total' => count( $res ) ];
            $db     = DB::instance();
            $now    = time();

            // <<< OPTIMIZATION: Fetch all existing currencies and their lookup codes in one go.
            $existing_currencies_map = self::get_existing_currencies_map();

            foreach ( $res as $k => $row ) {
                $code  = sanitize_text_field( (string) ( $row['code']  ?? $k ) );
                $name  = sanitize_text_field( (string) ( $row['name']  ?? $code ) );
                $price = (float) ( $row['price'] ?? 0 );

                if ( $price <= 0 ) {
                    $report['skipped']++;
                    continue;
                }

                // <<< OPTIMIZATION: Look up the currency ID from our pre-fetched map.
                $pid = $existing_currencies_map[ $code ] ?? 0;

                // If not found, create it (if enabled)
                if ( ! $pid ) {
                    if ( ! $args['create_new'] ) {
                        $report['skipped']++;
                        continue;
                    }
                    $pid = wp_insert_post( [
                        'post_type'   => 'mnsnp_currency',
                        'post_title'  => $name,
                        'post_status' => 'publish',
                    ], true );

                    if ( is_wp_error( $pid ) ) {
                        $report['errors'][] = $pid->get_error_message();
                        $report['skipped']++;
                        continue;
                    }
                    $report['created']++;
                    $db->update_post_meta( $pid, 'currency_rate_symbol', $code );
                    $db->update_post_meta( $pid, 'currency_code',        $code );
                }

                // Update rate metas
                $db->update_post_meta( $pid, 'currency_value', $price );
                $db->update_post_meta( $pid, 'currency_update_time', $now );

                // Update history
                $history = $db->read_post_meta( $pid, 'currency_history', [] );
                if ( ! is_array( $history ) ) $history = [];
                $history[ $now ] = $price;

                $cap = (int) $args['history_max'];
                if ( $cap > 0 && count( $history ) > $cap ) {
                    ksort( $history );
                    $history = array_slice( $history, -$cap, null, true );
                }
                $db->update_post_meta( $pid, 'currency_history', $history );

                do_action( 'mnsnp/rate_sync/updated_currency', $pid, $code, $price );
                $report['updated']++;
            }

            self::remember_last_sync( true, sprintf( 'Updated:%d Created:%d Skipped:%d', $report['updated'], $report['created'], $report['skipped'] ) );
            return $report;

        } catch ( \Throwable $e ) {
            self::remember_last_sync( false, $e->getMessage() );
            return [ 'ok' => false, 'error' => $e->getMessage() ];
        } finally {
            delete_transient( self::LOCK_KEY );
        }
    }

    /**
     * OPTIMIZATION HELPER: Fetches all currencies and maps them by their lookup codes.
     * Returns a map of [ 'code' => post_id ].
     */
    private static function get_existing_currencies_map(): array {
        global $wpdb;
        $db = DB::instance();
        $map = [];

        $meta_key = $db->full_meta_key( 'currency_rate_symbol' ); // Primary lookup key
        
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID, pm.meta_value FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s AND pm.meta_key = %s",
            'mnsnp_currency',
            $meta_key
        ) );

        if ( is_array( $results ) ) {
            foreach ( $results as $row ) {
                $map[ $row->meta_value ] = (int) $row->ID;
            }
        }
        return $map;
    }

    private static function remember_last_sync( bool $ok, string $msg ): void {
        update_option( 'mns_navasan_plus_last_sync', [
            'time' => time(),
            'ok'   => $ok,
            'msg'  => sanitize_text_field( $msg ),
        ], false );
    }
}