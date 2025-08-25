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

    /** پیشوند گزینه‌ها (options) بدون زیرخط ابتدایی */
    private string $option_prefix;

    /** پیشوند متاها (post/user/term meta) با زیرخط ابتدایی */
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

    /** پیشوند پایه‌ی options (بدون زیرخط ابتدا) */
    public function prefix(): string { return $this->option_prefix; }

    /** (اختیاری) دسترسی به پیشوندها برای دیباگ */
    public function option_prefix(): string { return $this->option_prefix; }
    public function meta_prefix(): string   { return $this->meta_prefix; }

    // ---------------------------------------------------------------------
    // Options
    // ---------------------------------------------------------------------

    /** کلید option نهایی با پیشوند */
    private function option_key( string $key ): string {
        $key = ltrim( $key, '_' );
        return $this->option_prefix . '_' . $key;
    }

    /** دسترسی عمومی به کلید option نهایی (برای دیباگ/Query) */
    public function full_option_key( string $key ): string {
        return $this->option_key( $key );
    }

    /** خواندن گزینه با مقدار پیش‌فرض */
    public function read_option( string $key, $default = false ) {
        $okey = $this->option_key( $key );
        $val  = get_option( $okey, '__mnsnp_absent__' );
        return ( '__mnsnp_absent__' === $val ) ? $default : $val;
    }

    /** نوشتن گزینه (اول add_option سپس update_option) */
    public function update_option( string $key, $value, bool $autoload = false ): bool {
        $okey = $this->option_key( $key );
        if ( false === get_option( $okey, false ) ) {
            return (bool) add_option( $okey, $value, '', $autoload ? 'yes' : 'no' );
        }
        return (bool) update_option( $okey, $value );
    }

    /** حذف گزینه */
    public function delete_option( string $key ): bool {
        return (bool) delete_option( $this->option_key( $key ) );
    }

    // ---------------------------------------------------------------------
    // Post/User/Term Meta
    // ---------------------------------------------------------------------

    /** کلید meta نهایی با پیشوند */
    private function meta_key( string $key ): string {
        $key = ltrim( $key, '_' );
        return $this->meta_prefix . $key;
    }

    /** دسترسی عمومی به کلید meta نهایی (برای Queryها) */
    public function full_meta_key( string $key ): string {
        return $this->meta_key( $key );
    }

    /** خواندن متای پست (single) */
    public function read_post_meta( int $post_id, string $key, $default = false ) {
        $mkey = $this->meta_key( $key );
        $val  = get_post_meta( $post_id, $mkey, true );
        return ( $val === '' || $val === null ) ? $default : $val;
    }

    /** نوشتن متای پست (single) */
    public function update_post_meta( int $post_id, string $key, $value ) {
        $mkey = $this->meta_key( $key );
        return update_post_meta( $post_id, $mkey, $value );
    }

    /** حذف متای پست */
    public function delete_post_meta( int $post_id, string $key ): bool {
        $mkey = $this->meta_key( $key );
        return (bool) delete_post_meta( $post_id, $mkey );
    }

    /** خواندن متای کاربر */
    public function read_user_meta( int $user_id, string $key, $default = false ) {
        $mkey = $this->meta_key( $key );
        $val  = get_user_meta( $user_id, $mkey, true );
        return ( $val === '' || $val === null ) ? $default : $val;
    }

    /** نوشتن متای کاربر */
    public function update_user_meta( int $user_id, string $key, $value ) {
        $mkey = $this->meta_key( $key );
        return update_user_meta( $user_id, $mkey, $value );
    }

    /** حذف متای کاربر */
    public function delete_user_meta( int $user_id, string $key ): bool {
        $mkey = $this->meta_key( $key );
        return (bool) delete_user_meta( $user_id, $mkey );
    }

    /** خواندن متای ترم */
    public function read_term_meta( int $term_id, string $key, $default = false ) {
        $mkey = $this->meta_key( $key );
        $val  = get_term_meta( $term_id, $mkey, true );
        return ( $val === '' || $val === null ) ? $default : $val;
    }

    /** نوشتن متای ترم */
    public function update_term_meta( int $term_id, string $key, $value ) {
        $mkey = $this->meta_key( $key );
        return update_term_meta( $term_id, $mkey, $value );
    }

    /** حذف متای ترم */
    public function delete_term_meta( int $term_id, string $key ): bool {
        $mkey = $this->meta_key( $key );
        return (bool) delete_term_meta( $term_id, $mkey );
    }
}