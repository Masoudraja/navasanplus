<?php
namespace MNS\NavasanPlus\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Settings {

    public function run(): void {
        add_action( 'admin_menu',  [ $this, 'add_menu' ] );
        add_action( 'admin_init',  [ $this, 'register_settings' ] );

        // Actions (manual operations)
        add_action( 'admin_post_mnsnp_test_connection', [ $this, 'handle_test_connection' ] );
        add_action( 'admin_post_mnsnp_sync_rates',      [ $this, 'handle_sync_rates' ] );
        add_action( 'admin_post_mnsnp_regen_token',     [ $this, 'handle_regenerate_token' ] );
    }

    public function add_menu(): void {
    $parent = class_exists('\MNS\NavasanPlus\Admin\Menu') 
        ? \MNS\NavasanPlus\Admin\Menu::SLUG 
        : 'woocommerce'; // فالبک امن

    add_submenu_page(
        $parent,
        __( 'Navasan Plus Settings', 'mns-navasan-plus' ),
        __( 'Settings', 'mns-navasan-plus' ),
        'manage_woocommerce',
        'mns-navasan-plus-settings',
        [ $this, 'settings_page_callback' ]
    );
}

    /** لیست سرویس‌های نرخ (قابل توسعه با فیلتر) */
    private function get_services(): array {
        $services = [
            'tabangohar' => [
                'label' => __( 'Taban Gohar', 'mns-navasan-plus' ),
                'creds' => [ 'username', 'password' ],
            ],
        ];
        return apply_filters( 'mnsnp/rate_services', $services );
    }

    public function register_settings(): void {
        register_setting(
            'mns_navasan_plus_options_group',
            'mns_navasan_plus_options',
            [ $this, 'sanitize_options' ]
        );

        // General
        add_settings_section(
            'mns_navasan_plus_general',
            __( 'General Settings', 'mns-navasan-plus' ),
            function() {
                echo '<p>' . esc_html__( 'Configure the default rate service and caching.', 'mns-navasan-plus' ) . '</p>';
            },
            'mns-navasan-plus-settings'
        );

        add_settings_field(
            'api_service',
            __( 'Default Rate Service', 'mns-navasan-plus' ),
            [ $this, 'api_service_callback' ],
            'mns-navasan-plus-settings',
            'mns_navasan_plus_general'
        );

        add_settings_field(
            'cache_expiration',
            __( 'Cache Expiration (minutes)', 'mns-navasan-plus' ),
            [ $this, 'cache_expiration_callback' ],
            'mns-navasan-plus-settings',
            'mns_navasan_plus_general'
        );

        // Sync
        add_settings_section(
            'mns_navasan_plus_sync',
            __( 'Sync (Schedule)', 'mns-navasan-plus' ),
            function() {
                echo '<p>' . esc_html__( 'Enable periodic sync from the selected service.', 'mns-navasan-plus' ) . '</p>';
            },
            'mns-navasan-plus-settings'
        );

        add_settings_field(
            'sync_enable',
            __( 'Enable Scheduled Sync', 'mns-navasan-plus' ),
            [ $this, 'sync_enable_callback' ],
            'mns-navasan-plus-settings',
            'mns_navasan_plus_sync'
        );

        add_settings_field(
            'sync_interval',
            __( 'Sync Interval (minutes)', 'mns-navasan-plus' ),
            [ $this, 'sync_interval_callback' ],
            'mns-navasan-plus-settings',
            'mns_navasan_plus_sync'
        );

        // Taban Gohar creds
        add_settings_section(
            'mns_navasan_plus_tg',
            __( 'Taban Gohar Credentials', 'mns-navasan-plus' ),
            function () {
                echo '<p>' . esc_html__( 'Enter your Taban Gohar API username & password.', 'mns-navasan-plus' ) . '</p>';
            },
            'mns-navasan-plus-settings'
        );

        add_settings_field(
            'tabangohar_username',
            __( 'Username', 'mns-navasan-plus' ),
            [ $this, 'tg_username_callback' ],
            'mns-navasan-plus-settings',
            'mns_navasan_plus_tg'
        );

        add_settings_field(
            'tabangohar_password',
            __( 'Password', 'mns-navasan-plus' ),
            [ $this, 'tg_password_callback' ],
            'mns-navasan-plus-settings',
            'mns_navasan_plus_tg'
        );

        // API / Push Endpoint (نمایش توکن و آدرس REST)
        add_settings_section(
            'mns_navasan_plus_api',
            __( 'API / Push Endpoint', 'mns-navasan-plus' ),
            [ $this, 'api_section_callback' ],
            'mns-navasan-plus-settings'
        );
    }

