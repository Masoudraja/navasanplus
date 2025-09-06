<?php
namespace MNS\NavasanPlus\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\DB;

final class HealthCheckPage {

    public function run(): void {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'maybe_run_tests' ] );
    }

    public function add_menu(): void {
        add_submenu_page(
            'woocommerce',
            __( 'Navasan Plus Health Check', 'mns-navasan-plus' ),
            __( 'Navasan Plus ▸ Health', 'mns-navasan-plus' ),
            'manage_woocommerce',
            'mnsnp-health',
            [ $this, 'render_page' ]
        );
    }

    public function render_page(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Navasan Plus Health Check', 'mns-navasan-plus' ); ?></h1>
            <p><?php esc_html_e( 'Run automated checks to verify the plugin is wired correctly.', 'mns-navasan-plus' ); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field( 'mnsnp_health', '_mnsnp_health' ); ?>
                <p><button class="button button-primary"><?php esc_html_e( 'Run Checks', 'mns-navasan-plus' ); ?></button></p>
            </form>

            <?php if ( isset( $_GET['mnsnp_health'] ) && $_GET['mnsnp_health'] === 'done' ) : ?>
                <?php $report = get_transient( 'mnsnp_health_report' ); delete_transient( 'mnsnp_health_report' ); ?>
                <?php if ( is_array( $report ) ) : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Check', 'mns-navasan-plus' ); ?></th>
                                <th><?php esc_html_e( 'Result', 'mns-navasan-plus' ); ?></th>
                                <th><?php esc_html_e( 'Details', 'mns-navasan-plus' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $report as $row ): ?>
                            <tr>
                                <td><strong><?php echo esc_html( $row['label'] ); ?></strong></td>
                                <td>
                                    <?php if ( ! empty( $row['ok'] ) ) : ?>
                                        <span style="color:#1a7f37;font-weight:600">PASS</span>
                                    <?php else: ?>
                                        <span style="color:#b00020;font-weight:600">FAIL</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo wp_kses_post( $row['msg'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public function maybe_run_tests(): void {
        if ( ! is_admin() ) return;
        if ( ! isset( $_POST['_mnsnp_health'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['_mnsnp_health'], 'mnsnp_health' ) ) return;
        if ( ! current_user_can( 'manage_woocommerce' ) ) return;

        $report = $this->run_tests();
        set_transient( 'mnsnp_health_report', $report, 60 );
        wp_safe_redirect( add_query_arg( 'mnsnp_health', 'done', menu_page_url( 'mnsnp-health', false ) ) );
        exit;
    }

    private function run_tests(): array {
        $rows = [];

        // Helper: plugin root path
        $root = dirname( __DIR__, 1 ) . '/../';

        // 0) WooCommerce active
        $wc_ok = class_exists( '\WooCommerce' ) || function_exists( 'WC' );
        $rows[] = $this->row(
            'WooCommerce',
            $wc_ok,
            $wc_ok ? 'WooCommerce is active.' : 'WooCommerce not detected.'
        );

        // 1) DB prefix consistency
        $rows[] = $this->row(
            'DB Prefix',
            ( defined('MNS_NAVASAN_PLUS_DB_PREFIX') && DB::instance()->prefix() === MNS_NAVASAN_PLUS_DB_PREFIX ),
            sprintf(
                'Prefix = <code>%s</code>, full meta example: <code>%s</code>',
                esc_html( DB::instance()->prefix() ),
                esc_html( DB::instance()->full_meta_key( 'active' ) )
            )
        );

        // 2) CPTs exist
        $cpts    = [ 'mnsnp_currency', 'mnsnp_formula', 'mnsnp_chart' ];
        $missing = array_filter( $cpts, fn($pt)=> ! post_type_exists( $pt ) );
        $rows[]  = $this->row(
            'Custom Post Types',
            empty( $missing ),
            empty($missing) ? 'All present' : ( 'Missing: ' . esc_html( implode(', ', $missing) ) )
        );

        // 3) Classes exist
        $class_checks = [
            '\\MNS\\NavasanPlus\\Admin\\MetaBoxes',
            '\\MNS\\NavasanPlus\\Admin\\Settings',
            '\\MNS\\NavasanPlus\\Admin\\WooCommerce',
            '\\MNS\\NavasanPlus\\Services\\PriceCalculator',
            '\\MNS\\NavasanPlus\\Services\\FormulaEngine',
            '\\MNS\\NavasanPlus\\Webservices\\Rates\\TabanGohar',
        ];
        $missing = array_filter( $class_checks, fn($c)=> ! class_exists($c) );
        $rows[] = $this->row(
            'Key Classes Loaded',
            empty( $missing ),
            empty($missing) ? 'OK' : ( 'Missing: <code>'. esc_html( implode('</code>, <code>', $missing) ) .'</code>' )
        );

        // 4) Templates exist
        $tpls = [
            'templates/metaboxes/product.php',
            'templates/metaboxes/product-formula.php',
            'templates/metaboxes/formula.php',
            'templates/metaboxes/formula-components.php',
            'templates/metaboxes/currency.php',
            'templates/metaboxes/currency-chart.php',
        ];
        $missing = array_filter( $tpls, fn($p)=> ! file_exists( $root . $p ) );
        $rows[] = $this->row(
            'Templates',
            empty( $missing ),
            empty($missing) ? 'OK' : ( 'Missing: ' . esc_html( implode(', ', $missing) ) )
        );

        // 5) Assets files exist (more پایدار از چکِ registered بودن)
        $assets = [
            // CSS
            [ 'assets/css/admin.min.css',   'assets/css/admin.css'   ],
            [ 'assets/css/public.min.css',  'assets/css/public.css'  ],
            // JS
            [ 'assets/js/admin.min.js',     'assets/js/admin.js'     ],
            [ 'assets/js/public.min.js',    'assets/js/public.js'    ],
            [ 'assets/js/common.min.js',    'assets/js/common.js'    ],
            [ 'assets/js/persist.min.js',   'assets/js/persist.js'   ],
            [ 'assets/js/formula-parser.min.js', 'assets/js/formula-parser.js' ],
        ];
        $missing_assets = [];
        foreach ( $assets as $pair ) {
            [$min,$plain] = $pair;
            if ( ! file_exists( $root . $min ) && ! file_exists( $root . $plain ) ) {
                $missing_assets[] = $min . ' | ' . $plain;
            }
        }
        $rows[] = $this->row(
            'Assets presence',
            empty( $missing_assets ),
            empty( $missing_assets ) ? 'All present' : ( 'Missing: <code>' . esc_html( implode('</code>, <code>', $missing_assets) ) . '</code>' )
        );

        // 6) Migrator presence (optional)
        $migrator_ok = class_exists('\\MNS\\NavasanPlus\\Tools\\Migrator');
        $rows[] = $this->row(
            'Migrator Class',
            $migrator_ok,
            $migrator_ok ? 'OK' : 'Optional but recommended'
        );

        // 7) WC_Order macro
        $has_macro = method_exists('WC_Order','get_currency_rate');
        $rows[] = $this->row(
            'WC_Order::get_currency_rate',
            $has_macro,
            $has_macro ? 'OK' : 'Not set yet (macro registers on init).'
        );

        // 8) Options sanity
        $opts = get_option( 'mns_navasan_plus_options', [] );
        $rows[] = $this->row(
            'Options (mns_navasan_plus_options)',
            is_array( $opts ),
            'Current: <code>' . esc_html( wp_json_encode( $opts ) ) . '</code>'
        );

        // 9) TabanGohar credentials (از ساختار جدید options با fallback)
        $tg_user = $opts['services']['tabangohar']['username'] ?? ( $opts['tabangohar_username'] ?? '' );
        $tg_pass = $opts['services']['tabangohar']['password'] ?? ( $opts['tabangohar_password'] ?? '' );
        $rows[] = $this->row(
            'TabanGohar credentials',
            ( $tg_user !== '' && $tg_pass !== '' ),
            $tg_user !== '' ? 'Username set' : 'Username empty'
        );

        // 10) REST route & token
        $routes = function_exists('rest_get_server') ? rest_get_server()->get_routes() : [];
        $route_ok = is_array($routes) && array_key_exists('/mnsnp/v1/rates', $routes);
        $token = '';
        if ( class_exists('\\MNS\\NavasanPlus\\Admin\\Options') && method_exists('\\MNS\\NavasanPlus\\Admin\\Options','get_rest_api_main_token') ) {
            $token = (string) \MNS\NavasanPlus\Admin\Options::get_rest_api_main_token();
        } else {
            $token = (string) DB::instance()->read_option( 'rest_api_main_token', '' );
        }
        $rows[] = $this->row(
            'REST route & token',
            ( $route_ok && $token !== '' ),
            ( $route_ok ? 'Route OK; ' : 'Route MISSING; ' ) . ( $token !== '' ? 'Token set.' : 'Token missing.' )
        );

        // 11) Cron schedule (اگر sync روشنه)
        $sync_on = ! empty( $opts['sync_enable'] );
        $next_ts = $sync_on ? wp_next_scheduled( 'mnsnp_rate_sync' ) : false;
        $rows[] = $this->row(
            'WP-Cron (Rate Sync)',
            ( ! $sync_on || $next_ts ),
            $sync_on
                ? ( $next_ts ? 'Next run: ' . esc_html( date_i18n( 'Y-m-d H:i', (int) $next_ts ) ) : 'Enabled but not scheduled.' )
                : 'Disabled'
        );

        return $rows;
    }

    private function row( string $label, bool $ok, string $msg ): array {
        return [ 'label' => $label, 'ok' => $ok, 'msg' => $msg ];
    }
}