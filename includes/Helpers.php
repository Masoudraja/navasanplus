<?php
namespace MNS\NavasanPlus;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Helpers {

    /** Optional: plugin version helper (fallback-safe) */
    public static function version(): string {
        return defined('MNS_NAVASAN_PLUS_VER') && MNS_NAVASAN_PLUS_VER
            ? (string) MNS_NAVASAN_PLUS_VER
            : '1.0.1';
    }

    /**
     * Ensure the value is an array; otherwise return default.
     */
    public static function array_if_not( $value, $default = [] ): array {
        return is_array( $value ) ? $value : $default;
    }

    /**
     * Convert Persian/Arabic digits and normalize decimal/thousand separators to EN.
     * (بدون وابستگی به mbstring/intl)
     */
    public static function normalize_digits( string $str ): string {
        static $map = [
            // Persian digits U+06F0..U+06F9
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            // Arabic-Indic digits U+0660..U+0669
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
            // separators
            '٬'=>'',   // U+066C thousands sep → drop
            '،'=>',',  // Arabic comma → comma (بعداً حذف کاما انجام می‌دیم)
            '٫'=>'.',  // Arabic decimal sep → dot
        ];
        return strtr($str, $map);
    }

    /**
     * Sanitize a numeric input (decimal allowed, minus optional).
     */
    public static function sanitize_number( $value, bool $allow_negative = true, ?int $decimals = null ): float {
        if ( is_array( $value ) || is_object( $value ) ) {
            return 0.0;
        }
        $value = (string) $value;

        // Normalize locales & digits
        $value = self::normalize_digits( $value );

        // Remove thousand separators/spaces; keep dot as decimal
        $value = str_replace([',', ' '], '', $value);

        // Keep only digits, dot and minus
        $clean = preg_replace('/[^0-9.\-]/', '', $value) ?? '';

        if ( ! $allow_negative ) {
            $clean = ltrim( $clean, '-' );
        }

        if ( function_exists( 'wc_format_decimal' ) && $decimals !== null ) {
            $formatted = wc_format_decimal( $clean, $decimals );
            return is_numeric( $formatted ) ? (float) $formatted : 0.0;
        }

        return (float) $clean;
    }

    /**
     * Format a decimal number according to site locale.
     */
    public static function format_decimal( $number, int $decimals = 2 ): string {
        $number = (float) $number;
        if ( function_exists( 'number_format_i18n' ) ) {
            return number_format_i18n( $number, $decimals );
        }
        return number_format( $number, $decimals, '.', ',' );
    }

    /**
     * Resolve plugin base file (works with symlinks/mu-plugins).
     */
    public static function plugin_base_file(): string {
        if ( defined( 'MNS_NAVASAN_PLUS_FILE' ) ) {
            return MNS_NAVASAN_PLUS_FILE;
        }
        return dirname( __DIR__ ) . '/mns-navasan-plus.php';
    }

    /**
     * Get plugin URL for a relative path.
     */
    public static function plugin_url( string $path = '' ): string {
        $base = plugins_url( '', self::plugin_base_file() );
        return trailingslashit( $base ) . ltrim( $path, '/' );
    }

    /**
     * Get plugin filesystem path for a relative path.
     */
    public static function plugin_path( string $path = '' ): string {
        $base = plugin_dir_path( self::plugin_base_file() );
        return trailingslashit( $base ) . ltrim( $path, '/' );
    }

    /**
     * Return URL of .min asset when SCRIPT_DEBUG is off and min file exists; fallback otherwise.
     */
    public static function asset_url_min_aware( string $rel_js ): string {
        $use_min = ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );
        if ( $use_min && preg_match( '/\.(js|css)$/i', $rel_js ) ) {
            $min_rel = preg_replace( '/\.(js|css)$/i', '.min.$1', $rel_js );
            if ( $min_rel && file_exists( self::plugin_path( $min_rel ) ) ) {
                return self::plugin_url( $min_rel );
            }
        }
        return self::plugin_url( $rel_js );
    }
}