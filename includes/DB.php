<?php
/**
 * MNS Navasan Plus – DB wrapper
 * File: includes/DB.php
 */

namespace MNS\NavasanPlus;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DB {

    /** @var self|null */
    private static ?self $instance = null;

    /** Option prefix (options) without leading underscore */
    private string $option_prefix;

    /** Meta prefix (post/user/term meta) with leading underscore */
    private string $meta_prefix;

    private function __construct() {
        $base = defined( 'MNS_NAVASAN_PLUS_DB_PREFIX' ) ? MNS_NAVASAN_PLUS_DB_PREFIX : 'mns_navasan_plus';
        $this->option_prefix = rtrim( (string) $base, '_' );        // mns_navasan_plus
        $this->meta_prefix   = '_' . $this->option_prefix . '_';    // _mns_navasan_plus_
    }

    /** Singleton */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Base option prefix (without leading underscore) */
    public function prefix(): string { return $this->option_prefix; }

    /** (Optional) Access to prefixes for debug */
    public function option_prefix(): string { return $this->option_prefix; }
    public function meta_prefix(): string   { return $this->meta_prefix; }

    // ---------------------------------------------------------------------
    // Options
    // ---------------------------------------------------------------------

    /** Final option key with prefix */
    private function option_key( string $key ): string {
        $key = ltrim( $key, '_' );
        return $this->option_prefix . '_' . $key;
    }

    /** Public access to final option key (for debug/Query) */
    public function full_option_key( string $key ): string {
        return $this->option_key( $key );
    }

    /** Read option with default value */
    public function read_option( string $key, $default = false ) {
        $okey = $this->option_key( $key );
        $val  = get_option( $okey, '__mnsnp_absent__' );
        return ( '__mnsnp_absent__' === $val ) ? $default : $val;
    }

    /** Write option (first add_option then update_option) */
    public function update_option( string $key, $value, bool $autoload = false ): bool {
        $okey = $this->option_key( $key );
        if ( false === get_option( $okey, false ) ) {
            return (bool) add_option( $okey, $value, '', $autoload ? 'yes' : 'no' );
        }
        return (bool) update_option( $okey, $value );
    }

    /** Delete option */
    public function delete_option( string $key ): bool {
        return (bool) delete_option( $this->option_key( $key ) );
    }

    // ---------------------------------------------------------------------
    // Post/User/Term Meta
    // ---------------------------------------------------------------------

    /** Final meta key with prefix */
    private function meta_key( string $key ): string {
        $key = ltrim( $key, '_' );
        return $this->meta_prefix . $key;
    }

    /** Public access to final meta key (for Queries) */
    public function full_meta_key( string $key ): string {
        return $this->meta_key( $key );
    }

    /** Read post meta (single) */
    public function read_post_meta( int $post_id, string $key, $default = false ) {
        $mkey = $this->meta_key( $key );
        $val  = get_post_meta( $post_id, $mkey, true );
        return ( $val === '' || $val === null ) ? $default : $val;
    }

    /** Write post meta (single) */
    public function update_post_meta( int $post_id, string $key, $value ) {
        $mkey = $this->meta_key( $key );
        return update_post_meta( $post_id, $mkey, $value );
    }

    /** Delete post meta */
    public function delete_post_meta( int $post_id, string $key ): bool {
        $mkey = $this->meta_key( $key );
        return (bool) delete_post_meta( $post_id, $mkey );
    }

    /** Read user meta */
    public function read_user_meta( int $user_id, string $key, $default = false ) {
        $mkey = $this->meta_key( $key );
        $val  = get_user_meta( $user_id, $mkey, true );
        return ( $val === '' || $val === null ) ? $default : $val;
    }

    /** Write user meta */
    public function update_user_meta( int $user_id, string $key, $value ) {
        $mkey = $this->meta_key( $key );
        return update_user_meta( $user_id, $mkey, $value );
    }

    /** Delete user meta */
    public function delete_user_meta( int $user_id, string $key ): bool {
        $mkey = $this->meta_key( $key );
        return (bool) delete_user_meta( $user_id, $mkey );
    }

    /** Read term meta */
    public function read_term_meta( int $term_id, string $key, $default = false ) {
        $mkey = $this->meta_key( $key );
        $val  = get_term_meta( $term_id, $mkey, true );
        return ( $val === '' || $val === null ) ? $default : $val;
    }

    /** Write term meta */
    public function update_term_meta( int $term_id, string $key, $value ) {
        $mkey = $this->meta_key( $key );
        return update_term_meta( $term_id, $mkey, $value );
    }

    /** Delete term meta */
    public function delete_term_meta( int $term_id, string $key ): bool {
        $mkey = $this->meta_key( $key );
        return (bool) delete_term_meta( $term_id, $mkey );
    }
}