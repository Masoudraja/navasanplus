<?php
namespace MNS\NavasanPlus\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\Services\PriceCalculator;

final class RecalculatePage {

    private const SLUG = 'mnsnp-recalculate-prices';

    public function run(): void {
        add_action( 'admin_menu', [ $this, 'menu' ] );
        add_action( 'admin_post_mnsnp_recalculate_all_prices', [ $this, 'handle' ] );
    }

    public function menu(): void {
        $parent = class_exists('\MNS\NavasanPlus\Admin\Menu')
            ? \MNS\NavasanPlus\Admin\Menu::SLUG
            : 'woocommerce';

        add_submenu_page(
            $parent,
            __( 'Recalculate Prices', 'mns-navasan-plus' ),
            __( 'Tools: Recalculate Prices', 'mns-navasan-plus' ),
            'manage_woocommerce',
            self::SLUG,
            [ $this, 'render' ]
        );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Access Denied' );
        }

        if ( isset( $_GET['mnsnp_done'] ) ) {
            $msg = isset( $_GET['mnsnp_msg'] ) ? wp_unslash( $_GET['mnsnp_msg'] ) : '';
            printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $msg ) );
        }

        echo '<div class="wrap"><h1>' . esc_html__( 'Recalculate All Product Prices', 'mns-navasan-plus' ) . '</h1>';
        
        // <<< SECTION 1: The User-Friendly UI Method >>>
        echo '<h2>' . esc_html__( 'Method 1: Update via Browser (for few products)', 'mns-navasan-plus' ) . '</h2>';
        echo '<p>' . esc_html__( 'Use this tool to recalculate and apply the formula-based price for all published products. This may take a long time for sites with many products and could time out.', 'mns-navasan-plus' ) . '</p>';
        
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'mnsnp_recalculate_all', '_mnsnp_nonce' );
        echo '<input type="hidden" name="action" value="mnsnp_recalculate_all_prices" />';
        submit_button( __( 'Start Recalculation Now', 'mns-navasan-plus' ), 'primary', 'submit', true, [
            'onclick' => 'return confirm(\'' . esc_js( __( 'This can be a slow process and cannot be undone. Are you sure you want to continue?', 'mns-navasan-plus' ) ) . '\');'
        ] );
        echo '</form>';

        // <<< ADDED: The complete WP-CLI Guide as a new section >>>
        echo '<hr style="margin: 2em 0;">';
        echo '<h2>' . esc_html__( 'Method 2: Update via Command Line (Recommended for many products)', 'mns-navasan-plus' ) . '</h2>';
        ?>
        <div id="mnsnp-wpcli-guide" style="max-width: 800px;">
            
            <h3><?php esc_html_e( '1. What is WP-CLI and Why Use It?', 'mns-navasan-plus' ); ?></h3>
            <p><?php esc_html_e( 'WP-CLI is a command-line interface for WordPress. It allows you to perform administrative tasks directly from the server\'s terminal. We use this method for updating prices because it is much faster than other methods and, most importantly, it does not encounter the execution time limit (timeout) that exists in web admin pages. This method is the only reliable way for sites with thousands of products.', 'mns-navasan-plus' ); ?></p>

            <h3><?php esc_html_e( '2. Prerequisites', 'mns-navasan-plus' ); ?></h3>
            <ul>
                <li><?php esc_html_e( 'SSH access to the site server.', 'mns-navasan-plus' ); ?></li>
                <li><?php esc_html_e( 'WP-CLI installed on the server (usually all good WordPress hosts have it).', 'mns-navasan-plus' ); ?></li>
                <li><?php esc_html_e( 'The "MNS Navasan Plus" plugin must be active.', 'mns-navasan-plus' ); ?></li>
            </ul>

            <h3><?php esc_html_e( '3. Execution Steps', 'mns-navasan-plus' ); ?></h3>
            <ol>
                <li>
                    <p><strong><?php esc_html_e( 'Connect to the server:', 'mns-navasan-plus' ); ?></strong> <?php esc_html_e( 'Using a terminal application (like Terminal on Mac or PuTTY on Windows), connect to your server via SSH.', 'mns-navasan-plus' ); ?></p>
                    <pre style="background-color: #f6f7f7; padding: 15px; border-radius: 4px;"><code>ssh username@yourdomain.com</code></pre>
                </li>
                <li>
                    <p><strong><?php esc_html_e( 'Navigate to the WordPress root folder:', 'mns-navasan-plus' ); ?></strong> <?php esc_html_e( 'Go to the path where the main WordPress files (like wp-config.php) are located. This path is usually public_html or your domain name.', 'mns-navasan-plus' ); ?></p>
                    <pre style="background-color: #f6f7f7; padding: 15px; border-radius: 4px;"><code>cd /path/to/your/wordpress/root</code><br><em style="color:#777;"><?php esc_html_e( '# Example: cd public_html', 'mns-navasan-plus' ); ?></em></pre>
                </li>
                <li>
                    <p><strong><?php esc_html_e( 'Run the command:', 'mns-navasan-plus' ); ?></strong> <?php esc_html_e( 'Copy and run the following command completely in the terminal:', 'mns-navasan-plus' ); ?></p>
                    <pre style="background-color: #f6f7f7; padding: 15px; border-radius: 4px;"><code>wp mnsnp recalc-prices</code></pre>
                </li>
                <li>
                    <p><strong><?php esc_html_e( 'Observe the output:', 'mns-navasan-plus' ); ?></strong> <?php esc_html_e( 'After running the command, you will see an output similar to the following. A progress bar shows you the status of the operation.', 'mns-navasan-plus' ); ?></p>
                    <pre style="background-color: #f6f7f7; padding: 15px; border-radius: 4px; white-space: pre-wrap; word-break: break-all;">Fetching all published products and variations...
