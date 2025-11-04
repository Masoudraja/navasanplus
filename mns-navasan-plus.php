<?php
/**
 * Plugin Name: MNS Navasan Plus
 * Description: Advanced WooCommerce product pricing based on live currency rates and formulas.
 * Version:     1.0.3
 * Author:      Masoud Rajabi
 * Text Domain: mns-navasan-plus
 * Domain Path: /languages
 *
 * WC requires at least: 4.0
 * WC tested up to: 8.3
 */

namespace MNS\NavasanPlus;

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
  exit();
}

// Define plugin constants
if (!defined('MNS_NAVASAN_PLUS_FILE')) {
  define('MNS_NAVASAN_PLUS_FILE', __FILE__);
}
if (!defined('MNS_NAVASAN_PLUS_DIR')) {
  define('MNS_NAVASAN_PLUS_DIR', __DIR__);
}
if (!defined('MNS_NAVASAN_PLUS_URL')) {
  define('MNS_NAVASAN_PLUS_URL', plugin_dir_url(__FILE__));
}
if (!defined('MNS_NAVASAN_PLUS_VER')) {
  define('MNS_NAVASAN_PLUS_VER', '1.0.3');
}

// Load Composer autoloader if it exists
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
  require_once __DIR__ . '/vendor/autoload.php';
}

// Load helper functions
if (!function_exists('MNS\NavasanPlus\is_order_pay_page')) {
  require_once __DIR__ . '/includes/Helpers.php';
}

/**
 * If you don't have Composer, we also manually load the two services below
 * so that RateSync (WP-Cron) and REST API are always available.
 * (They are also auto-loaded with autoloader; this is just extra assurance.)
 */
$maybe_files = [
  __DIR__ . '/includes/Services/RateSync.php',
  __DIR__ . '/includes/REST/RatesController.php',
];
foreach ($maybe_files as $mf) {
  if (file_exists($mf)) {
    require_once $mf;
  }
}

/**
 * Old plugin path (for migration tool)
 */
add_filter('mnsnp/migrator/old_plugin', function () {
  $basename = 'mns-woocommerce-rate-based-products/mns-woocommerce-rate-based-products.php';
  return file_exists(WP_PLUGIN_DIR . '/' . $basename) ? $basename : '';
});

/**
 * Global helper: Plugin Singleton
 *
 * @return \MNS\NavasanPlus\Plugin
 */
if (!function_exists('mns_navasan_plus')) {
  function mns_navasan_plus(): \MNS\NavasanPlus\Plugin {
    return \MNS\NavasanPlus\Plugin::instance(MNS_NAVASAN_PLUS_FILE);
  }
}

/**
 * [Optional] Enable "Currency Chart" metabox
 * Default is off. To enable, just uncomment this line.
 */
// add_filter( 'mnsnp/enable_currency_chart_metabox', '__return_true' );

/** Boot plugin after loading other plugins */
add_action('plugins_loaded', static function () {
  mns_navasan_plus()->run();
});
