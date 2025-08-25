<?php
/**
 * Order Rates Meta‐box template for MNS Navasan Plus
 *
 * Displays each order item with its related currency rates (invoice vs current).
 *
 * @var WP_Post $post
 */

use MNS\NavasanPlus\Helpers;
use MNS\NavasanPlus\Templates\Classes\Snippets;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get the WC_Order object for the current post
$order = wc_get_order( $post->ID );
if ( ! $order ) {
    return;
}
?>

<div class="mns-navasan-plus-order-rates">
    <?php foreach ( $order->get_items() as $item ) :
        $wc_item    = new \WC_Order_Item_Product( $item );
        $wc_product = $wc_item->get_product();
        if ( ! is_object( $wc_product ) ) {
            continue;
        }

        // Enhance the product with Navasan Plus functionality
        $mns_product = mns_navasan_plus()->get_product( $wc_product );
        ?>
        <h4><?php echo esc_html( $wc_product->get_title() ); ?></h4>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Currency', 'mns-navasan-plus' ); ?></th>
                    <th><?php esc_html_e( 'Invoice Rate', 'mns-navasan-plus' ); ?></th>
                    <th><?php esc_html_e( 'Current Rate', 'mns-navasan-plus' ); ?></th>
                    <th><?php esc_html_e( 'Change', 'mns-navasan-plus' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $mns_product->get_currencies() as $currency ) :
                    $current_rate = floatval( $currency->get_rate() );
                    $invoice_rate = $order->get_currency_rate( $currency->get_id() ) ?: $current_rate;
                    ?>
                    <tr>
                        <td><?php echo esc_html( $currency->get_name() ); ?></td>
                        <td><?php echo esc_html( Helpers::format_decimal( $invoice_rate, 2 ) ); ?></td>
                        <td><?php echo esc_html( Helpers::format_decimal( $current_rate, 2 ) ); ?></td>
                        <td><?php echo wp_kses_post( Snippets::percentage_change( $invoice_rate, $current_rate ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
</div>