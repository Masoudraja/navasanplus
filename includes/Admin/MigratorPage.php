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
        $parent = class_exists('\MNS\NavasanPlus\Admin\Menu')
            ? \MNS\NavasanPlus\Admin\Menu::SLUG
            : 'tools.php';

        add_submenu_page(
            $parent,
            __( 'Migrate from Navasan', 'mns-navasan-plus' ),
            __( 'Migration', 'mns-navasan-plus' ),
            'manage_options',
            self::SLUG,
            [ $this, 'render' ]
        );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission.', 'mns-navasan-plus' ) );
        }

        if ( ! class_exists( Migrator::class ) ) {
            $migrator_file = dirname( __DIR__ ) . '/Tools/Migrator.php';
            if ( file_exists( $migrator_file ) ) {
                require_once $migrator_file;
            }
        }

        // POST → PRG
        if ( isset( $_POST['mnsnp_migrate_action'] ) && check_admin_referer( 'mnsnp_migrate' ) ) {
            $action = in_array( $_POST['mnsnp_migrate_action'], [ 'dry', 'run' ], true ) ? $_POST['mnsnp_migrate_action'] : 'dry';
            
            // Get values from new fields and sanitize them
            $migrator_args = [
                'dry'            => ($action === 'dry'),
                'new_formula_id' => isset($_POST['mnsnp_new_formula_id']) ? intval($_POST['mnsnp_new_formula_id']) : 0,
                'new_vazn_code'  => isset($_POST['mnsnp_new_vazn_code']) ? sanitize_text_field($_POST['mnsnp_new_vazn_code']) : '',
                'new_ojrat_code' => isset($_POST['mnsnp_new_ojrat_code']) ? sanitize_text_field($_POST['mnsnp_new_ojrat_code']) : '',
                'old_vazn_key'   => isset($_POST['mnsnp_old_vazn_key']) ? sanitize_text_field($_POST['mnsnp_old_vazn_key']) : '',
                'old_ojrat_key'  => isset($_POST['mnsnp_old_ojrat_key']) ? sanitize_text_field($_POST['mnsnp_old_ojrat_key']) : '',
            ];

            $report = [];
            $error  = '';

            try {
                if ( ! class_exists( Migrator::class ) ) {
                    throw new \RuntimeException( __( 'Migrator class not available.', 'mns-navasan-plus' ) );
                }
                $report = Migrator::run( $migrator_args );
            } catch ( \Throwable $e ) {
                $error = $e->getMessage();
                $report = [ 'errors' => [ $error ] ];
            }

            set_transient( self::TRANSIENT_KEY, $report, 5 * MINUTE_IN_SECONDS );
            wp_safe_redirect( add_query_arg( 'mnsnp_done', $error ? 'fail' : 'ok', menu_page_url( self::SLUG, false ) ) );
            exit;
        }

        // GET view
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Migration from Navasan', 'mns-navasan-plus' ) . '</h1>';
        echo '<p>' . esc_html__( 'This tool migrates specific variable values (like weight and fee) from the old Navasan plugin to the new Navasan Plus structure.', 'mns-navasan-plus' ) . '</p>';

        if ( isset( $_GET['mnsnp_done'] ) ) {
            $ok     = ( $_GET['mnsnp_done'] === 'ok' );
            $report = get_transient( self::TRANSIENT_KEY );
            delete_transient( self::TRANSIENT_KEY );

            printf(
                '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
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
        
        echo '<h2>' . esc_html__( 'Migration Settings', 'mns-navasan-plus' ) . '</h2>';
        echo '<table class="form-table"><tbody>';

        // New Plugin Fields
        echo '<tr><th colspan="2"><h3>' . esc_html__( 'New Plugin (Navasan Plus) Info', 'mns-navasan-plus' ) . '</h3></th></tr>';
        echo '<tr><th><label for="mnsnp_new_formula_id">' . esc_html__( 'New Formula ID', 'mns-navasan-plus' ) . '</label></th><td>';
        echo '<input type="number" id="mnsnp_new_formula_id" name="mnsnp_new_formula_id" class="regular-text" required placeholder="e.g. 4502" />';
        echo '<p class="description">' . esc_html__( 'Enter the ID of the formula you created in the new plugin.', 'mns-navasan-plus' ) . '</p>';
        echo '</td></tr>';
        
        echo '<tr><th><label for="mnsnp_new_vazn_code">' . esc_html__( 'New "Weight" Variable Code', 'mns-navasan-plus' ) . '</label></th><td>';
        echo '<input type="text" id="mnsnp_new_vazn_code" name="mnsnp_new_vazn_code" class="regular-text" required placeholder="e.g. v_mexan5fc7d" />';
        echo '<p class="description">' . esc_html__( 'Enter the variable code for "weight" from your new formula.', 'mns-navasan-plus' ) . '</p>';
        echo '</td></tr>';

        echo '<tr><th><label for="mnsnp_new_ojrat_code">' . esc_html__( 'New "Fee" Variable Code', 'mns-navasan-plus' ) . '</label></th><td>';
        echo '<input type="text" id="mnsnp_new_ojrat_code" name="mnsnp_new_ojrat_code" class="regular-text" required placeholder="e.g. v_mewt5p262c" />';
        echo '<p class="description">' . esc_html__( 'Enter the variable code for "fee" from your new formula.', 'mns-navasan-plus' ) . '</p>';
        echo '</td></tr>';

        // Old Plugin Fields
        echo '<tr><th colspan="2"><h3>' . esc_html__( 'Old Plugin (Navasan) Info', 'mns-navasan-plus' ) . '</h3></th></tr>';
        echo '<tr><th><label for="mnsnp_old_vazn_key">' . esc_html__( 'Old "Weight" Meta Key', 'mns-navasan-plus' ) . '</label></th><td>';
        echo '<input type="text" id="mnsnp_old_vazn_key" name="mnsnp_old_vazn_key" class="regular-text" required value="_mnswmc_variable_regular_926-1" />';
        echo '<p class="description">' . esc_html__( 'The exact meta_key for the old weight value in the wp_postmeta table.', 'mns-navasan-plus' ) . '</p>';
        echo '</td></tr>';
        
        echo '<tr><th><label for="mnsnp_old_ojrat_key">' . esc_html__( 'Old "Fee" Meta Key', 'mns-navasan-plus' ) . '</label></th><td>';
        echo '<input type="text" id="mnsnp_old_ojrat_key" name="mnsnp_old_ojrat_key" class="regular-text" required value="_mnswmc_variable_regular_0-2" />';
        echo '<p class="description">' . esc_html__( 'The exact meta_key for the old fee value in the wp_postmeta table.', 'mns-navasan-plus' ) . '</p>';
        echo '</td></tr>';


        echo '</tbody></table>';

        echo '<p>';
        echo '<button type="submit" class="button" name="mnsnp_migrate_action" value="dry">' . esc_html__( 'Dry run (no changes)', 'mns-navasan-plus' ) . '</button> ';
        echo '<button type="submit" class="button button-primary" name="mnsnp_migrate_action" value="run">' . esc_html__( 'Run Migration', 'mns-navasan-plus' ) . '</button>';
        echo '</p>';

        echo '</form></div>';
    }

    private function report_table( array $r ): void {
        $rows = [
            'report_summary'   => __( 'Summary', 'mns-navasan-plus' ),
            'products_scanned' => __( 'Products Scanned', 'mns-navasan-plus' ),
            'products_updated' => __( 'Products to be Updated', 'mns-navasan-plus' ),
            'vazn_found'       => __( 'Found "Weight" Values', 'mns-navasan-plus' ),
            'ojrat_found'      => __( 'Found "Fee" Values', 'mns-navasan-plus' ),
        ];
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__( 'Item', 'mns-navasan-plus' ) . '</th>';
        echo '<th>' . esc_html__( 'Count / Details', 'mns-navasan-plus' ) . '</th>';
        echo '</tr></thead><tbody>';
        foreach ( $rows as $key => $label ) {
            if ( ! isset( $r[ $key ] ) ) continue;
            $val = is_scalar( $r[ $key ] ) ? (string) $r[ $key ] : '-';
            printf( '<tr><td>%s</td><td>%s</td></tr>', esc_html( $label ), esc_html( $val ) );
        }
        if ( ! empty( $r['errors'] ) && is_array( $r['errors'] ) ) {
            foreach ( $r['errors'] as $err ) {
                printf( '<tr><td colspan="2" style="color:#b32d2e"><strong>%s</strong> %s</td></tr>', esc_html__( 'Error:', 'mns-navasan-plus' ), esc_html( (string) $err ) );
            }
        }
        echo '</tbody></table>';
    }
}