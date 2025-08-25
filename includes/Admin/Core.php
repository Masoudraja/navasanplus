<?php
namespace MNS\NavasanPlus\Admin;

use MNS\NavasanPlus\Helpers;
use MNS\NavasanPlus\DB;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Core: بارگذاری استایل/اسکریپت ادمین + بوت مؤلفه‌های ادمین
 */
final class Core {
    /** @var Core|null */
    private static $_instance = null;

    private function __construct() {
        // اسکریپت/استایل بخش ادمین
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        // نوتیس بازبینی/امتیاز
        add_action( 'admin_notices',        [ $this, 'review_notice' ] );

        // ـ بوت تنظیمات
        if ( class_exists( __NAMESPACE__ . '\\Settings' ) && method_exists( Settings::class, 'instance' ) ) {
            Settings::instance(); // معمولاً خودش هوک‌ها را رجیستر می‌کند
        }

        // ـ ثبت CPTها (اگر init استاتیک ندارد، run نمونه‌ای را صدا بزن)
        if ( class_exists( __NAMESPACE__ . '\\PostTypes' ) ) {
            if ( method_exists( PostTypes::class, 'init' ) ) {
                PostTypes::init();
            } elseif ( method_exists( PostTypes::class, 'run' ) ) {
                ( new PostTypes() )->run();
            }
        }

        // ـ متاباکس‌ها (کلاس شما متد run دارد)
        if ( class_exists( __NAMESPACE__ . '\\MetaBoxes' ) ) {
            ( new MetaBoxes() )->run();
        }

        // ـ ادغام ووکامرس (کلاس شما متد run دارد)
        if ( class_exists( __NAMESPACE__ . '\\WooCommerce' ) ) {
            ( new WooCommerce() )->run();
        }

        // توجه: Widgets نداریم، پس چیزی صدا نمی‌زنیم
    }

    /** @return Core */
    public static function instance(): self {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function enqueue_assets(): void {
        wp_enqueue_style(
            'mns-navasan-plus-admin',
            Helpers::plugin_url( 'assets/css/admin.css' ),
            [],
            '1.0.0'
        );
        wp_enqueue_script(
            'mns-navasan-plus-admin',
            Helpers::plugin_url( 'assets/js/admin.js' ),
            [ 'jquery' ],
            '1.0.0',
            true
        );
    }

    public function review_notice(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // اگر قبلاً بسته شده
        if ( DB::instance()->read_user_meta( get_current_user_id(), 'rating_notice_info_demiss', false ) ) {
            return;
        }

        $install_time   = (int) DB::instance()->read_option( 'install_time', 0 );
        $install_passed = time() - $install_time;
        if ( $install_passed < WEEK_IN_SECONDS ) {
            return;
        }

        $reminder_time   = (int) DB::instance()->read_user_meta( get_current_user_id(), 'rating_notice_info_reminder_time', 0 );
        $reminder_passed = time() - $reminder_time;
        if ( $reminder_passed < WEEK_IN_SECONDS ) {
            return;
        }

        // ستاره‌ها
        $stars = str_repeat( '<i class="dashicons dashicons-star-filled" style="color:#fea000;"></i>', 5 );
        $nonce = wp_create_nonce( 'mns_navasan_plus_rating_notice_nonce' );
        $message = sprintf(
            /* translators: 1: plugin name, 2: stars HTML */
            __( "You've been using the %1\$s plugin for more than a week. If you like it, please give us a %2\$s rating.", 'mns-navasan-plus' ),
            'Navasan Plus',
            $stars
        );

        printf(
            '<div class="notice notice-info is-dismissible" data-nonce="%1$s"><p>%2$s</p></div>',
            esc_attr( $nonce ),
            wp_kses_post( $message )
        );
    }
}