    public function sanitize_options( $input ): array {
        $input  = is_array( $input ) ? $input : [];
        $output = [];

        // Service
        $service_keys = array_keys( $this->get_services() );
        $sel = sanitize_text_field( $input['api_service'] ?? 'tabangohar' );
        $output['api_service'] = in_array( $sel, $service_keys, true ) ? $sel : 'tabangohar';

        // Cache
        $min = max( 1, absint( $input['cache_expiration'] ?? 60 ) );
        $output['cache_expiration'] = min( 1440, $min );

        // Sync
        $output['sync_enable']  = ! empty( $input['sync_enable'] ) ? 1 : 0;
        $iv = max( 1, absint( $input['sync_interval'] ?? 10 ) );
        $output['sync_interval'] = min( 1440, $iv );

        // Credentials
        $output['services'] = $input['services'] ?? [];
        $tg_user = isset( $input['services']['tabangohar']['username'] ) ? sanitize_text_field( $input['services']['tabangohar']['username'] ) : '';
        $tg_pass = isset( $input['services']['tabangohar']['password'] ) ? sanitize_text_field( $input['services']['tabangohar']['password'] ) : '';
        $output['services']['tabangohar']['username'] = $tg_user;
        $output['services']['tabangohar']['password'] = $tg_pass;

        // Back-compat keys (اختیاری)
        $output['tabangohar_username'] = $tg_user;
        $output['tabangohar_password'] = $tg_pass;

        return $output;
    }

    // ---- field renderers ----

