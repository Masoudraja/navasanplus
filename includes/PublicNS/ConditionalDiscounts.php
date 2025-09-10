<?php
namespace MNS\NavasanPlus\PublicNS;

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\Services\PriceCalculator;

final class ConditionalDiscounts {

    private const INSTALLMENT_GATEWAY_ID = 'WC_Gateway_SnappPay';

    public function run(): void {
        add_filter( 'woocommerce_product_get_price', [ $this, 'maybe_remove_discount' ], 100, 2 );
        add_filter( 'woocommerce_product_variation_get_price', [ $this, 'maybe_remove_discount' ], 100, 2 );
        add_action( 'wp_footer', [ $this, 'add_checkout_update_script' ] );
    }

    public function maybe_remove_discount( $price, $product ) {
        if ( is_admin() ) return $price;
        if ( ! is_cart() && ! is_checkout() ) return $price;
        if ( ! WC()->session || ! WC()->session->has_session() ) return $price;
        
        $chosen_gateway = '';
        if ( ! empty( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) {
            parse_str( $_POST['post_data'], $post_data_array );
            if ( ! empty( $post_data_array['payment_method'] ) ) {
                $chosen_gateway = sanitize_text_field( $post_data_array['payment_method'] );
            }
        }
        if ( empty($chosen_gateway) ) {
            $chosen_gateway = WC()->session->get( 'chosen_payment_method' );
        }
        
        if ( $chosen_gateway === self::INSTALLMENT_GATEWAY_ID ) {
            if ( $product->is_on_sale() && $product->get_regular_price() ) {
                return $product->get_regular_price();
            }
        }
        return $price;
    }
    
    public function add_checkout_update_script() {
        if ( ! is_checkout() ) return;
        $cart = WC()->cart;
        if ( ! $cart || $cart->is_empty() ) return;

        $discounted_total = 0;
        foreach ( $cart->get_cart() as $cart_item ) {
            $product = $cart_item['data'];
            if ($product) {
                remove_filter( 'woocommerce_product_get_price', [ $this, 'maybe_remove_discount' ], 100 );
                $price_for_calc = $product->get_price();
                add_filter( 'woocommerce_product_get_price', [ $this, 'maybe_remove_discount' ], 100, 2 );
                $discounted_total += (float) $price_for_calc * $cart_item['quantity'];
            }
        }
        $discounted_total += (float) $cart->get_shipping_total() + (float) $cart->get_total_tax() + (float) $cart->get_fee_total();
        
        $discounted_total_html = wc_price($discounted_total);
        $installment_gateway_id = self::INSTALLMENT_GATEWAY_ID;
        ?>
        <div id="mnsnp-checkout-price-data" style="display:none;"
             data-discounted-total-html="<?php echo esc_attr( wp_strip_all_tags( $discounted_total_html ) ); ?>"
             data-installment-gateway-id="<?php echo esc_attr( $installment_gateway_id ); ?>">
        </div>

        <script type="text/javascript">
        jQuery( function( $ ) {
            
            function fixCheckoutDisplay() {
                var priceData = $( '#mnsnp-checkout-price-data' );
                var discountedTotalHtml = priceData.data( 'discounted-total-html' );
                var installmentGatewayId = priceData.data( 'installment-gateway-id' );
                var selectedGateway = $( 'ul.payment_methods input[name="payment_method"]:checked' ).val();
                var totalElement = $( 'tr.order-total .woocommerce-Price-amount' ).last();
                
                if ( ! priceData.length || ! totalElement.length ) return;
                
                if ( selectedGateway !== installmentGatewayId ) {
                    totalElement.html( discountedTotalHtml );
                }
            }

            // A variable to hold our polling timer, so we can control it.
            var mnsnpInterval = null;

            // Listen for a change on the payment methods. 'change' is more reliable than 'click'.
            $( 'form.checkout' ).on( 'change', 'input[name="payment_method"]', function() {
                
                // If a check is already running, clear it before starting a new one.
                if ( mnsnpInterval ) {
                    clearInterval( mnsnpInterval );
                }
                
                // Manually trigger WooCommerce's update process. This is the key.
                $( 'body' ).trigger( 'update_checkout' );

                // Start a new polling check.
                mnsnpInterval = setInterval( function() {
                    // .blockUI is the class for the loading overlay. We wait for it to disappear.
                    if ( $( 'div.blockUI' ).length === 0 ) {
                        // The AJAX is done. Stop polling.
                        clearInterval( mnsnpInterval );
                        mnsnpInterval = null;
                        
                        // Run our price fixing function.
                        fixCheckoutDisplay();
                    }
                }, 100); // Check every 100 milliseconds
            });
        });
        </script>
        <?php
    }
}