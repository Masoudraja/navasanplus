<?php
namespace MNS\NavasanPlus\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\Services\PriceCalculator;

/**
 * Adds bulk discount management fields to WooCommerce product category pages.
 */
final class CategoryDiscountFields {

    private array $fields = [];

    public function run(): void {
        $this->fields = [
            'discount_profit_percentage' => __( 'Discount on Profit (%)', 'mns-navasan-plus' ),
            'discount_profit_fixed'      => __( 'Discount on Profit (Fixed)', 'mns-navasan-plus' ),
            'discount_charge_percentage' => __( 'Discount on Charge (%)', 'mns-navasan-plus' ),
            'discount_charge_fixed'      => __( 'Discount on Charge (Fixed)', 'mns-navasan-plus' ),
        ];

        add_action( 'product_cat_add_form_fields', [ $this, 'add_fields' ], 20 );
        add_action( 'product_cat_edit_form_fields', [ $this, 'edit_fields' ], 20 );
        add_action( 'create_product_cat', [ $this, 'save_and_bulk_update_fields' ] );
        add_action( 'edit_product_cat',   [ $this, 'save_and_bulk_update_fields' ] );
        add_action( 'admin_action_mnsnp_clear_cat_discounts', [ $this, 'handle_clear_discounts' ] );
    }

    /**
     * Display fields on the "Add New Category" screen.
     */
    public function add_fields( $taxonomy ): void {
        wp_nonce_field( 'mnsnp_cat_discount_save', '_mnsnp_cat_nonce' );
        ?>
        <div style="margin: 1.5em 0; border-top: 1px solid #c3c4c7; padding-top: 1.5em;">
            <h2><?php esc_html_e( 'Navasan Plus - Category Discounts', 'mns-navasan-plus' ); ?></h2>
            <p><?php esc_html_e( 'These discounts will be applied to products in this category upon creation.', 'mns-navasan-plus' ); ?></p>
            <?php foreach ( $this->fields as $key => $label ) : ?>
                <div class="form-field term-<?php echo esc_attr($key); ?>-wrap">
                    <label for="mnsnp_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
                    <input type="number" step="1" name="mnsnp_discount[<?php echo esc_attr($key); ?>]" id="mnsnp_<?php echo esc_attr($key); ?>" value="">
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Display fields on the "Edit Category" screen using the robust, self-contained metabox-like structure.
     */
    public function edit_fields( \WP_Term $term ): void {
        wp_nonce_field( 'mnsnp_cat_discount_save', '_mnsnp_cat_nonce' );
        
        $clear_url = wp_nonce_url(
            admin_url( 'admin.php?action=mnsnp_clear_cat_discounts&term_id=' . $term->term_id . '&taxonomy=' . $term->taxonomy ),
            'mnsnp_clear_cat_discounts_' . $term->term_id
        );
        ?>
        <tr class="form-field">
            <td colspan="2" style="padding: 0;">
                <div id="mnsnp-category-discounts-metabox" class="postbox" style="margin-top: 1em;">
                    <div class="postbox-header">
                        <h2 class="hndle" style="cursor: default;"><?php esc_html_e( 'Navasan Plus - Bulk Edit Discounts', 'mns-navasan-plus' ); ?></h2>
                    </div>
                    <div class="inside">
                        <p class="description" style="margin-bottom: 1em;"><?php esc_html_e( 'IMPORTANT: When you update the category, these values will be applied to ALL products in this category, overwriting their current discounts and recalculating their final prices.', 'mns-navasan-plus' ); ?></p>
                        <table class="form-table" role="presentation">
                            <tbody>
                            <?php foreach ( $this->fields as $key => $label ) : 
                                $value = get_term_meta( $term->term_id, $key, true );
                                ?>
                                <tr class="form-field term-<?php echo esc_attr($key); ?>-wrap">
                                    <th scope="row"><label for="mnsnp_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                                    <td><input type="number" step="1" name="mnsnp_discount[<?php echo esc_attr($key); ?>]" id="mnsnp_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr( $value ); ?>"></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="form-field">
                                <th scope="row"><?php esc_html_e( 'Clear Discounts Action', 'mns-navasan-plus' ); ?></th>
                                <td>
                                    <a href="<?php echo esc_url( $clear_url ); ?>" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to clear these discounts for the category AND all its products?', 'mns-navasan-plus' ); ?>');">
                                        <?php esc_html_e( 'Clear All Discounts for This Category', 'mns-navasan-plus' ); ?>
                                    </a>
                                    <p class="description"><?php esc_html_e( 'This will remove the saved discounts from this category and all products within it.', 'mns-navasan-plus' ); ?></p>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
        <?php
    }

