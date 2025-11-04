<?php
namespace MNS\NavasanPlus\Services;

if (!defined('ABSPATH')) {
  exit();
}

use MNS\NavasanPlus\Services\PriceCalculator;

/**
 * Handles order status changes and updates product prices when orders are cancelled or refunded
 */
final class OrderStatusHandler {
  public function run(): void {
    // Listen for order status changes
    add_action('woocommerce_order_status_changed', [$this, 'handle_order_status_change'], 10, 4);

    // Listen for cart item additions to update prices when reordering
    add_filter('woocommerce_add_cart_item', [$this, 'update_cart_item_price'], 10, 2);

    // Listen for cart item data to ensure fresh prices
    add_filter(
      'woocommerce_get_cart_item_from_session',
      [$this, 'update_session_cart_item_price'],
      10,
      3,
    );

    // Listen for order again functionality
    add_action('woocommerce_order_again_cart_item_data', [$this, 'handle_order_again'], 10, 3);

    // Update cart prices before calculating totals
    add_action(
      'woocommerce_cart_calculate_fees',
      [$this, 'update_cart_prices_before_checkout'],
      10,
      1,
    );

    // Woodmart theme specific hooks
    add_action('wp_footer', [$this, 'add_woodmart_price_update_script']);

    // AJAX handler for price updates
    add_action('wp_ajax_mnsnp_update_cart_prices', [$this, 'ajax_update_cart_prices']);
    add_action('wp_ajax_nopriv_mnsnp_update_cart_prices', [$this, 'ajax_update_cart_prices']);

    // Handle order pay page to ensure fresh prices
    add_action('template_redirect', [$this, 'handle_order_pay_page']);

    // Update order item prices on order pay page
    add_filter(
      'woocommerce_order_amount_item_total',
      [$this, 'update_order_pay_item_price'],
      20,
      5,
    );
  }

  /**
   * Handle order pay page to ensure fresh prices
   */
  public function handle_order_pay_page(): void {
    // Check if we're on the order pay page
    if (\MNS\NavasanPlus\is_order_pay_page()) {
      // Update cart prices when on the order pay page
      $this->update_cart_prices_on_order_pay();
    }
  }

  /**
   * Update order item price on order pay page
   */
  public function update_order_pay_item_price($price, $order, $item, $inc_tax, $round) {
    // Check if we're on the order pay page
    if (\MNS\NavasanPlus\is_order_pay_page()) {
      // Get the product from the order item
      $product = $item->get_product();

      if ($product) {
        // Calculate the current price using our price calculator
        if (class_exists(PriceCalculator::class)) {
          $res = PriceCalculator::instance()->calculate($product->get_id());

          if ($res !== null) {
            $price_after = is_array($res) ? (float) ($res['price'] ?? 0) : (float) $res;
            return $price_after;
          }
        }
      }
    }

    return $price;
  }

  /**
   * Update cart prices specifically for order pay page
   */
  private function update_cart_prices_on_order_pay(): void {
    // Get the order ID from the URL
    $order_id = absint(get_query_var('order-pay'));

    if (!$order_id) {
      return;
    }

    // Get the order
    $order = wc_get_order($order_id);

    if (!$order) {
      return;
    }

    // Get all items in the order
    $items = $order->get_items();

    if (empty($items)) {
      return;
    }

    // Update prices for each product in the order
    foreach ($items as $item) {
      $product = $item->get_product();

      if (!$product) {
        continue;
      }

      // Update the product price with current calculation
      $this->update_cart_product_price($product);
    }
  }

  /**
   * Handle order status changes
   *
   * @param int $order_id
   * @param string $old_status
   * @param string $new_status
   * @param \WC_Order $order
   */
  public function handle_order_status_change(
    int $order_id,
    string $old_status,
    string $new_status,
    \WC_Order $order,
  ): void {
    // Check if the order status changed to cancelled, refunded, or failed
    if (in_array($new_status, ['cancelled', 'refunded', 'failed'])) {
      $this->update_product_prices_for_order($order);
    }
  }

