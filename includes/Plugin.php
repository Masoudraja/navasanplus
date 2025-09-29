<?php
namespace MNS\NavasanPlus;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Plugin Core (Singleton)
 * - Holder of Loader
 * - Helper access to version/path/data
 * - Provides product wrapper: get_product( WC_Product )
 */
final class Plugin {

    /** @var Plugin|null */
    private static ?Plugin $instance = null;

    /** @var Loader */
    private Loader $loader;

    /** @var string Plugin version for cache busting scripts/styles */
    private string $version = '1.0.1';

    /** @var string Main plugin file path */
    private string $plugin_file;

    /** @var array Cache for "plugin info" from header */
    private array $plugin_data_cache = [];

    /**
     * @param string $plugin_file Main plugin file path
     */
    private function __construct( string $plugin_file ) {
        $this->plugin_file = $plugin_file;
        $this->loader      = new Loader();
    }

    /** Prevent creating second instance */
    private function __clone() {}
    /** Prevent unserialize */
    private function __wakeup() {}

    /**
     * Get Singleton instance
     * @param string $plugin_file
     */
    public static function instance( string $plugin_file = '' ): self {
        if ( null === self::$instance ) {
            if ( $plugin_file === '' && defined( 'MNS_NAVASAN_PLUS_FILE' ) ) {
                $plugin_file = MNS_NAVASAN_PLUS_FILE;
            }
            self::$instance = new self( $plugin_file ?: __FILE__ );
        }
        return self::$instance;
    }

    /** Complete plugin boot (Loader → init) */
    public function run(): void {
        $this->loader->init();
    }

    /** Access to Loader */
    public function loader(): Loader {
        return $this->loader;
    }

    /** Plugin version (filterable) */
    public function version(): string {
        return apply_filters( 'mnsnp/version', $this->version, $this );
    }

    /**
     * Appropriate asset version:
     * - In dev mode (SCRIPT_DEBUG=true) uses time() for cache bust
     * - In prod mode uses plugin version
     * - Filterable
     */
    public function assets_version(): string {
        $v = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? (string) time() : $this->version();
        return apply_filters( 'mnsnp/assets_version', $v, $this );
    }

    /** Main plugin file path */
    public function plugin_file(): string {
        return $this->plugin_file;
    }

    /** Plugin basename */
    public function plugin_basename(): string {
        return plugin_basename( $this->plugin_file );
    }

    /** Plugin directory path */
    public function plugin_dir_path(): string {
        return plugin_dir_path( $this->plugin_file );
    }

    /** Plugin directory URL */
    public function plugin_dir_url(): string {
        return plugin_dir_url( $this->plugin_file );
    }

    /** Plugin header info (Name, Version, Author, PluginURI, …) */
    public function get_plugin_data(): array {
        if ( empty( $this->plugin_data_cache ) ) {
            if ( ! function_exists( 'get_plugin_data' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $this->plugin_data_cache = get_plugin_data( $this->plugin_file, false, false );
        }
        return $this->plugin_data_cache;
    }

    /** Plugin name for display (filterable) */
    public function get_plugin_name(): string {
        $data  = $this->get_plugin_data();
        $name  = $data['Name'] ?? 'MNS Navasan Plus';
        return apply_filters( 'mnsnp/plugin_name', $name, $this );
    }

    /** Quick access to DB wrapper */
    public function db(): DB {
        return DB::instance();
    }

    /** Is WooCommerce active? */
    public function is_wc_active(): bool {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Convert WC_Product to "Navasan Plus Product" wrapper
     *
     * @param \WC_Product $wc_product
     * @return \MNS\NavasanPlus\PublicNS\Product
     */
    public function get_product( \WC_Product $wc_product ): \MNS\NavasanPlus\PublicNS\Product {
        return new \MNS\NavasanPlus\PublicNS\Product( $wc_product );
    }
}