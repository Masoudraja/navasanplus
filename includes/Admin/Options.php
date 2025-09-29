<?php
namespace MNS\NavasanPlus\Admin;

use MNS\NavasanPlus\DB;
use MNS\NavasanPlus\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides accessors for all plugin options.
 */
class Options {

    public static function get_telegram_bot_product_alert(): bool|string {
        $active = DB::instance()->read_option( 'telegram_bot_product_alert' );
        return $active ? 'yes' : false;
    }

    public static function get_telegram_bot_token(): string {
        return DB::instance()->read_option( 'telegram_bot_token', '' );
    }

    public static function get_rest_api_active(): bool|string {
        $active = DB::instance()->read_option( 'rest_api_active' );
        return $active ? 'yes' : false;
    }

    /**
     * Main REST token.
     * - If empty, auto-generate once and persist (safer than a hardcoded default).
     */
    public static function get_rest_api_main_token(): string {
        $db = DB::instance();
        $t  = (string) $db->read_option( 'rest_api_main_token', '' );
        if ( $t === '' ) {
            if ( function_exists( 'wp_generate_password' ) ) {
                $t = wp_generate_password( 32, false, false );
            } else {
                try {
                    $t = bin2hex( random_bytes( 16 ) );
                } catch ( \Throwable $e ) {
                    // very unlikely fallback
                    $t = md5( uniqid( 'mnsnp', true ) );
                }
            }
            $db->update_option( 'rest_api_main_token', $t, false );
        }
        return $t;
    }

    public static function get_global_rounding(): array {
        $rounding = DB::instance()->read_option( 'global_rounding', [] );
        $rounding = is_array( $rounding ) ? $rounding : [];
        $rounding['value'] = ( isset( $rounding['value'] ) && is_numeric( $rounding['value'] ) )
            ? (string) $rounding['value']
            : '0';
        $rounding['type']  = $rounding['type']  ?? 'zero';
        $rounding['side']  = $rounding['side']  ?? 'close';
        return $rounding;
    }

    public static function get_debug_mode(): bool|string {
        $active = DB::instance()->read_option( 'debug_mode' );
        return $active ? 'yes' : false;
    }

    public static function get_prices_check_type(): string {
        $type = DB::instance()->read_option( 'prices_check_type', 'parallel' );
        return in_array( $type, [ 'none', 'parallel', 'consecutive' ], true )
            ? $type
            : 'parallel';
    }

    public static function get_adminbar_rates(): array {
        return Helpers::array_if_not( DB::instance()->read_option( 'adminbar_rates', [] ) );
    }

    public static function get_display_order_rates(): bool|string {
        $active = DB::instance()->read_option( 'display_order_rates' );
        return $active ? 'yes' : false;
    }

    public static function get_display_order_totals(): string {
        $currency_id = DB::instance()->read_option( 'display_order_totals', '0' );
        return is_numeric( $currency_id ) ? (string) $currency_id : '0';
    }

    public static function get_cancel_orders(): array {
        $cancel = DB::instance()->read_option( 'cancel_orders', [] );
        $cancel = is_array( $cancel ) ? $cancel : [];
        $cancel['increase'] = $cancel['increase'] ?? '';
        $cancel['decrease'] = $cancel['decrease'] ?? '';
        return $cancel;
    }

    public static function get_cancel_orders_statuses(): array {
        $statuses = DB::instance()->read_option( 'cancel_orders_statuses', [] );
        $statuses = is_array( $statuses ) ? $statuses : [];
        return count( $statuses ) ? $statuses : [ 'wc-pending' ];
    }

    public static function get_cancel_orders_tolerance(): array {
        $tol = DB::instance()->read_option( 'cancel_orders_tolerance', [] );
        $tol = is_array( $tol ) ? $tol : [];
        $tol['increase'] = $tol['increase'] ?? '0';
        $tol['decrease'] = $tol['decrease'] ?? '0';
        return $tol;
    }

    public static function get_rate_changes_number(): string {
        $number = DB::instance()->read_option( 'rate_changes_number', '' );
        return $number ?: '';
    }

    public static function get_webservices_options(): array {
        $opts = DB::instance()->read_option( 'webservices_options', [] );
        return is_array( $opts ) ? $opts : [];
    }

    public static function get_sms_webservice(): string {
        return DB::instance()->read_option( 'sms_webservice', 'none' );
    }

    public static function get_sms_username(): string {
        return DB::instance()->read_option( 'sms_username', '' );
    }

    public static function get_sms_password(): string {
        return DB::instance()->read_option( 'sms_password', '' );
    }

    public static function get_sms_apikey(): string {
        return DB::instance()->read_option( 'sms_apikey', '' );
    }

    public static function get_sms_secretkey(): string {
        return DB::instance()->read_option( 'sms_secretkey', '' );
    }

    public static function get_sms_from(): string {
        return DB::instance()->read_option( 'sms_from', '' );
    }

    /**
     * Patterns + ensure defaults for registered patterns.
     * Safe if SMS service class isn’t present yet.
     */
    public static function get_sms_patterns(): array {
        $patterns = DB::instance()->read_option( 'sms_patterns', [] );
        $patterns = is_array( $patterns ) ? $patterns : [];

        $registered = [];
        if ( class_exists( '\MNS\NavasanPlus\Services\SMS' ) ) {
            try {
                $registered = \MNS\NavasanPlus\Services\SMS::instance()->get_patterns();
            } catch ( \Throwable $e ) {
                $registered = [];
            }
        }

        foreach ( $registered as $key => $vals ) {
            if ( ! isset( $patterns[ $key ] ) ) {
                $patterns[ $key ] = [
                    'id'   => '',
                    'vars' => array_fill_keys( array_keys( (array) ( $vals['vars'] ?? [] ) ), '' ),
                    'text' => (string) ( $vals['text'] ?? '' ),
                ];
            } else {
                // Ensure all vars keys exist
                $need_keys = array_keys( (array) ( $vals['vars'] ?? [] ) );
                foreach ( $need_keys as $vk ) {
                    if ( ! isset( $patterns[ $key ]['vars'][ $vk ] ) ) {
                        $patterns[ $key ]['vars'][ $vk ] = '';
                    }
                }
                if ( ! isset( $patterns[ $key ]['text'] ) && isset( $vals['text'] ) ) {
                    $patterns[ $key ]['text'] = (string) $vals['text'];
                }
            }
        }

        return $patterns;
    }
}