  /**
   * Handle order again functionality to ensure fresh prices
   *
   * @param array $cart_item_data
   * @param array $item
   * @param int $order_id
   * @return array
   */
  public function handle_order_again(array $cart_item_data, array $item, int $order_id): array {
    // Get the product from the cart item data
    if (isset($cart_item_data['data']) && $cart_item_data['data'] instanceof \WC_Product) {
      $product = $cart_item_data['data'];
      // Update the product price with current calculation
      $this->update_cart_product_price($product);
    }

    return $cart_item_data;
  }

  /**
   * Update cart prices before checkout to ensure fresh prices
   *
   * @param \WC_Cart $cart
   */
  public function update_cart_prices_before_checkout(\WC_Cart $cart): void {
    // Loop through cart items and update prices
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
      if (isset($cart_item['data']) && $cart_item['data'] instanceof \WC_Product) {
        $product = $cart_item['data'];
        $this->update_cart_product_price($product);
      }
    }
  }

  /**
   * AJAX handler to update cart prices
   */
  public function ajax_update_cart_prices(): void {
    // Verify nonce
    if (
      !isset($_POST['nonce']) ||
      !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mnsnp_update_prices')
    ) {
      wp_die('Security check failed');
    }

    // Update cart prices
    if (WC()->cart) {
      foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['data']) && $cart_item['data'] instanceof \WC_Product) {
          $product = $cart_item['data'];
          $this->update_cart_product_price($product);
        }
      }

      // Also refresh the cart totals
      WC()->cart->calculate_totals();
    }

    wp_send_json_success('Prices updated');
  }

  /**
   * Add JavaScript to handle Woodmart AJAX price updates
   */
  public function add_woodmart_price_update_script(): void {
    if (!is_cart() && !is_checkout() && !\MNS\NavasanPlus\is_order_pay_page()) {
      return;
    } ?>
        <script type="text/javascript">
        (function($) {
            'use strict';
            
            // Function to update prices via AJAX
            function updateCartPrices() {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'mnsnp_update_cart_prices',
                        nonce: '<?php echo wp_create_nonce('mnsnp_update_prices'); ?>'
                    },
                    success: function(response) {
                        // Trigger cart update to reflect new prices
                        $(document.body).trigger('wc_update_cart');
                    },
                    error: function(xhr, status, error) {
                        console.log('Price update failed: ' + error);
                    }
                });
            }
            
            // Listen for WooCommerce events that might affect prices
            $(document).on('added_to_cart', function() {
                updateCartPrices();
            });
            
            $(document).on('wc_fragment_refresh', function() {
                updateCartPrices();
            });
            
            // Also listen for other common WooCommerce events
            $(document).on('updated_wc_div', function() {
                updateCartPrices();
            });
            
            $(document).on('updated_cart_totals', function() {
                updateCartPrices();
            });
            
            // Also update on page load if there are items in cart
            <?php if (WC()->cart && !WC()->cart->is_empty()): ?>
            $(document).ready(function() {
                updateCartPrices();
            });
            <?php endif; ?>
            
        })(jQuery);
        </script>
        <?php
  }

  /**
   * Update product prices for an order when it's cancelled or refunded
   *
   * @param \WC_Order $order
   */
  private function update_product_prices_for_order(\WC_Order $order): void {
    // Get all items in the order
    $items = $order->get_items();

    if (empty($items)) {
      return;
    }

    // Collect product IDs to update
    $product_ids = [];

    foreach ($items as $item) {
      $product = $item->get_product();

      if (!$product) {
        continue;
      }

      $product_id = $product->get_id();

      // Add product ID to update list
      $product_ids[] = $product_id;

      // If this is a variation, also add the parent product
      if ($product->is_type('variation')) {
        $parent_id = $product->get_parent_id();
        if ($parent_id) {
          $product_ids[] = $parent_id;
        }
      }
    }

    // Remove duplicates
    $product_ids = array_unique($product_ids);

    // Update prices for each product
    foreach ($product_ids as $product_id) {
      $this->update_product_price($product_id);
    }
  }

  /**
   * Update the price for a specific product
   *
   * @param int $product_id
   */
  private function update_product_price(int $product_id): void {
    if (!class_exists(PriceCalculator::class)) {
      return;
    }

    try {
      $res = PriceCalculator::instance()->calculate($product_id);

      if ($res === null) {
        return;
      }

      $price_after = is_array($res) ? (float) ($res['price'] ?? 0) : (float) $res;
      $price_before = is_array($res)
        ? (float) ($res['price_before'] ?? $price_after)
        : $price_after;
      $p_i = (int) floor($price_after);
      $pb_i = (int) floor($price_before);
      $has_discount = $pb_i > $p_i;
      $regular_i = $has_discount ? $pb_i : $p_i;
      $sale_i = $has_discount ? $p_i : 0;

      update_post_meta($product_id, '_regular_price', $regular_i > 0 ? (string) $regular_i : '');
      update_post_meta($product_id, '_sale_price', $sale_i > 0 ? (string) $sale_i : '');
      update_post_meta(
        $product_id,
        '_price',
        $sale_i > 0 ? (string) $sale_i : ($regular_i > 0 ? (string) $regular_i : ''),
      );

      // Clear caches
      if (function_exists('wc_delete_product_transients')) {
        wc_delete_product_transients($product_id);

        // If this is a variation, also clear the parent product cache
        $product = wc_get_product($product_id);
        if ($product && $product->is_type('variation')) {
          $parent_id = $product->get_parent_id();
          if ($parent_id) {
            wc_delete_product_transients($parent_id);
          }
        }
      }
    } catch (\Throwable $e) {
      // Log error if needed, but continue processing other products
      if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Navasan Plus - Error updating product price: ' . $e->getMessage());
      }
    }
  }

  /**
   * Update cart item price when added to cart (for reorder functionality)
   *
   * @param array $cart_item_data
   * @param string $cart_item_key
   * @return array
   */
  public function update_cart_item_price(array $cart_item_data, string $cart_item_key): array {
    // Only update if we have a product
    if (!isset($cart_item_data['data']) || !$cart_item_data['data'] instanceof \WC_Product) {
      return $cart_item_data;
    }

    $product = $cart_item_data['data'];
    $product_id = $product->get_id();

    // Update the product price with current calculation
    $this->update_cart_product_price($product);

    return $cart_item_data;
  }

  /**
   * Update cart item price from session (ensures prices are fresh)
   *
   * @param array $cart_item
   * @param array $cart_item_session
   * @param string $cart_item_key
   * @return array
   */
  public function update_session_cart_item_price(
    array $cart_item,
    array $cart_item_session,
    string $cart_item_key,
  ): array {
    // Only update if we have a product
    if (!isset($cart_item['data']) || !$cart_item['data'] instanceof \WC_Product) {
      return $cart_item;
    }

    $product = $cart_item['data'];

    // Update the product price with current calculation
    $this->update_cart_product_price($product);

    return $cart_item;
  }

  /**
   * Update a product's price in the cart context
   *
   * @param \WC_Product $product
   */
  private function update_cart_product_price(\WC_Product $product): void {
    if (!class_exists(PriceCalculator::class)) {
      return;
    }

    try {
      $product_id = $product->get_id();

      $res = PriceCalculator::instance()->calculate($product_id);

      if ($res === null) {
        return;
      }

      $price_after = is_array($res) ? (float) ($res['price'] ?? 0) : (float) $res;
      $price_before = is_array($res)
        ? (float) ($res['price_before'] ?? $price_after)
        : $price_after;
      $has_discount = $price_before > $price_after;
      $regular_price = $has_discount ? $price_before : $price_after;
      $sale_price = $has_discount ? $price_after : '';

      // Update the product object with fresh prices
      $product->set_regular_price($regular_price);
      $product->set_sale_price($sale_price);
      $product->set_price($sale_price !== '' ? $sale_price : $regular_price);

      // Also update post meta for consistency
      update_post_meta($product_id, '_regular_price', $regular_price);
      update_post_meta($product_id, '_sale_price', $sale_price);
      update_post_meta($product_id, '_price', $sale_price !== '' ? $sale_price : $regular_price);

      // Clear product transients to ensure fresh data
      if (function_exists('wc_delete_product_transients')) {
        wc_delete_product_transients($product_id);
      }
    } catch (\Throwable $e) {
      // Log error if needed
      if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Navasan Plus - Error updating cart product price: ' . $e->getMessage());
      }
    }
  }
}
