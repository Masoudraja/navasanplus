<?php
namespace MNS\NavasanPlus;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * هستهٔ افزونه (Singleton)
 * - نگهدارندهٔ Loader
 * - دسترسی کمکی به نسخه/مسیر/دیتا
 * - ارائه‌ی wrapper محصول: get_product( WC_Product )
 */
final class Plugin {

    /** @var Plugin|null */
    private static ?Plugin $instance = null;

    /** @var Loader */
    private Loader $loader;

    /** @var string نسخهٔ افزونه برای کش‌شکنی اسکریپت‌ها/استایل‌ها */
    private string $version = '1.0.1';

    /** @var string مسیر فایل اصلی افزونه */
    private string $plugin_file;

    /** @var array کش «اطلاعات افزونه» از هدر */
    private array $plugin_data_cache = [];

    /**
     * @param string $plugin_file مسیر فایل اصلی افزونه
     */
    private function __construct( string $plugin_file ) {
        $this->plugin_file = $plugin_file;
        $this->loader      = new Loader();
    }

    /** جلوگیری از ساخت نمونهٔ دوم */
    private function __clone() {}
    /** جلوگیری از unserialize */
    private function __wakeup() {}

    /**
     * گرفتن نمونهٔ Singleton
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

    /** بوت کامل افزونه (Loader → init) */
    public function run(): void {
        $this->loader->init();
    }

    /** دسترسی به Loader */
    public function loader(): Loader {
        return $this->loader;
    }

    /** نسخهٔ افزونه (قابل فیلتر) */
    public function version(): string {
        return apply_filters( 'mnsnp/version', $this->version, $this );
    }

    /**
     * نسخهٔ مناسب asset:
     * - در حالت dev (SCRIPT_DEBUG=true) از time() برای bust cache
     * - در حالت prod از نسخهٔ افزونه
     * - قابل فیلتر
     */
    public function assets_version(): string {
        $v = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? (string) time() : $this->version();
        return apply_filters( 'mnsnp/assets_version', $v, $this );
    }

    /** مسیر فایل اصلی افزونه */
    public function plugin_file(): string {
        return $this->plugin_file;
    }

    /** basename افزونه */
    public function plugin_basename(): string {
        return plugin_basename( $this->plugin_file );
    }

    /** مسیر پوشهٔ افزونه */
    public function plugin_dir_path(): string {
        return plugin_dir_path( $this->plugin_file );
    }

    /** URL پوشهٔ افزونه */
    public function plugin_dir_url(): string {
        return plugin_dir_url( $this->plugin_file );
    }

    /** اطلاعات هدر افزونه (Name, Version, Author, PluginURI, …) */
    public function get_plugin_data(): array {
        if ( empty( $this->plugin_data_cache ) ) {
            if ( ! function_exists( 'get_plugin_data' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $this->plugin_data_cache = get_plugin_data( $this->plugin_file, false, false );
        }
        return $this->plugin_data_cache;
    }

    /** نام افزونه برای نمایش (قابل فیلتر) */
    public function get_plugin_name(): string {
        $data  = $this->get_plugin_data();
        $name  = $data['Name'] ?? 'MNS Navasan Plus';
        return apply_filters( 'mnsnp/plugin_name', $name, $this );
    }

    /** دسترسی سریع به DB wrapper */
    public function db(): DB {
        return DB::instance();
    }

    /** آیا ووکامرس فعاله؟ */
    public function is_wc_active(): bool {
        return class_exists( 'WooCommerce' );
    }

    /**
     * تبدیل WC_Product به wrapper «محصول نوسان پلاس»
     *
     * @param \WC_Product $wc_product
     * @return \MNS\NavasanPlus\PublicNS\Product
     */
    public function get_product( \WC_Product $wc_product ): \MNS\NavasanPlus\PublicNS\Product {
        return new \MNS\NavasanPlus\PublicNS\Product( $wc_product );
    }
}