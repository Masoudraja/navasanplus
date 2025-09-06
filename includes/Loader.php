<?php
/**
 * Loader
 *
 * Wires up all hooks, registers assets, and boots admin/public modules.
 *
 * File: includes/Loader.php
 */

namespace MNS\NavasanPlus;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Loader {

    /** @var string */
    private string $version;

    public function __construct() {
        // اگر ثابت نسخه تعریف نشده بود، fallback
        $this->version = defined( 'MNS_NAVASAN_PLUS_VER' ) ? MNS_NAVASAN_PLUS_VER : '1.0.1';
    }

    /** Bootstrap everything */
    public function init(): void {

        // Stable meta/option prefix
        if ( ! defined( 'MNS_NAVASAN_PLUS_DB_PREFIX' ) ) {
            define( 'MNS_NAVASAN_PLUS_DB_PREFIX', 'mns_navasan_plus' );
        }

        // I18n
        add_action( 'init', [ $this, 'load_textdomain' ] );

        // Register (not enqueue) assets so templates/classes can enqueue by handle
        add_action( 'wp_enqueue_scripts',    [ $this, 'register_public_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'register_admin_assets' ] );

        // Boot
        $this->boot_common();
        $this->boot_admin_or_public();
    }

    /** Load translations */
    public function load_textdomain(): void {
        $rel = dirname( plugin_basename( __FILE__ ), 2 ) . '/languages';
        load_plugin_textdomain( 'mns-navasan-plus', false, $rel );
    }

    /** Small helper: per-file version for dev (filemtime) or plugin version */
    private function ver_for( string $abs_path ): string {
        if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( $abs_path ) ) {
            $mt = @filemtime( $abs_path );
            if ( $mt ) {
                return (string) $mt;
            }
        }
        return $this->version;
    }

    /** Register public-side assets (do not enqueue here) */
    public function register_public_assets(): void {
        $plugin_dir = dirname( __DIR__ ); // .../mns-navasan-plus
        $use_min    = ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );

        $public_css_rel = $use_min && file_exists( $plugin_dir . '/assets/css/public.min.css' )
            ? 'assets/css/public.min.css'
            : 'assets/css/public.css';
        wp_register_style(
            'mns-navasan-plus-public',
            Helpers::plugin_url( $public_css_rel ),
            [],
            $this->ver_for( $plugin_dir . '/' . $public_css_rel )
        );

        $parser_rel = $use_min && file_exists( $plugin_dir . '/assets/js/formula-parser.min.js' )
            ? 'assets/js/formula-parser.min.js'
            : 'assets/js/formula-parser.js';
        wp_register_script(
            'mns-navasan-plus-formula-parser',
            Helpers::plugin_url( $parser_rel ),
            [],
            $this->ver_for( $plugin_dir . '/' . $parser_rel ),
            true
        );

        $common_rel = $use_min && file_exists( $plugin_dir . '/assets/js/common.min.js' )
            ? 'assets/js/common.min.js'
            : 'assets/js/common.js';
        wp_register_script(
            'mns-navasan-plus-common',
            Helpers::plugin_url( $common_rel ),
            [ 'jquery' ],
            $this->ver_for( $plugin_dir . '/' . $common_rel ),
            true
        );

        $public_rel = $use_min && file_exists( $plugin_dir . '/assets/js/public.min.js' )
            ? 'assets/js/public.min.js'
            : 'assets/js/public.js';
        wp_register_script(
            'mns-navasan-plus-public',
            Helpers::plugin_url( $public_rel ),
            [ 'jquery', 'mns-navasan-plus-formula-parser' ],
            $this->ver_for( $plugin_dir . '/' . $public_rel ),
            true
        );
    }

    /** Register admin-side assets (do not enqueue here) */
    public function register_admin_assets(): void {
        $plugin_dir = dirname( __DIR__ );
        $use_min    = ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );

        $admin_css_rel = $use_min && file_exists( $plugin_dir . '/assets/css/admin.min.css' )
            ? 'assets/css/admin.min.css'
            : 'assets/css/admin.css';
        wp_register_style(
            'mns-navasan-plus-admin',
            Helpers::plugin_url( $admin_css_rel ),
            [],
            $this->ver_for( $plugin_dir . '/' . $admin_css_rel )
        );