    public function save_and_bulk_update_fields( int $term_id ): void {
        if ( ! isset( $_POST['_mnsnp_cat_nonce'] ) || ! wp_verify_nonce( $_POST['_mnsnp_cat_nonce'], 'mnsnp_cat_discount_save' ) ) return;
        if ( empty( $_POST['mnsnp_discount'] ) || ! is_array( $_POST['mnsnp_discount'] ) ) return;
        $discounts = wp_unslash( $_POST['mnsnp_discount'] );
        $this->apply_and_save( $term_id, $discounts );
    }
    
    public function handle_clear_discounts(): void {
        $term_id = isset( $_GET['term_id'] ) ? absint( $_GET['term_id'] ) : 0;
        if ( ! $term_id || ! check_admin_referer( 'mnsnp_clear_cat_discounts_' . $term_id ) || ! current_user_can( 'edit_term', $term_id ) ) {
            wp_die( __( 'Invalid request.', 'mns-navasan-plus' ) );
        }
        $empty_discounts = array_fill_keys( array_keys($this->fields), '' );
        $this->apply_and_save( $term_id, $empty_discounts );
        $redirect_url = get_edit_term_link( $term_id, 'product_cat' );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    private function apply_and_save( int $term_id, array $discounts ): void {
        $db = \MNS\NavasanPlus\DB::instance();

        // Step 1: Save the values to the category term meta.
        foreach ( $this->fields as $key => $label ) {
            $value = isset( $discounts[ $key ] ) && $discounts[ $key ] !== '' ? wc_format_decimal( $discounts[ $key ], 0 ) : '';
            update_term_meta( $term_id, $key, $value );
        }
        
        // Step 2: Get all products AND variations in the category.
        $product_ids = get_posts([
            'post_type'      => ['product', 'product_variation'],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'suppress_filters' => true,
            'tax_query'      => [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $term_id,
                ],
            ],
        ]);
        if ( empty($product_ids) ) return;

        // Step 3: Loop and update each product/variation.
        set_time_limit(300);
        foreach ( $product_ids as $pid ) {
            // 3a: Update discount meta.
            foreach ( $this->fields as $key => $label ) {
                $value_to_save = $discounts[ $key ] ?? '';
                $value = ( $value_to_save === '' ) ? '' : wc_format_decimal( $value_to_save, 0 );
                update_post_meta( $pid, $db->full_meta_key( $key ), $value );
            }
            // 3b: Recalculate and apply the final price.
            if ( class_exists( PriceCalculator::class ) ) {
                try {
                    $res = PriceCalculator::instance()->calculate( $pid );
                    if ( $res !== null ) {
                        $price_after  = is_array($res) ? (float) ($res['price'] ?? 0) : (float) $res;
                        $price_before = is_array($res) ? (float) ($res['price_before'] ?? $price_after) : $price_after;
                        $p_i  = (int) floor($price_after);
                        $pb_i = (int) floor($price_before);
                        $has_discount = ($pb_i > $p_i);
                        $regular_i = $has_discount ? $pb_i : $p_i;
                        $sale_i    = $has_discount ? $p_i  : 0;
                        update_post_meta( $pid, '_regular_price', $regular_i > 0 ? (string)$regular_i : '' );
                        update_post_meta( $pid, '_sale_price',    $sale_i    > 0 ? (string)$sale_i    : '' );
                        update_post_meta( $pid, '_price', ($sale_i > 0 ? (string)$sale_i : ($regular_i > 0 ? (string)$regular_i : '')) );
                        
                        // Clear caches for both the product/variation AND its parent.
                        if ( function_exists('wc_delete_product_transients') ) {
                            wc_delete_product_transients( $pid );
                            $parent_id = wp_get_post_parent_id( $pid );
                            if ( $parent_id ) {
                                wc_delete_product_transients( $parent_id );
                            }
                        }
                    }
                } catch (\Throwable $e) { /* Continue on error */ }
            }
        }
    }
}