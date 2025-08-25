<?php
namespace MNS\NavasanPlus\Admin;

use MNS\NavasanPlus\Tools\Migrator;

if ( ! defined( 'ABSPATH' ) ) exit;

final class MigratorPage {

    private const TRANSIENT_KEY = 'mnsnp_migration_report';
    private const SLUG          = 'mnsnp-migrate';

    public function run(): void {
        add_action( 'admin_menu', [ $this, 'menu' ] );
    }

    public function menu(): void {
        add_management_page(
            __( 'Migrate from Navasan', 'mns-navasan-plus' ),
            __( 'Navasan → Plus Migration', 'mns-navasan-plus' ),
            'manage_options',
            self::SLUG,
            [ $this, 'render' ]
        );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission.', 'mns-navasan-plus' ) );
        }

        // Fallback autoload اگر Composer فعال نباشد
        if ( ! class_exists( Migrator::class ) ) {
            $migrator_file = dirname( __DIR__ ) . '/Tools/Migrator.php';
            if ( file_exists( $migrator_file ) ) {
                require_once $migrator_file;
            }
        }

        // POST → PRG
        if ( isset( $_POST['mnsnp_migrate_action'] ) && check_admin_referer( 'mnsnp_migrate' ) ) {
            $action_raw      = sanitize_text_field( wp_unslash( $_POST['mnsnp_migrate_action'] ) );
            $action          = in_array( $action_raw, [ 'dry', 'run' ], true ) ? $action_raw : 'dry';
            $deactivate_old  = ! empty( $_POST['mnsnp_deactivate_old'] );
            $delete_old_opts = ! empty( $_POST['mnsnp_delete_old_opts'] );

            $report = [];
            $error  = '';

            try {
                if ( ! class_exists( Migrator::class ) ) {
                    throw new \RuntimeException( __( 'Migrator class not available.', 'mns-navasan-plus' ) );
                }
                $report = Migrator::run( [
                    'dry'             => ( $action === 'dry' ),
                    'deactivate_old'  => $deactivate_old,
                    'delete_old_opts' => $delete_old_opts,
                ] );
            } catch ( \Throwable $e ) {
                $error = $e->getMessage();
                $report = [ 'errors' => [ $error ] ];
            }

            set_transient( self::TRANSIENT_KEY, $report, 5 * MINUTE_IN_SECONDS );

            wp_safe_redirect( add_query_arg( 'mnsnp_done', $error ? 'fail' : 'ok', admin_url( 'tools.php?page=' . self::SLUG ) ) );
            exit;
        }

        // GET view
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Migration from Navasan', 'mns-navasan-plus' ) . '</h1>';

        if ( isset( $_GET['mnsnp_done'] ) ) {
            $ok     = ( $_GET['mnsnp_done'] === 'ok' );
            $report = get_transient( self::TRANSIENT_KEY );
            delete_transient( self::TRANSIENT_KEY );

            printf(
                '<div class="notice %1$s"><p>%2$s</p></div>',
                $ok ? 'notice-success' : 'notice-error',
                $ok ? esc_html__( 'Migration finished. See the report below.', 'mns-navasan-plus' )
                    : esc_html__( 'Migration failed. See the report below.', 'mns-navasan-plus' )
            );

            if ( is_array( $report ) ) {
                $this->report_table( $report );
            }
        }

        // Form
        echo '<form method="post">';
        wp_nonce_field( 'mnsnp_migrate' );
        echo '<table class="form-table"><tbody>';

        echo '<tr><th>' . esc_html__( 'Options', 'mns-navasan-plus' ) . '</th><td>';
        echo '<label><input type="checkbox" name="mnsnp_deactivate_old" value="1" /> ' .
             esc_html__( 'Deactivate old plugin after migration', 'mns-navasan-plus' ) . '</label><br />';
        echo '<label><input type="checkbox" name="mnsnp_delete_old_opts" value="1" /> ' .
             esc_html__( 'Delete old options (mnswmc_*) after copying', 'mns-navasan-plus' ) . '</label>';
        echo '</td></tr>';

        echo '</tbody></table>';

        echo '<p>';
        echo '<button class="button" name="mnsnp_migrate_action" value="dry">' .
             esc_html__( 'Dry run (no changes)', 'mns-navasan-plus' ) . '</button> ';
        echo '<button class="button button-primary" name="mnsnp_migrate_action" value="run">' .
             esc_html__( 'Run Migration', 'mns-navasan-plus' ) . '</button>';
        echo '</p>';

        echo '</form></div>';
    }

    private function report_table( array $r ): void {
        $rows = [
            'currencies_scanned' => __( 'Currencies scanned', 'mns-navasan-plus' ),
            'currencies_moved'   => __( 'Currency metas moved', 'mns-navasan-plus' ),
            'products_scanned'   => __( 'Products scanned', 'mns-navasan-plus' ),
            'products_moved'     => __( 'Product metas moved', 'mns-navasan-plus' ),
            'options_scanned'    => __( 'Options scanned', 'mns-navasan-plus' ),
            'options_moved'      => __( 'Options moved', 'mns-navasan-plus' ),
        ];
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__( 'Item', 'mns-navasan-plus' ) . '</th>';
        echo '<th>' . esc_html__( 'Count', 'mns-navasan-plus' ) . '</th>';
        echo '</tr></thead><tbody>';
        foreach ( $rows as $key => $label ) {
            $val = isset( $r[ $key ] ) ? (string) $r[ $key ] : '-';
            printf(
                '<tr><td>%s</td><td>%s</td></tr>',
                esc_html( $label ),
                esc_html( $val )
            );
        }
        if ( ! empty( $r['errors'] ) && is_array( $r['errors'] ) ) {
            foreach ( $r['errors'] as $err ) {
                printf(
                    '<tr><td colspan="2" style="color:#b32d2e">%s</td></tr>',
                    esc_html( (string) $err )
                );
            }
        }
        echo '</tbody></table>';
    }
}