Found 2548 products. Starting recalculation...
Recalculating Prices: 100% [=========================================] 0:15 / 0:15
Success: Process complete. 2548 products updated. 0 products failed.</pre>
                </li>
            </ol>

            <h3><?php esc_html_e( '4. Troubleshooting', 'mns-navasan-plus' ); ?></h3>
            <ul>
                <li><?php esc_html_e( "If you encounter the error `Error: 'mnsnp' is not a registered wp command`, make sure the 'MNS Navasan Plus' plugin is active.", 'mns-navasan-plus' ); ?></li>
                <li><?php esc_html_e( 'If you see a warning error for a specific product during execution, note down the product ID and check it manually in the admin panel.', 'mns-navasan-plus' ); ?></li>
            </ul>
        </div>
        <?php
        echo '</div>'; // close .wrap
    }

    public function handle(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Access Denied' );
        }
        check_admin_referer( 'mnsnp_recalculate_all', '_mnsnp_nonce' );

        set_time_limit(0); // Attempt to prevent timeouts

        $product_ids = get_posts([
            'post_type'      => ['product', 'product_variation'],
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ]);

        $updated_count = 0;
        $error_count = 0;

        foreach ( $product_ids as $pid ) {
            try {
                $res = PriceCalculator::instance()->calculate( $pid );
                if ($res === null) {
                    $error_count++;
                    continue;
                }
                $price_after  = is_array($res) ? (float) ($res['price'] ?? 0) : (float) $res;
                $price_before = is_array($res) ? (float) ($res['price_before'] ?? $price_after) : $price_after;
                $p_i  = (int) floor($price_after);
                $pb_i = (int) floor($price_before);
                $has_discount = ($pb_i > $p_i);
                $regular_i = $has_discount ? $pb_i : $p_i;
                $sale_i    = $has_discount ? $p_i  : 0;
                
                update_post_meta( $pid, '_regular_price', $regular_i > 0 ? (string)$regular_i : '' );
                update_post_meta( $pid, '_sale_price',    $sale_i    > 0 ? (string)$sale_i    : '' );
                update_post_meta( $pid, '_price',         ($sale_i > 0 ? (string)$sale_i : ($regular_i > 0 ? (string)$regular_i : '')) );
                
                if ( function_exists('wc_delete_product_transients') ) {
                    wc_delete_product_transients( $pid );
                }
                $updated_count++;
            } catch (\Throwable $e) {
                $error_count++;
            }
        }

        $msg = sprintf( __( 'Process complete. %d products updated, %d products failed.', 'mns-navasan-plus' ), $updated_count, $error_count );
        $url = add_query_arg( [ 'page' => self::SLUG, 'mnsnp_done' => '1', 'mnsnp_msg' => rawurlencode($msg) ], admin_url('admin.php') );
        wp_safe_redirect( $url );
        exit;
    }
}