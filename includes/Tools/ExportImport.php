<?php
/**
 * Export/Import Tool for MNS Navasan Plus
 *
 * Allows users to backup and restore all plugin data including:
 * - Plugin settings and options
 * - Custom post types (currencies, formulas, charts)
 * - Product meta data
 * - User preferences
 *
 * File: includes/Tools/ExportImport.php
 */

namespace MNS\NavasanPlus\Tools;

use MNS\NavasanPlus\DB;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ExportImport {

    /** @var ExportImport|null */
    private static ?self $instance = null;

    /**
     * Singleton
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize hooks
     */
    private function __construct() {
        add_action( 'admin_post_mns_export_data', [ $this, 'handle_export' ] );
        add_action( 'admin_post_mns_import_data', [ $this, 'handle_import' ] );
    }

    /**
     * Export all plugin data to JSON file
     */
    public function handle_export(): void {
        // Security check
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to export data.', 'mns-navasan-plus' ) );
        }

        check_admin_referer( 'mns_export_data', 'mns_export_nonce' );

        $data = $this->gather_export_data();

        // Create filename with timestamp
        $filename = 'mns-navasan-plus-backup-' . date( 'Y-m-d-His' ) . '.json';

        // Set headers for download
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        exit;
    }

    /**
     * Gather all plugin data for export
     *
     * @return array
     */
    private function gather_export_data(): array {
        global $wpdb;

        $data = [
            'plugin'    => 'MNS Navasan Plus',
            'version'   => MNS_NAVASAN_PLUS_VER,
            'exported'  => current_time( 'mysql' ),
            'site_url'  => get_site_url(),
            'data'      => [],
        ];

        // 1. Export all plugin options
        $prefix = DB::instance()->prefix();
        $option_like = $wpdb->esc_like( $prefix . '_' ) . '%';

        $options = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                $option_like
            ),
            ARRAY_A
        );

        $data['data']['options'] = [];
        foreach ( $options as $option ) {
            $data['data']['options'][ $option['option_name'] ] = maybe_unserialize( $option['option_value'] );
        }

        // 2. Export custom post types (currencies, formulas, charts)
        $post_types = [ 'mnsnp_currency', 'mnsnp_formula', 'mnsnp_chart' ];
        $data['data']['posts'] = [];

        foreach ( $post_types as $post_type ) {
            $posts = get_posts( [
                'post_type'      => $post_type,
                'post_status'    => 'any',
                'numberposts'    => -1,
                'suppress_filters' => true,
            ] );

            foreach ( $posts as $post ) {
                $post_data = [
                    'ID'            => $post->ID,
                    'post_type'     => $post->post_type,
                    'post_title'    => $post->post_title,
                    'post_content'  => $post->post_content,
                    'post_status'   => $post->post_status,
                    'post_name'     => $post->post_name,
                    'menu_order'    => $post->menu_order,
                    'post_date'     => $post->post_date,
                    'meta'          => [],
                ];

                // Get all post meta
                $meta = get_post_meta( $post->ID );
                foreach ( $meta as $key => $values ) {
                    // Only export our plugin's meta
                    if ( strpos( $key, '_' . $prefix . '_' ) === 0 || strpos( $key, $prefix . '_' ) === 0 ) {
                        $post_data['meta'][ $key ] = array_map( 'maybe_unserialize', $values );
                    }
                }

                $data['data']['posts'][] = $post_data;
            }
        }

        // 3. Export product meta (for WooCommerce products using our plugin)
        $meta_like = $wpdb->esc_like( '_' . $prefix . '_' ) . '%';

        $product_meta = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, meta_key, meta_value
                FROM {$wpdb->postmeta}
                WHERE meta_key LIKE %s
                ORDER BY post_id, meta_key",
                $meta_like
            ),
            ARRAY_A
        );

        $data['data']['product_meta'] = [];
        foreach ( $product_meta as $meta ) {
            $product_id = $meta['post_id'];
            if ( ! isset( $data['data']['product_meta'][ $product_id ] ) ) {
                $data['data']['product_meta'][ $product_id ] = [];
            }
            $data['data']['product_meta'][ $product_id ][ $meta['meta_key'] ] = maybe_unserialize( $meta['meta_value'] );
        }

        // 4. Export user meta (user preferences)
        $user_meta = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, meta_key, meta_value
                FROM {$wpdb->usermeta}
                WHERE meta_key LIKE %s
                ORDER BY user_id, meta_key",
                $meta_like
            ),
            ARRAY_A
        );

        $data['data']['user_meta'] = [];
        foreach ( $user_meta as $meta ) {
            $user_id = $meta['user_id'];
            if ( ! isset( $data['data']['user_meta'][ $user_id ] ) ) {
                $data['data']['user_meta'][ $user_id ] = [];
            }
            $data['data']['user_meta'][ $user_id ][ $meta['meta_key'] ] = maybe_unserialize( $meta['meta_value'] );
        }

        return $data;
    }

    /**
     * Import plugin data from JSON file
     */
    public function handle_import(): void {
        // Security check
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to import data.', 'mns-navasan-plus' ) );
        }

        check_admin_referer( 'mns_import_data', 'mns_import_nonce' );

        // Check if file was uploaded
        if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
            wp_redirect( add_query_arg( [
                'page'   => 'mns-navasan-plus-export-import',
                'error'  => 'no_file',
            ], admin_url( 'admin.php' ) ) );
            exit;
        }

        // Read and decode JSON
        $json = file_get_contents( $_FILES['import_file']['tmp_name'] );
        $data = json_decode( $json, true );

        if ( json_last_error() !== JSON_ERROR_NONE || empty( $data['data'] ) ) {
            wp_redirect( add_query_arg( [
                'page'   => 'mns-navasan-plus-export-import',
                'error'  => 'invalid_file',
            ], admin_url( 'admin.php' ) ) );
            exit;
        }

        // Import the data
        $results = $this->import_data( $data['data'] );

        wp_redirect( add_query_arg( [
            'page'     => 'mns-navasan-plus-export-import',
            'imported' => 1,
            'options'  => $results['options'],
            'posts'    => $results['posts'],
            'meta'     => $results['product_meta'] + $results['user_meta'],
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Import data into database
     *
     * @param array $data
     * @return array Import statistics
     */
    private function import_data( array $data ): array {
        $results = [
            'options'      => 0,
            'posts'        => 0,
            'product_meta' => 0,
            'user_meta'    => 0,
        ];

        // 1. Import options
        if ( ! empty( $data['options'] ) ) {
            foreach ( $data['options'] as $option_name => $option_value ) {
                update_option( $option_name, $option_value, false );
                $results['options']++;
            }
        }

        // 2. Import custom post types
        if ( ! empty( $data['posts'] ) ) {
            foreach ( $data['posts'] as $post_data ) {
                // Check if post already exists by post_name
                $existing = get_posts( [
                    'post_type'   => $post_data['post_type'],
                    'name'        => $post_data['post_name'],
                    'numberposts' => 1,
                    'fields'      => 'ids',
                ] );

                $post_id = null;

                if ( ! empty( $existing ) ) {
                    // Update existing post
                    $post_id = $existing[0];
                    wp_update_post( [
                        'ID'           => $post_id,
                        'post_title'   => $post_data['post_title'],
                        'post_content' => $post_data['post_content'],
                        'post_status'  => $post_data['post_status'],
                        'menu_order'   => $post_data['menu_order'],
                    ] );
                } else {
                    // Create new post
                    $post_id = wp_insert_post( [
                        'post_type'    => $post_data['post_type'],
                        'post_title'   => $post_data['post_title'],
                        'post_content' => $post_data['post_content'],
                        'post_status'  => $post_data['post_status'],
                        'post_name'    => $post_data['post_name'],
                        'menu_order'   => $post_data['menu_order'],
                    ] );
                }

                if ( $post_id && ! is_wp_error( $post_id ) ) {
                    // Import post meta
                    if ( ! empty( $post_data['meta'] ) ) {
                        foreach ( $post_data['meta'] as $meta_key => $meta_values ) {
                            delete_post_meta( $post_id, $meta_key );
                            foreach ( $meta_values as $meta_value ) {
                                add_post_meta( $post_id, $meta_key, $meta_value );
                            }
                        }
                    }
                    $results['posts']++;
                }
            }
        }

        // 3. Import product meta
        if ( ! empty( $data['product_meta'] ) ) {
            foreach ( $data['product_meta'] as $product_id => $meta_array ) {
                // Only import if product exists
                if ( get_post( $product_id ) ) {
                    foreach ( $meta_array as $meta_key => $meta_value ) {
                        update_post_meta( $product_id, $meta_key, $meta_value );
                        $results['product_meta']++;
                    }
                }
            }
        }

        // 4. Import user meta
        if ( ! empty( $data['user_meta'] ) ) {
            foreach ( $data['user_meta'] as $user_id => $meta_array ) {
                // Only import if user exists
                if ( get_user_by( 'id', $user_id ) ) {
                    foreach ( $meta_array as $meta_key => $meta_value ) {
                        update_user_meta( $user_id, $meta_key, $meta_value );
                        $results['user_meta']++;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Render export/import page
     */
    public function render_page(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Export / Import', 'mns-navasan-plus' ); ?></h1>

            <?php
            // Show success message
            if ( ! empty( $_GET['imported'] ) ) {
                $options = intval( $_GET['options'] ?? 0 );
                $posts = intval( $_GET['posts'] ?? 0 );
                $meta = intval( $_GET['meta'] ?? 0 );
                ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        printf(
                            esc_html__( 'Import successful! Imported %d settings, %d posts, and %d meta entries.', 'mns-navasan-plus' ),
                            $options,
                            $posts,
                            $meta
                        );
                        ?>
                    </p>
                </div>
                <?php
            }

            // Show error messages
            if ( ! empty( $_GET['error'] ) ) {
                $error = sanitize_text_field( $_GET['error'] );
                $message = __( 'An error occurred during import.', 'mns-navasan-plus' );

                if ( $error === 'no_file' ) {
                    $message = __( 'Please select a file to import.', 'mns-navasan-plus' );
                } elseif ( $error === 'invalid_file' ) {
                    $message = __( 'Invalid or corrupt backup file.', 'mns-navasan-plus' );
                }
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html( $message ); ?></p>
                </div>
                <?php
            }
            ?>

            <div style="max-width: 800px;">
                <!-- Export Section -->
                <div class="card" style="margin-top: 20px;">
                    <h2><?php esc_html_e( 'Export Plugin Data', 'mns-navasan-plus' ); ?></h2>
                    <p>
                        <?php esc_html_e( 'Download a backup file containing all plugin settings, currencies, formulas, and product configurations.', 'mns-navasan-plus' ); ?>
                    </p>
                    <p>
                        <?php esc_html_e( 'This backup includes:', 'mns-navasan-plus' ); ?>
                    </p>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li><?php esc_html_e( 'Plugin settings and options', 'mns-navasan-plus' ); ?></li>
                        <li><?php esc_html_e( 'Custom currencies, formulas, and charts', 'mns-navasan-plus' ); ?></li>
                        <li><?php esc_html_e( 'Product pricing configurations', 'mns-navasan-plus' ); ?></li>
                        <li><?php esc_html_e( 'User preferences', 'mns-navasan-plus' ); ?></li>
                    </ul>

                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="mns_export_data">
                        <?php wp_nonce_field( 'mns_export_data', 'mns_export_nonce' ); ?>
                        <?php submit_button( __( 'Download Backup File', 'mns-navasan-plus' ), 'primary', 'submit', false ); ?>
                    </form>
                </div>

                <!-- Import Section -->
                <div class="card" style="margin-top: 20px;">
                    <h2><?php esc_html_e( 'Import Plugin Data', 'mns-navasan-plus' ); ?></h2>
                    <p>
                        <?php esc_html_e( 'Restore plugin data from a previously exported backup file.', 'mns-navasan-plus' ); ?>
                    </p>
                    <div class="notice notice-warning inline">
                        <p>
                            <strong><?php esc_html_e( 'Warning:', 'mns-navasan-plus' ); ?></strong>
                            <?php esc_html_e( 'Importing will overwrite existing settings and data. It is recommended to create an export backup first.', 'mns-navasan-plus' ); ?>
                        </p>
                    </div>

                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="mns_import_data">
                        <?php wp_nonce_field( 'mns_import_data', 'mns_import_nonce' ); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="import_file"><?php esc_html_e( 'Backup File', 'mns-navasan-plus' ); ?></label>
                                </th>
                                <td>
                                    <input type="file" name="import_file" id="import_file" accept=".json" required>
                                    <p class="description">
                                        <?php esc_html_e( 'Select a .json backup file exported from this plugin.', 'mns-navasan-plus' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button( __( 'Import Backup', 'mns-navasan-plus' ), 'secondary', 'submit', false ); ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}
