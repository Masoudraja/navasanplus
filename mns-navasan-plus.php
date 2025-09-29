<?php
/**
 * Plugin Name: MNS Navasan Plus
 * Description: Advanced WooCommerce pricing plugin based on currency/metals rates with discount, profit and fee fields
 * Version:     1.0.1
 * Author:      Masoudraja@gmail.com
 * Text Domain: mns-navasan-plus
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Main plugin file path */
if ( ! defined( 'MNS_NAVASAN_PLUS_FILE' ) ) {
    define( 'MNS_NAVASAN_PLUS_FILE', __FILE__ );
}

/** Plugin version (for cache busting assets and internal consistency) */
if ( ! defined( 'MNS_NAVASAN_PLUS_VER' ) ) {
    define( 'MNS_NAVASAN_PLUS_VER', '1.0.1' );
}

/** Prefix for option/meta keys */
if ( ! defined( 'MNS_NAVASAN_PLUS_DB_PREFIX' ) ) {
    define( 'MNS_NAVASAN_PLUS_DB_PREFIX', 'mns_navasan_plus' );
}

/** Composer autoload (if available) */
$composer_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
    require_once $composer_autoload;
} else {
    // Light autoloader for plugin classes when we don't have Composer
    spl_autoload_register( static function ( $class ) {
        // Only plugin namespace
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

/** Essential base files (safe; no conflict with autoloader) */
require_once __DIR__ . '/includes/DB.php';
require_once __DIR__ . '/includes/Helpers.php';
require_once __DIR__ . '/includes/Loader.php';
require_once __DIR__ . '/includes/Plugin.php';

/**
 * If you don't have Composer, we also manually load the two services below
 * so that RateSync (WP-Cron) and REST API are always available.
 * (They are also auto-loaded with autoloader; this is just extra assurance.)
 */
$maybe_files = [
    __DIR__ . '/includes/Services/RateSync.php',
    __DIR__ . '/includes/REST/RatesController.php',
];
foreach ( $maybe_files as $mf ) {
    if ( file_exists( $mf ) ) { require_once $mf; }
}

/**
 * Old plugin path (for migration tool)
 */
add_filter( 'mnsnp/migrator/old_plugin', function () {
    $basename = 'mns-woocommerce-rate-based-products/mns-woocommerce-rate-based-products.php';
    return file_exists( WP_PLUGIN_DIR . '/' . $basename ) ? $basename : '';
} );

/**
 * Global helper: Plugin Singleton
 *
 * @return \MNS\NavasanPlus\Plugin
 */
if ( ! function_exists( 'mns_navasan_plus' ) ) {
    function mns_navasan_plus(): \MNS\NavasanPlus\Plugin {
        return \MNS\NavasanPlus\Plugin::instance( MNS_NAVASAN_PLUS_FILE );
    }
}

/**
 * [Optional] Enable "Currency Chart" metabox
 * Default is off. To enable, just uncomment this line.
 */
// add_filter( 'mnsnp/enable_currency_chart_metabox', '__return_true' );

/** Boot plugin after loading other plugins */
add_action( 'plugins_loaded', static function () {
    mns_navasan_plus()->run();
} );

/** Initial setup during activation */
register_activation_hook( __FILE__, static function () {
    try {
        \MNS\NavasanPlus\DB::instance()->update_option( 'install_time', time(), true );
    } catch ( \Throwable $e ) { /* silent */ }
} );

/** Clean up schedules during deactivation (for RateSync) */
register_deactivation_hook( __FILE__, static function () {
    if ( class_exists( '\MNS\NavasanPlus\Services\RateSync' ) ) {
        try {
            \MNS\NavasanPlus\Services\RateSync::unschedule();
        } catch ( \Throwable $e ) { /* silent */ }
    }
} );