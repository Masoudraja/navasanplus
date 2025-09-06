<?php
/**
 * Plugin Name: MNS Navasan Plus
 * Description: افزونه‌ی پیشرفته قیمت‌گذاری ووکامرس بر پایه نرخ ارز/فلزات با فیلدهای تخفیف سود و اجرت
 * Version:     1.0.1
 * Author:      Masoud
 * Text Domain: mns-navasan-plus
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** مسیر فایل اصلی افزونه */
if ( ! defined( 'MNS_NAVASAN_PLUS_FILE' ) ) {
    define( 'MNS_NAVASAN_PLUS_FILE', __FILE__ );
}

/** نسخه‌ی افزونه (برای کش‌شکنی assetها و همسانی داخلی) */
if ( ! defined( 'MNS_NAVASAN_PLUS_VER' ) ) {
    define( 'MNS_NAVASAN_PLUS_VER', '1.0.1' );
}

/** پیشوند کلیدهای گزینه/متا */
if ( ! defined( 'MNS_NAVASAN_PLUS_DB_PREFIX' ) ) {
    define( 'MNS_NAVASAN_PLUS_DB_PREFIX', 'mns_navasan_plus' );
}

/** Composer autoload (اگر موجود بود) */
$composer_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
    require_once $composer_autoload;
} else {
    // اتولودر سبک برای کلاس‌های افزونه وقتی Composer نداریم
    spl_autoload_register( static function ( $class ) {
        // فقط فضای نام افزونه
        $prefixes = [
            'MNS\\NavasanPlus\\Templates\\' => __DIR__ . '/templates/',
            'MNS\\NavasanPlus\\'            => __DIR__ . '/includes/',
        ];
        foreach ( $prefixes as $prefix => $base_dir ) {
            $len = strlen( $prefix );
            if ( strncmp( $class, $prefix, $len ) !== 0 ) {
                continue;
            }
            $relative = substr( $class, $len );
            $relative_path = str_replace( '\\', '/', $relative ) . '.php';
            $file = $base_dir . $relative_path;
            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }
    } );
}

/** فایل‌های ضروری پایه (ایمن؛ با اتولودر هم تداخلی ندارد) */
require_once __DIR__ . '/includes/DB.php';
require_once __DIR__ . '/includes/Helpers.php';
require_once __DIR__ . '/includes/Loader.php';
require_once __DIR__ . '/includes/Plugin.php';

/**
 * اگر Composer ندارید، دو سرویس زیر را هم دستی لود می‌کنیم
 * تا RateSync (WP-Cron) و REST API همیشه در دسترس باشند.
 * (با اتولودر هم خودکار لود می‌شوند؛ این فقط اطمینان بیشتر است.)
 */
$maybe_files = [
    __DIR__ . '/includes/Services/RateSync.php',
    __DIR__ . '/includes/REST/RatesController.php',
];
foreach ( $maybe_files as $mf ) {
    if ( file_exists( $mf ) ) { require_once $mf; }
}

/**
 * مسیر افزونهٔ قدیمی (برای ابزار مهاجرت)
 */
add_filter( 'mnsnp/migrator/old_plugin', function () {
    $basename = 'mns-woocommerce-rate-based-products/mns-woocommerce-rate-based-products.php';
    return file_exists( WP_PLUGIN_DIR . '/' . $basename ) ? $basename : '';
} );

/**
 * هِلپر سراسری: Singleton افزونه
 *
 * @return \MNS\NavasanPlus\Plugin
 */
if ( ! function_exists( 'mns_navasan_plus' ) ) {
    function mns_navasan_plus(): \MNS\NavasanPlus\Plugin {
        return \MNS\NavasanPlus\Plugin::instance( MNS_NAVASAN_PLUS_FILE );
    }
}

/**
 * [اختیاری] فعال‌سازی متاباکس «Currency Chart»
 * پیش‌فرض خاموش است. برای روشن‌کردن، فقط کامنت این خط را بردار.
 */
// add_filter( 'mnsnp/enable_currency_chart_metabox', '__return_true' );

/** بوت افزونه پس از لود سایر افزونه‌ها */
add_action( 'plugins_loaded', static function () {
    mns_navasan_plus()->run();
} );

/** مقداردهی اولیه هنگام فعال‌سازی */
register_activation_hook( __FILE__, static function () {
    try {
        \MNS\NavasanPlus\DB::instance()->update_option( 'install_time', time(), true );
    } catch ( \Throwable $e ) { /* silent */ }
} );

/** پاک‌سازی زمان‌بندی‌ها هنگام غیرفعال‌سازی (برای RateSync) */
register_deactivation_hook( __FILE__, static function () {
    if ( class_exists( '\MNS\NavasanPlus\Services\RateSync' ) ) {
        try {
            \MNS\NavasanPlus\Services\RateSync::unschedule();
        } catch ( \Throwable $e ) { /* silent */ }
    }
} );