        if ( ! wp_script_is( 'chartjs', 'registered' ) && ! wp_script_is( 'chartjs', 'enqueued' ) ) {
            wp_register_script('mnsnp-chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true );
            $chart_handle = 'mnsnp-chartjs';
        } else {
            $chart_handle = 'chartjs';
        }

        $persist_rel = $use_min && file_exists( $plugin_dir . '/assets/js/persist.min.js' )
            ? 'assets/js/persist.min.js' : 'assets/js/persist.js';
        wp_register_script('mns-navasan-plus-persist', Helpers::plugin_url( $persist_rel ), [ 'jquery' ], $this->ver_for( $plugin_dir . '/' . $persist_rel ), true);

        $common_rel = $use_min && file_exists( $plugin_dir . '/assets/js/common.min.js' )
            ? 'assets/js/common.min.js' : 'assets/js/common.js';
        wp_register_script('mns-navasan-plus-common', Helpers::plugin_url( $common_rel ), [ 'jquery' ], $this->ver_for( $plugin_dir . '/' . $common_rel ), true);

        $admin_rel = $use_min && file_exists( $plugin_dir . '/assets/js/admin.min.js' )
            ? 'assets/js/admin.min.js' : 'assets/js/admin.js';
        wp_register_script('mns-navasan-plus-admin', Helpers::plugin_url( $admin_rel ), [ 'jquery', $chart_handle ], $this->ver_for( $plugin_dir . '/' . $admin_rel ), true);

        $parser_rel = $use_min && file_exists( $plugin_dir . '/assets/js/formula-parser.min.js' )
            ? 'assets/js/formula-parser.min.js' : 'assets/js/formula-parser.js';
        wp_register_script('mns-navasan-plus-formula-parser', Helpers::plugin_url( $parser_rel ), [], $this->ver_for( $plugin_dir . '/' . $parser_rel ), true);
    }

    /** Boot modules shared by admin/public */
    private function boot_common(): void {
        if ( class_exists( __NAMESPACE__ . '\\DB' ) ) {
            DB::instance();
        }

        $rate_sync_file = __DIR__ . '/Services/RateSync.php';
        if ( ! class_exists( __NAMESPACE__ . '\\Services\\RateSync' ) && file_exists( $rate_sync_file ) ) {
            require_once $rate_sync_file;
        }
        if ( class_exists( __NAMESPACE__ . '\\Services\\RateSync' ) ) {
            \MNS\NavasanPlus\Services\RateSync::boot();
        }

	$updater_file = __DIR__ . '/Services/AutoPriceUpdater.php';
	if ( ! class_exists( __NAMESPACE__ . '\\Services\\AutoPriceUpdater' ) && file_exists( $updater_file ) ) {
    	require_once $updater_file;
	}
	if ( class_exists( __NAMESPACE__ . '\\Services\\AutoPriceUpdater' ) ) {
    	( new \MNS\NavasanPlus\Services\AutoPriceUpdater() )->run();
	}

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            $tools = __DIR__ . '/Tools/Migrator.php';
            $cmd   = __DIR__ . '/CLI/MigratorCommand.php';
            if ( file_exists( $tools ) ) require_once $tools;
            if ( file_exists( $cmd ) )   require_once $cmd;

            // Load the new Recalculate command for WP-CLI
            $recalc_cmd = __DIR__ . '/CLI/RecalculateCommand.php';
            if ( file_exists( $recalc_cmd ) ) {
                require_once $recalc_cmd;
            }
        }

