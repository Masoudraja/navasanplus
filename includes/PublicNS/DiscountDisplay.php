<?php
namespace MNS\NavasanPlus\PublicNS;

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\Services\PriceCalculator;

/**
 * Handles the display of the saved discount amount on cart and checkout items.
 */
final class DiscountDisplay {

    /**
     * The unique ID of the installment payment gateway.
     */
    private const INSTALLMENT_GATEWAY_ID = 'WC_Gateway_SnappPay';

    public function run(): void {
        add_action( 'woocommerce_after_cart_item_name', [ $this, 'display_cart_item_discount' ], 10, 2 );
        add_filter( 'woocommerce_checkout_cart_item_quantity', [ $this, 'add_discount_to_checkout_quantity' ], 10, 3 );
    }

    public function display_cart_item_discount( $cart_item, $cart_item_key ): void {
        echo $this->get_discount_html_for_item( $cart_item );
    }

    public function add_discount_to_checkout_quantity( $quantity_html, $cart_item, $cart_item_key ): string {
        return $quantity_html . $this->get_discount_html_for_item( $cart_item );
    }

    /**
     * A central helper function to get the discount HTML for a cart item.
     * <<< UPDATED: It now checks the selected payment gateway before displaying the discount. >>>
     */
    private function get_discount_html_for_item( array $cart_item ): string {
        // --- Step 1: Determine the currently selected payment gateway ---
        $chosen_gateway = '';
        if ( ! empty( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) && is_checkout() ) {
            parse_str( $_POST['post_data'], $post_data_array );
            if ( ! empty( $post_data_array['payment_method'] ) ) {
                $chosen_gateway = sanitize_text_field( $post_data_array['payment_method'] );
            }
        }
        if ( empty($chosen_gateway) && WC()->session && WC()->session->has_session() ) {
            $chosen_gateway = WC()->session->get( 'chosen_payment_method' );
        }

        // --- Step 2: If the installment gateway is selected, show no discount ---
        if ( $chosen_gateway === self::INSTALLMENT_GATEWAY_ID ) {
            return ''; // Return an empty string to hide the discount notice.
        }

        // --- Step 3: If any other gateway is selected, calculate and show the discount ---
        if ( ! isset( $cart_item['data'] ) || ! $cart_item['data'] instanceof \WC_Product ) {
            return '';
        }
        $product = $cart_item['data'];

        if ( ! class_exists( PriceCalculator::class ) ) {
            return '';
        }
        $calc = PriceCalculator::instance()->calculate( $product->get_id() );

        if ( is_array( $calc ) && isset( $calc['price_before'], $calc['price'] ) ) {
            $price_after  = (float) $calc['price'];
            $price_before = (float) $calc['price_before'];
            $discount_amount = $price_before - $price_after;

            if ( $discount_amount > 0.001 ) {
                $discount_html = wc_price( $discount_amount );
                return '<div class="mnsnp-item-discount-notice" style="font-size: 0.9em; color: #28a745; font-weight: bold; width: 100%;">' .
                       sprintf( esc_html__( 'سود شما: %s', 'mns-navasan-plus' ), wp_kses_post( $discount_html ) ) .
                       '</div>';
            }
        }

        return '';
    }
}