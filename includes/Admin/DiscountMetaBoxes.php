<?php
namespace MNS\NavasanPlus\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\DB;

class DiscountMetaBoxes {

    public function run() {
        // پیش‌فرض: «روشن» — قابل خاموش‌کردن با فیلتر
        if ( ! apply_filters( 'mnsnp/enable_side_discount_box', true ) ) {
            return;
        }
        add_action( 'add_meta_boxes',    [ $this, 'add_discount_box' ] );
        add_action( 'save_post_product', [ $this, 'save_discount_box' ] );
    }

    public function add_discount_box() {
        add_meta_box(
            'mnsnp_discount_legacy',
            __( 'Navasan Plus — Discounts (Side Box)', 'mns-navasan-plus' ),
            [ $this, 'render_discount_box' ],
            'product',
            'side',
            'default'
        );
    }

    public function render_discount_box( $post ) {
        wp_nonce_field( 'mnsnp_discount_box', '_mnsnp_discount_nonce' );

        $db = DB::instance();
        $get = fn($k) => get_post_meta( $post->ID, $db->full_meta_key( $k ), true );

        $fields = [
            'profit_percentage' => $get('discount_profit_percentage'),
            'profit_fixed'      => $get('discount_profit_fixed'),
            'charge_percentage' => $get('discount_charge_percentage'),
            'charge_fixed'      => $get('discount_charge_fixed'),
        ];
        ?>
        <p>
            <label for="mnsnp_discount_profit_percentage"><?php _e( 'Discount on Profit (%)', 'mns-navasan-plus' ); ?></label>
            <input type="number" step="1" name="mnsnp_discount[profit_percentage]" id="mnsnp_discount_profit_percentage" value="<?php echo esc_attr( $fields['profit_percentage'] ); ?>" class="widefat">
        </p>
        <p>
            <label for="mnsnp_discount_profit_fixed"><?php _e( 'Discount on Profit (Fixed)', 'mns-navasan-plus' ); ?></label>
            <input type="number" step="1" name="mnsnp_discount[profit_fixed]" id="mnsnp_discount_profit_fixed" value="<?php echo esc_attr( $fields['profit_fixed'] ); ?>" class="widefat">
        </p>
        <p>
            <label for="mnsnp_discount_charge_percentage"><?php _e( 'Discount on Charge (%)', 'mns-navasan-plus' ); ?></label>
            <input type="number" step="1" name="mnsnp_discount[charge_percentage]" id="mnsnp_discount_charge_percentage" value="<?php echo esc_attr( $fields['charge_percentage'] ); ?>" class="widefat">
        </p>
        <p>
            <label for="mnsnp_discount_charge_fixed"><?php _e( 'Discount on Charge (Fixed)', 'mns-navasan-plus' ); ?></label>
            <input type="number" step="1" name="mnsnp_discount[charge_fixed]" id="mnsnp_discount_charge_fixed" value="<?php echo esc_attr( $fields['charge_fixed'] ); ?>" class="widefat">
        </p>
        <?php
    }

    public function save_discount_box( $post_id ) {
        if ( ! isset( $_POST['_mnsnp_discount_nonce'] ) || ! wp_verify_nonce( $_POST['_mnsnp_discount_nonce'], 'mnsnp_discount_box' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( empty( $_POST['mnsnp_discount'] ) || ! is_array( $_POST['mnsnp_discount'] ) ) return;

        $db = DB::instance();
        $in = wp_unslash( $_POST['mnsnp_discount'] );

        $map = [
            'profit_percentage' => 0, // دقت اعشار
            'profit_fixed'      => 0,
            'charge_percentage' => 0,
            'charge_fixed'      => 0,
        ];

        foreach ( $map as $key => $precision ) {
            $raw = $in[ $key ] ?? '';
            $val = ( $raw === '' ) ? '' : wc_format_decimal( $raw, $precision );
            update_post_meta( $post_id, $db->full_meta_key( 'discount_' . $key ), $val );
        }
    }
}