        $rest_rates_file = __DIR__ . '/REST/RatesController.php';
        if ( ! class_exists( __NAMESPACE__ . '\\REST\\RatesController' ) && file_exists( $rest_rates_file ) ) {
            require_once $rest_rates_file;
        }
        if ( class_exists( __NAMESPACE__ . '\\REST\\RatesController' ) ) {
            ( new \MNS\NavasanPlus\REST\RatesController() )->boot();
        }
    }

    /** Boot admin or public stacks */
    private function boot_admin_or_public(): void {
        if ( is_admin() ) {
            $this->boot_admin();
        } else {
            $this->boot_public();
        }
    }

    /** Admin stack */
    private function boot_admin(): void {
        $post_types_file  = __DIR__ . '/Admin/PostTypes.php';
        $metaboxes_file   = __DIR__ . '/Admin/MetaBoxes.php';
        $settings_file    = __DIR__ . '/Admin/Settings.php';
        $wc_file          = __DIR__ . '/Admin/WooCommerce.php';
        $ppm_file         = __DIR__ . '/Admin/PricePreviewMetaBox.php';
        $migrator_file    = __DIR__ . '/Admin/MigratorPage.php';
        $health_file      = __DIR__ . '/Admin/HealthCheckPage.php';
        $discount_file   = __DIR__ . '/Admin/DiscountMetaBoxes.php';
        $recalc_file      = __DIR__ . '/Admin/RecalculatePage.php'; // Define path for new page

        if ( class_exists( __NAMESPACE__ . '\\Admin\\Menu' ) ) {
            ( new \MNS\NavasanPlus\Admin\Menu() )->run();
        }
        if ( ! class_exists( __NAMESPACE__ . '\\Admin\\PostTypes' ) && file_exists( $post_types_file ) ) require_once $post_types_file;
        if ( ! class_exists( __NAMESPACE__ . '\\Admin\\MetaBoxes' ) && file_exists( $metaboxes_file ) )  require_once $metaboxes_file;
        if ( ! class_exists( __NAMESPACE__ . '\\Admin\\Settings' ) && file_exists( $settings_file ) )    require_once $settings_file;
        if ( ! class_exists( __NAMESPACE__ . '\\Admin\\WooCommerce' ) && file_exists( $wc_file ) )       require_once $wc_file;
        if ( ! class_exists( __NAMESPACE__ . '\\Admin\\PricePreviewMetaBox' ) && file_exists( $ppm_file ) ) require_once $ppm_file;
        if ( ! class_exists( __NAMESPACE__ . '\\Admin\\DiscountMetaBoxes' ) && file_exists( $discount_file ) ) require_once $discount_file;

        if ( class_exists( __NAMESPACE__ . '\\Admin\\AssignFormulaPage' ) ) {
            ( new \MNS\NavasanPlus\Admin\AssignFormulaPage() )->run();
        }

        // Load and run the new Recalculate admin page
        if ( ! class_exists( __NAMESPACE__ . '\\Admin\\RecalculatePage' ) && file_exists( $recalc_file ) ) {
            require_once $recalc_file;
        }
        if ( class_exists( __NAMESPACE__ . '\\Admin\\RecalculatePage' ) ) {
            ( new \MNS\NavasanPlus\Admin\RecalculatePage() )->run();
        }

        if ( class_exists( __NAMESPACE__ . '\\Admin\\PostTypes' ) ) {
            ( new \MNS\NavasanPlus\Admin\PostTypes() )->run();
        }
        if ( class_exists( __NAMESPACE__ . '\\Admin\\MetaBoxes' ) ) {
            ( new \MNS\NavasanPlus\Admin\MetaBoxes() )->run();
        }
        if ( class_exists( __NAMESPACE__ . '\\Admin\\PricePreviewMetaBox' ) ) {
            ( new \MNS\NavasanPlus\Admin\PricePreviewMetaBox() )->run();
        }
        if ( class_exists( __NAMESPACE__ . '\\Admin\\DiscountMetaBoxes' ) ) {
            ( new \MNS\NavasanPlus\Admin\DiscountMetaBoxes() )->run();
        }
        if ( class_exists( __NAMESPACE__ . '\\Admin\\Settings' ) ) {
            ( new \MNS\NavasanPlus\Admin\Settings() )->run();
        }
        if ( class_exists( __NAMESPACE__ . '\\Admin\\WooCommerce' ) ) {
            ( new \MNS\NavasanPlus\Admin\WooCommerce() )->run();
        }

        add_action( 'admin_enqueue_scripts', function () {
            $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
            $post_types = [ 'mnsnp_currency', 'mnsnp_formula', 'mnsnp_chart', 'product' ];

            $is_plugin_page = false;
            if ( isset( $_GET['page'] ) ) {
                $slug = sanitize_key( wp_unslash( $_GET['page'] ) );
                if ( in_array( $slug, [ 'mns-navasan-plus-settings', 'mnsnp-migrate', 'mnsnp-recalculate-prices' ], true ) ) {
                    $is_plugin_page = true;
                }
            }

            $is_ours =
                $is_plugin_page ||
                ( $screen && in_array( $screen->post_type ?? '', $post_types, true ) ) ||
                ( $screen && strpos( (string) $screen->id, 'woocommerce_page_mns-navasan-plus-settings' ) !== false );

            if ( ! $is_ours ) {
                return;
            }
            if ( $screen && $screen->post_type === 'mnsnp_formula' ) {
                wp_enqueue_script( 'mns-navasan-plus-formula-parser' );
            }

            wp_enqueue_style( 'mns-navasan-plus-admin' );
            wp_enqueue_script( 'mns-navasan-plus-persist' );
            wp_enqueue_script( 'mns-navasan-plus-common' );
            wp_enqueue_script( 'mns-navasan-plus-admin' );
        }, 20 );

        if ( ! class_exists( '\MNS\NavasanPlus\Admin\MigratorPage' ) && file_exists( $migrator_file ) ) {
            require_once $migrator_file;
        }
        if ( class_exists( '\MNS\NavasanPlus\Admin\MigratorPage' ) ) {
            ( new \MNS\NavasanPlus\Admin\MigratorPage() )->run();
        }

        if ( ! class_exists( '\MNS\NavasanPlus\Admin\HealthCheckPage' ) && file_exists( $health_file ) ) {
            require_once $health_file;
        }
        if ( class_exists( '\MNS\NavasanPlus\Admin\HealthCheckPage' ) ) {
            ( new \MNS\NavasanPlus\Admin\HealthCheckPage() )->run();
        }
    }

    /** Public stack */
    private function boot_public(): void {
        $public_core_file = __DIR__ . '/PublicNS/Core.php';
        if ( ! class_exists( __NAMESPACE__ . '\\PublicNS\\Core' ) && file_exists( $public_core_file ) ) {
            require_once $public_core_file;
        }
        if ( class_exists( __NAMESPACE__ . '\\PublicNS\\Core' ) ) {
            \MNS\NavasanPlus\PublicNS\Core::instance()->run();
        }
    }
}