    public function api_service_callback(): void {
        $opts     = get_option( 'mns_navasan_plus_options', [] );
        $selected = $opts['api_service'] ?? 'tabangohar';
        $services = $this->get_services();

        echo '<select name="mns_navasan_plus_options[api_service]">';
        foreach ( $services as $key => $data ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $key ),
                selected( $selected, $key, false ),
                esc_html( $data['label'] ?? $key )
            );
        }
        echo '</select>';
    }

    public function cache_expiration_callback(): void {
        $opts = get_option( 'mns_navasan_plus_options', [] );
        $val  = (int) ( $opts['cache_expiration'] ?? 60 );
        printf(
            '<input type="number" min="1" max="1440" name="mns_navasan_plus_options[cache_expiration]" value="%d" class="small-text" /> <span class="description">%s</span>',
            (int) $val,
            esc_html__( '1–1440 minutes.', 'mns-navasan-plus' )
        );
    }

    public function sync_enable_callback(): void {
        $opts = get_option( 'mns_navasan_plus_options', [] );
        $on   = ! empty( $opts['sync_enable'] );
        printf(
            '<label><input type="checkbox" name="mns_navasan_plus_options[sync_enable]" value="1" %s /> %s</label>',
            checked( $on, true, false ),
            esc_html__( 'Enable periodic sync via WP-Cron', 'mns-navasan-plus' )
        );
        // نمایش آخرین نتیجه Sync
        $last = get_option( 'mns_navasan_plus_last_sync', [] );
        if ( ! empty( $last ) && ! empty( $last['time'] ) ) {
            $color = ! empty( $last['ok'] ) ? '#0a0' : '#a00';
            printf(
                '<p style="margin:.5em 0 0;color:%s">%s: %s — %s</p>',
                esc_attr( $color ),
                esc_html__( 'Last Sync', 'mns-navasan-plus' ),
                esc_html( date_i18n( 'Y-m-d H:i', (int) $last['time'] ) ),
                esc_html( (string) ( $last['msg'] ?? '' ) )
            );
        }
    }

    public function sync_interval_callback(): void {
        $opts = get_option( 'mns_navasan_plus_options', [] );
        $val  = (int) ( $opts['sync_interval'] ?? 10 );
        printf(
            '<input type="number" min="1" max="1440" name="mns_navasan_plus_options[sync_interval]" value="%d" class="small-text" /> <span class="description">%s</span>',
            (int) $val,
            esc_html__( 'How often to sync (minutes).', 'mns-navasan-plus' )
        );
    }

    public function tg_username_callback(): void {
        $opts = get_option( 'mns_navasan_plus_options', [] );
        $val  = $opts['services']['tabangohar']['username'] ?? ( $opts['tabangohar_username'] ?? '' );
        printf(
            '<input type="text" name="mns_navasan_plus_options[services][tabangohar][username]" value="%s" class="regular-text" autocomplete="username" />',
            esc_attr( $val )
        );
    }

    public function tg_password_callback(): void {
        $opts = get_option( 'mns_navasan_plus_options', [] );
        $val  = $opts['services']['tabangohar']['password'] ?? ( $opts['tabangohar_password'] ?? '' );
        printf(
            '<input type="password" name="mns_navasan_plus_options[services][tabangohar][password]" value="%s" class="regular-text" autocomplete="current-password" />',
            esc_attr( $val )
        );
    }

    /** سکشن API: نمایش Endpoint و توکن + دکمهٔ Regenerate */
    public function api_section_callback(): void {
        $token = self::get_rest_api_main_token();
        $url   = esc_url( rest_url( 'mnsnp/v1/rates' ) );
        $regen = wp_nonce_url( admin_url( 'admin-post.php?action=mnsnp_regen_token' ), 'mnsnp_regen_token' );
        ?>
        <p><?php _e( 'Use this endpoint to push rates into your site (POST JSON).', 'mns-navasan-plus' ); ?></p>
        <p><code><?php echo $url; ?>?token=<?php echo esc_html( $token ); ?></code></p>
        <p><em><?php _e( 'Or send the token via HTTP header: X-API-TOKEN', 'mns-navasan-plus' ); ?></em></p>
        <p>
            <a class="button" href="<?php echo esc_url( $regen ); ?>">
                <?php _e( 'Regenerate Token', 'mns-navasan-plus' ); ?>
            </a>
        </p>
        <?php
    }

    // ---- pages / actions ----

    public function settings_page_callback(): void {
        // پیام‌ها
        if ( isset( $_GET['mnsnp_msg'], $_GET['mnsnp_text'] ) ) {
            $ok = sanitize_key( $_GET['mnsnp_msg'] ) === 'ok';
            printf(
                '<div class="notice %s"><p>%s</p></div>',
                $ok ? 'notice-success' : 'notice-error',
                esc_html( wp_unslash( $_GET['mnsnp_text'] ) )
            );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Navasan Plus Settings', 'mns-navasan-plus' ); ?></h1>

            <form method="post" action="options.php" style="max-width:900px;">
                <?php
                settings_fields( 'mns_navasan_plus_options_group' );
                do_settings_sections( 'mns-navasan-plus-settings' );
                submit_button();
                ?>
            </form>

            <hr/>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
                <?php wp_nonce_field( 'mnsnp_sync_rates', '_mnsnp_nonce' ); ?>
                <input type="hidden" name="action" value="mnsnp_sync_rates" />
                <label>
                    <input type="checkbox" name="create_new" value="1" checked />
                    <?php esc_html_e('Create missing currencies if not found', 'mns-navasan-plus'); ?>
                </label>
                <?php submit_button( __( 'Sync Rates Now', 'mns-navasan-plus' ), 'primary', 'submit', false ); ?>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
                <?php wp_nonce_field( 'mnsnp_test_connection', '_mnsnp_nonce' ); ?>
                <input type="hidden" name="action" value="mnsnp_test_connection" />
                <?php submit_button( __( 'Test Connection (Taban Gohar)', 'mns-navasan-plus' ), 'secondary' ); ?>
            </form>
        </div>
        <?php
    }

    public function handle_test_connection(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Access denied.', 'mns-navasan-plus' ) );
        }
        check_admin_referer( 'mnsnp_test_connection', '_mnsnp_nonce' );

        $ok = false; $msg = '';
        $opts  = get_option( 'mns_navasan_plus_options', [] );
        $svc   = $opts['api_service'] ?? 'tabangohar';

        try {
            if ( $svc === 'tabangohar' && class_exists( '\MNS\NavasanPlus\Webservices\Rates\TabanGohar' ) ) {
                $tg  = new \MNS\NavasanPlus\Webservices\Rates\TabanGohar();
                $res = $tg->retrieve();
                if ( is_wp_error( $res ) ) {
                    $msg = $res->get_error_message();
                } else {
                    $ok  = is_array( $res ) && ! empty( $res );
                    $msg = $ok ? __( 'Connection successful.', 'mns-navasan-plus' ) : __( 'Empty response received.', 'mns-navasan-plus' );
                }
            } else {
                $msg = __( 'Service class not available.', 'mns-navasan-plus' );
            }
        } catch ( \Throwable $e ) {
            $msg = $e->getMessage();
        }

        $redir = add_query_arg( [
            'page'        => 'mns-navasan-plus-settings',
            'mnsnp_msg'   => $ok ? 'ok' : 'fail',
            'mnsnp_text'  => rawurlencode( $msg ),
        ], admin_url( 'admin.php' ) );

        wp_safe_redirect( $redir );
        exit;
    }

    public function handle_sync_rates(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Access denied.', 'mns-navasan-plus' ) );
        }
        check_admin_referer( 'mnsnp_sync_rates', '_mnsnp_nonce' );

        $create_new = ! empty( $_POST['create_new'] );

        if ( ! class_exists( '\MNS\NavasanPlus\Services\RateSync' ) ) {
            require_once dirname( __DIR__ ) . '/Services/RateSync.php';
        }

        $report = \MNS\NavasanPlus\Services\RateSync::sync( [
            'create_new'  => $create_new,
            'history_max' => 200,
        ] );

        $msg = $report['ok']
            ? sprintf( __( 'Synced. Updated: %1$d, Created: %2$d, Skipped: %3$d', 'mns-navasan-plus' ),
                (int) ($report['updated'] ?? 0),
                (int) ($report['created'] ?? 0),
                (int) ($report['skipped'] ?? 0)
            )
            : ( $report['error'] ?? __( 'Sync failed.', 'mns-navasan-plus' ) );

        $redir = add_query_arg( [
            'page'       => 'mns-navasan-plus-settings',
            'mnsnp_msg'  => $report['ok'] ? 'ok' : 'fail',
            'mnsnp_text' => rawurlencode( $msg ),
        ], admin_url( 'admin.php' ) );

        wp_safe_redirect( $redir );
        exit;
    }

    public function handle_regenerate_token(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Access denied.', 'mns-navasan-plus' ) );
        }
        check_admin_referer( 'mnsnp_regen_token' );
        self::regenerate_rest_api_main_token();
        $redir = add_query_arg( [
            'page'       => 'mns-navasan-plus-settings',
            'mnsnp_msg'  => 'ok',
            'mnsnp_text' => rawurlencode( __( 'API token regenerated.', 'mns-navasan-plus' ) ),
        ], admin_url( 'admin.php' ) );
        wp_safe_redirect( $redir );
        exit;
    }

    // ---------------------------
    // Token helpers (static)
    // ---------------------------

    public static function get_rest_api_main_token(): string {
        $key = 'mns_navasan_plus_rest_api_main_token';
        $tok = get_option( $key, '' );
        if ( empty( $tok ) ) {
            $tok = wp_generate_password( 32, false, false );
            add_option( $key, $tok, '', 'no' );
        }
        return $tok;
    }

    public static function regenerate_rest_api_main_token(): string {
        $key = 'mns_navasan_plus_rest_api_main_token';
        $tok = wp_generate_password( 32, false, false );
        update_option( $key, $tok );
        return $tok;
    }
}