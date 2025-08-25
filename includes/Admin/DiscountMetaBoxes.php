<?php
namespace MNS\NavasanPlus\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class DiscountMetaBoxes {

    public function run() {
        add_action( 'add_meta_boxes', [ $this, 'add_discount_box' ] );
        add_action( 'save_post_product', [ $this, 'save_discount_box' ] );
    }

    public function add_discount_box() {
        add_meta_box(
            'mns_discount',
            __( 'Discount Fields', 'mns-navasan-plus' ),
            [ $this, 'render_discount_box' ],
            'product',
            'side',
            'default'
        );
    }

    public function render_discount_box( $post ) {
        wp_nonce_field( 'mns_discount_nonce', 'mns_discount_nonce_field' );

        $fields = [
            'profit_percentage' => get_post_meta( $post->ID, '_mns_discount_profit_percentage', true ),
            'profit_fixed'      => get_post_meta( $post->ID, '_mns_discount_profit_fixed', true ),
            'charge_percentage' => get_post_meta( $post->ID, '_mns_discount_charge_percentage', true ),
            'charge_fixed'      => get_post_meta( $post->ID, '_mns_discount_charge_fixed', true ),
        ];

        ?>
        <p>
            <label for="mns_discount_profit_percentage"><?php _e( 'Discount on Profit (%)', 'mns-navasan-plus' ); ?></label>
            <input type="number" step="0.01" name="mns_discount_profit_percentage" id="mns_discount_profit_percentage" value="<?php echo esc_attr( $fields['profit_percentage'] ); ?>" class="widefat">
        </p>
        <p>
            <label for="mns_discount_profit_fixed"><?php _e( 'Discount on Profit (Fixed)', 'mns-navasan-plus' ); ?></label>
            <input type="number" step="0.01" name="mns_discount_profit_fixed" id="mns_discount_profit_fixed" value="<?php echo esc_attr( $fields['profit_fixed'] ); ?>" class="widefat">
        </p>
        <p>
            <label for="mns_discount_charge_percentage"><?php _e( 'Discount on Charge (%)', 'mns-navasan-plus' ); ?></label>
            <input type="number" step="0.01" name="mns_discount_charge_percentage" id="mns_discount_charge_percentage" value="<?php echo esc_attr( $fields['charge_percentage'] ); ?>" class="widefat">
        </p>
        <p>
            <label for="mns_discount_charge_fixed"><?php _e( 'Discount on Charge (Fixed)', 'mns-navasan-plus' ); ?></label>
            <input type="number" step="0.01" name="mns_discount_charge_fixed" id="mns_discount_charge_fixed" value="<?php echo esc_attr( $fields['charge_fixed'] ); ?>" class="widefat">
        </p>
        <?php
    }

    public function save_discount_box( $post_id ) {
        if ( ! isset( $_POST['mns_discount_nonce_field'] ) ||
             ! wp_verify_nonce( $_POST['mns_discount_nonce_field'], 'mns_discount_nonce' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $fields = [
            'mns_discount_profit_percentage' => 'floatval',
            'mns_discount_profit_fixed'      => 'floatval',
            'mns_discount_charge_percentage' => 'floatval',
            'mns_discount_charge_fixed'      => 'floatval',
        ];

        foreach ( $fields as $key => $sanitize_cb ) {
            if ( isset( $_POST[ $key ] ) ) {
                $value = call_user_func( $sanitize_cb, $_POST[ $key ] );
                update_post_meta( $post_id, '_' . $key, $value );
            } else {
                delete_post_meta( $post_id, '_' . $key );
            }
        }
    }
}