<?php
namespace MNS\NavasanPlus\Services;

if (!defined('ABSPATH')) {
  exit();
}

use MNS\NavasanPlus\DB;

/**
 * Handles time-based discount functionality
 */
final class TimeBasedDiscounts {
  /**
   * Hook into WordPress
   */
  public function run(): void {
    // Schedule cron job for discount expiration
    add_action('init', [$this, 'schedule_discount_expiration_cron']);
    add_action('mnsnp_discount_expiration_cron', [$this, 'process_expired_discounts']);

    // Check if discount is active before applying
    add_filter(
      'mnsnp/discounts/override_values',
      [$this, 'maybe_disable_expired_discounts'],
      10,
      2,
    );

    // Add time fields to discount metabox via hooks
    add_action('mnsnp_discount_box_after_fields', [$this, 'add_time_fields']);
    add_action('mnsnp_save_discount_box', [$this, 'save_time_fields']);

    // CRITICAL: Set sale dates on product object BEFORE WooCommerce saves
    // This is the key to single-save functionality
    add_action('woocommerce_admin_process_product_object', [$this, 'set_sale_dates_on_product_object'], 10);

    // Protect our sale date meta from being cleared by WooCommerce
    add_filter('update_post_metadata', [$this, 'protect_sale_date_meta'], 10, 5);

    // Legacy: Inject into $_POST as backup
    add_action('woocommerce_process_product_meta', [$this, 'inject_times_into_post'], 1);

    // Re-sync AGGRESSIVELY after WooCommerce finishes (run VERY last)
    add_action('woocommerce_process_product_meta', [$this, 'resync_after_woocommerce_save'], 999);

    // Add time fields to category discount forms via hooks
    add_action('mnsnp_category_discount_add_fields', [$this, 'add_category_time_fields']);
    add_action('mnsnp_category_discount_edit_fields', [$this, 'edit_category_time_fields']);
    add_action('mnsnp_save_category_discount_fields', [$this, 'save_category_time_fields']);

    // Listen for WooCommerce sale schedule changes
    add_action('updated_post_meta', [$this, 'handle_woocommerce_sale_schedule_change'], 10, 4);
    add_action('added_post_meta', [$this, 'handle_woocommerce_sale_schedule_change'], 10, 4);

    // Add countdown timer to frontend
    add_action('woocommerce_single_product_summary', [$this, 'display_sale_countdown'], 25);
    add_action('woocommerce_shop_loop_item_title', [$this, 'display_archive_sale_countdown'], 15);

    // AJAX handlers for manual sync buttons
    add_action('wp_ajax_mnsnp_sync_wc_dates', [$this, 'ajax_sync_wc_dates']);
    add_action('wp_ajax_mnsnp_sync_category_wc_dates', [$this, 'ajax_sync_category_wc_dates']);
  }

  /**
   * Schedule cron job for discount expiration
   */
  public function schedule_discount_expiration_cron(): void {
    if (!wp_next_scheduled('mnsnp_discount_expiration_cron')) {
      wp_schedule_event(time(), 'hourly', 'mnsnp_discount_expiration_cron');
    }
  }

  /**
   * Add time fields to discount metabox
   */
  public function add_time_fields($post): void {
    $db = DB::instance();
    $discount_start = get_post_meta($post->ID, $db->full_meta_key('discount_start_time'), true);
    $discount_end = get_post_meta($post->ID, $db->full_meta_key('discount_end_time'), true);
    ?>
        <!-- Time-based discount fields -->
        <p>
            <label for="mnsnp_discount_start_time"><?php _e(
              'Discount Start Time',
              'mns-navasan-plus',
            ); ?></label>
            <input type="datetime-local" name="mnsnp_discount[start_time]" id="mnsnp_discount_start_time" value="<?php echo esc_attr(
              $discount_start,
            ); ?>" class="widefat">
            <span class="description"><?php _e(
              'Leave blank for no start time restriction',
              'mns-navasan-plus',
            ); ?></span>
        </p>
        <p>
            <label for="mnsnp_discount_end_time"><?php _e(
              'Discount End Time',
              'mns-navasan-plus',
            ); ?></label>
            <input type="datetime-local" name="mnsnp_discount[end_time]" id="mnsnp_discount_end_time" value="<?php echo esc_attr(
              $discount_end,
            ); ?>" class="widefat">
            <span class="description"><?php _e(
              'Leave blank for no end time (permanent discount)',
              'mns-navasan-plus',
            ); ?></span>
        </p>
        <p>
            <button type="button" id="mnsnp_sync_to_woocommerce" class="button button-secondary" style="width: 100%;">
                <?php _e('↻ Sync to WooCommerce Schedule', 'mns-navasan-plus'); ?>
            </button>
            <span class="description" style="display: block; margin-top: 5px;">
                <?php _e('Click to sync these times to WooCommerce sale schedule', 'mns-navasan-plus'); ?>
            </span>
            <span id="mnsnp_sync_status" style="display: none; margin-top: 5px;"></span>
        </p>
        <script>
        jQuery(document).ready(function($) {
            $('#mnsnp_sync_to_woocommerce').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                var status = $('#mnsnp_sync_status');
                var startTime = $('#mnsnp_discount_start_time').val();
                var endTime = $('#mnsnp_discount_end_time').val();
                var productId = <?php echo absint($post->ID); ?>;

                if (!startTime && !endTime) {
                    status.html('<span style="color: #d63638;">⚠ Please set start and/or end time first</span>').show();
                    return;
                }

                btn.prop('disabled', true).text('<?php _e('Syncing...', 'mns-navasan-plus'); ?>');
                status.html('<span style="color: #2271b1;">⏳ Syncing...</span>').show();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mnsnp_sync_wc_dates',
                        product_id: productId,
                        start_time: startTime,
                        end_time: endTime,
                        nonce: '<?php echo wp_create_nonce('mnsnp_sync_wc_dates'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            status.html('<span style="color: #00a32a;">✓ ' + response.data.message + '</span>');
                            btn.prop('disabled', false).text('<?php _e('↻ Sync to WooCommerce Schedule', 'mns-navasan-plus'); ?>');
                            setTimeout(function() { status.fadeOut(); }, 3000);
                        } else {
                            status.html('<span style="color: #d63638;">✗ ' + response.data.message + '</span>');
                            btn.prop('disabled', false).text('<?php _e('↻ Sync to WooCommerce Schedule', 'mns-navasan-plus'); ?>');
                        }
                    },
                    error: function() {
                        status.html('<span style="color: #d63638;">✗ <?php _e('Error syncing. Please try again.', 'mns-navasan-plus'); ?></span>');
                        btn.prop('disabled', false).text('<?php _e('↻ Sync to WooCommerce Schedule', 'mns-navasan-plus'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
  }

  /**
   * Save time fields
   */
  public function save_time_fields(int $post_id): void {
    if (!isset($_POST['mnsnp_discount']) || !is_array($_POST['mnsnp_discount'])) {
      return;
    }

    $in = wp_unslash($_POST['mnsnp_discount']);
    $db = DB::instance();

    // Save time-based discount fields
    $start_time = sanitize_text_field($in['start_time'] ?? '');
    $end_time = sanitize_text_field($in['end_time'] ?? '');

    update_post_meta($post_id, $db->full_meta_key('discount_start_time'), $start_time);
    update_post_meta($post_id, $db->full_meta_key('discount_end_time'), $end_time);

    // Sync with WooCommerce sale schedule
    $this->sync_product_with_woocommerce_sale_schedule($post_id, $start_time, $end_time);
  }

  /**
   * Set sale dates directly on WooCommerce product object BEFORE it's saved
   * This is the most reliable way to ensure single-save functionality
   * Runs on woocommerce_admin_process_product_object hook
   */
  public function set_sale_dates_on_product_object($product): void {
    // Check if we have discount times in the POST data
    if (!isset($_POST['mnsnp_discount']) || !is_array($_POST['mnsnp_discount'])) {
      return;
    }

    $in = wp_unslash($_POST['mnsnp_discount']);
    $start_time = sanitize_text_field($in['start_time'] ?? '');
    $end_time = sanitize_text_field($in['end_time'] ?? '');

    if (!$start_time && !$end_time) {
      return; // No times to set
    }

    // Convert to timestamps
    $start_timestamp = $start_time ? strtotime($start_time) : null;
    $end_timestamp = $end_time ? strtotime($end_time) : null;

    // Set the sale dates on the product object
    // WooCommerce will save these when it saves the product
    if ($start_timestamp) {
      $product->set_date_on_sale_from($start_timestamp);
    } else {
      $product->set_date_on_sale_from('');
    }

    if ($end_timestamp) {
      $product->set_date_on_sale_to($end_timestamp);
    } else {
      $product->set_date_on_sale_to('');
    }

    // CRITICAL: Also save to meta immediately so WooCommerce doesn't clear it
    $product_id = $product->get_id();
    if ($start_timestamp) {
      update_post_meta($product_id, '_sale_price_dates_from', $start_timestamp);
    } else {
      delete_post_meta($product_id, '_sale_price_dates_from');
    }

    if ($end_timestamp) {
      update_post_meta($product_id, '_sale_price_dates_to', $end_timestamp);
    } else {
      delete_post_meta($product_id, '_sale_price_dates_to');
    }

    // Clear product cache so WooCommerce picks up the new values
    if (function_exists('wc_delete_product_transients')) {
      wc_delete_product_transients($product_id);
    }
  }

  /**
   * Protect our sale date meta from being cleared
   * Prevents WooCommerce from overwriting with empty values
   */
  public function protect_sale_date_meta($check, $object_id, $meta_key, $meta_value, $prev_value) {
    // Only protect during product saves
    if (!doing_action('woocommerce_process_product_meta')) {
      return $check; // Allow normal updates
    }

    // Only protect sale date fields
    if (!in_array($meta_key, ['_sale_price_dates_from', '_sale_price_dates_to'])) {
      return $check; // Allow other meta to update normally
    }

    // If WooCommerce is trying to set empty value, but we have a value set, prevent it
    if (empty($meta_value)) {
      $db = DB::instance();
      $our_start = get_post_meta($object_id, $db->full_meta_key('discount_start_time'), true);
      $our_end = get_post_meta($object_id, $db->full_meta_key('discount_end_time'), true);

      // If we have times set in our plugin, don't let WooCommerce clear them
      if (($meta_key === '_sale_price_dates_from' && $our_start) ||
          ($meta_key === '_sale_price_dates_to' && $our_end)) {
        return false; // Prevent the update (keep existing value)
      }
    }

    return $check; // Allow the update
  }

  /**
   * Inject our discount times into $_POST before WooCommerce processes
   * This prevents WooCommerce from clearing the sale date fields
   * Runs at priority 1 (very early)
   */
  public function inject_times_into_post(int $product_id): void {
    // Check if we have discount times set
    if (!isset($_POST['mnsnp_discount']) || !is_array($_POST['mnsnp_discount'])) {
      return;
    }

    $in = wp_unslash($_POST['mnsnp_discount']);
    $start_time = sanitize_text_field($in['start_time'] ?? '');
    $end_time = sanitize_text_field($in['end_time'] ?? '');

    if (!$start_time && !$end_time) {
      return; // No times to inject
    }

    // Convert to timestamps
    $start_timestamp = $start_time ? strtotime($start_time) : '';
    $end_timestamp = $end_time ? strtotime($end_time) : '';

    // Inject into $_POST so WooCommerce sees them
    if ($start_timestamp) {
      $_POST['_sale_price_dates_from'] = $start_timestamp;
      $_POST['_sale_price_dates_from_checkbox'] = 'yes'; // Tell WooCommerce the field is active
    }

    if ($end_timestamp) {
      $_POST['_sale_price_dates_to'] = $end_timestamp;
      $_POST['_sale_price_dates_to_checkbox'] = 'yes'; // Tell WooCommerce the field is active
    }
  }

  /**
   * Re-sync after WooCommerce processes product meta
   * This runs at priority 99 to ensure WooCommerce doesn't clear our values
   */
  public function resync_after_woocommerce_save(int $product_id): void {
    // Get our saved times
    $db = DB::instance();
    $start_time = get_post_meta($product_id, $db->full_meta_key('discount_start_time'), true);
    $end_time = get_post_meta($product_id, $db->full_meta_key('discount_end_time'), true);

    // If we have times set, re-apply them to WooCommerce fields
    if ($start_time || $end_time) {
      $this->sync_product_with_woocommerce_sale_schedule($product_id, $start_time, $end_time);
    }
  }

  /**
   * Re-sync after WooCommerce product object is fully updated
   * This is the final hook that fires after everything is saved
   */
  public function resync_after_product_update($product): void {
    // Prevent infinite loops
    static $processing = [];
    $product_id = is_numeric($product) ? $product : $product->get_id();

    if (isset($processing[$product_id])) {
      return; // Already processing this product
    }
    $processing[$product_id] = true;

    // Get our saved times
    $db = DB::instance();
    $start_time = get_post_meta($product_id, $db->full_meta_key('discount_start_time'), true);
    $end_time = get_post_meta($product_id, $db->full_meta_key('discount_end_time'), true);

    // If we have times set, use WooCommerce's data store to save directly to product object
    if ($start_time || $end_time) {
      // Convert to timestamps
      $start_timestamp = $start_time ? strtotime($start_time) : '';
      $end_timestamp = $end_time ? strtotime($end_time) : '';

      // Get the product object if we don't have it
      if (is_numeric($product)) {
        $product = wc_get_product($product_id);
      }

      if ($product) {
        // Check if dates are already set correctly
        $current_from = $product->get_date_on_sale_from('edit');
        $current_to = $product->get_date_on_sale_to('edit');

        $current_from_ts = $current_from ? $current_from->getTimestamp() : 0;
        $current_to_ts = $current_to ? $current_to->getTimestamp() : 0;

        // Only update if they're different
        $needs_update = false;
        if ($start_timestamp && $start_timestamp != $current_from_ts) {
          $product->set_date_on_sale_from($start_timestamp);
          $needs_update = true;
        }
        if ($end_timestamp && $end_timestamp != $current_to_ts) {
          $product->set_date_on_sale_to($end_timestamp);
          $needs_update = true;
        }

        // Only save if something changed
        if ($needs_update) {
          $product->save();
        }
      }
    }

    unset($processing[$product_id]);
  }

  /**
   * Sync product time fields with WooCommerce sale scheduling
   *
   * Note: WooCommerce's date picker only shows dates (not time), but we support full datetime.
   * We preserve the exact time in our plugin fields while syncing the timestamp to WooCommerce.
   */
  private function sync_product_with_woocommerce_sale_schedule(
    int $product_id,
    string $start_time,
    string $end_time,
  ): void {
    // Prevent infinite loops by checking if we're already syncing
    static $syncing = false;
    if ($syncing) {
      return;
    }
    $syncing = true;

    // Convert our datetime-local format to WooCommerce timestamp format
    // Our format: Y-m-d\TH:i (e.g., "2025-01-15T14:30")
    // WooCommerce stores: Unix timestamp (e.g., 1736951400)
    $start_timestamp = '';
    $end_timestamp = '';

    if ($start_time) {
      $start_timestamp = strtotime($start_time);
      if ($start_timestamp === false) {
        $start_timestamp = '';
      }
    }

    if ($end_time) {
      $end_timestamp = strtotime($end_time);
      if ($end_timestamp === false) {
        $end_timestamp = '';
      }
    }

    // Update WooCommerce sale schedule meta fields
    // Important: WooCommerce expects timestamps, even though its UI only shows dates
    if ($start_timestamp) {
      update_post_meta($product_id, '_sale_price_dates_from', $start_timestamp);
    } else {
      delete_post_meta($product_id, '_sale_price_dates_from');
    }

    if ($end_timestamp) {
      update_post_meta($product_id, '_sale_price_dates_to', $end_timestamp);
    } else {
      delete_post_meta($product_id, '_sale_price_dates_to');
    }

    // Clear transients to ensure WooCommerce updates the product display
    if (function_exists('wc_delete_product_transients')) {
      wc_delete_product_transients($product_id);
    }

    $syncing = false;
  }

  /**
   * Handle WooCommerce sale schedule changes and sync back to our fields
   */
  public function handle_woocommerce_sale_schedule_change(
    $meta_id,
    $post_id,
    $meta_key,
    $meta_value,
  ): void {
    // Prevent infinite loops by checking if we're already syncing
    static $syncing = false;
    if ($syncing) {
      return;
    }

    // Only handle WooCommerce sale schedule meta keys
    if (!in_array($meta_key, ['_sale_price_dates_from', '_sale_price_dates_to'])) {
      return;
    }

    // Only for products and variations
    if (!in_array(get_post_type($post_id), ['product', 'product_variation'])) {
      return;
    }

    $syncing = true;

    $db = DB::instance();

    // Get current values from our fields
    $our_start_time = get_post_meta($post_id, $db->full_meta_key('discount_start_time'), true);
    $our_end_time = get_post_meta($post_id, $db->full_meta_key('discount_end_time'), true);

    // Convert WooCommerce timestamps back to our datetime format
    if ($meta_key === '_sale_price_dates_from') {
      $new_start_time = $meta_value ? date('Y-m-d\TH:i', $meta_value) : '';
      // Only update if different to avoid infinite loops
      if ($new_start_time !== $our_start_time) {
        update_post_meta($post_id, $db->full_meta_key('discount_start_time'), $new_start_time);
      }
    } elseif ($meta_key === '_sale_price_dates_to') {
      $new_end_time = $meta_value ? date('Y-m-d\TH:i', $meta_value) : '';
      // Only update if different to avoid infinite loops
      if ($new_end_time !== $our_end_time) {
        update_post_meta($post_id, $db->full_meta_key('discount_end_time'), $new_end_time);
      }
    }

    $syncing = false;
  }

  /**
   * Display sale countdown on single product page
   */
  public function display_sale_countdown(): void {
    global $product;

    if (!$product) {
      return;
    }

    $this->render_countdown_timer($product->get_id());
  }

  /**
   * Display sale countdown on archive pages
   */
  public function display_archive_sale_countdown(): void {
    global $product;

    if (!$product) {
      return;
    }

    $this->render_countdown_timer($product->get_id(), true);
  }

  /**
   * Render countdown timer
   */
  private function render_countdown_timer(int $product_id, bool $is_archive = false): void {
    $db = DB::instance();
    $start_time = get_post_meta($product_id, $db->full_meta_key('discount_start_time'), true);
    $end_time = get_post_meta($product_id, $db->full_meta_key('discount_end_time'), true);

    // Check if discount is active
    if (!$this->is_discount_active($product_id)) {
      return;
    }

    $now = time();
    $start_timestamp = $start_time ? strtotime($start_time) : 0;
    $end_timestamp = $end_time ? strtotime($end_time) : 0;

    // Determine which time to show countdown for
    $target_timestamp = 0;
    $countdown_type = '';

    if ($start_timestamp > $now) {
      // Countdown to start
      $target_timestamp = $start_timestamp;
      $countdown_type = 'start';
    } elseif ($end_timestamp > $now) {
      // Countdown to end
      $target_timestamp = $end_timestamp;
      $countdown_type = 'end';
    }

    if ($target_timestamp <= 0) {
      return;
    }

    $time_diff = $target_timestamp - $now;

    // Only show countdown if less than 30 days
    if ($time_diff > 30 * DAY_IN_SECONDS) {
      return;
    }

    $days = floor($time_diff / DAY_IN_SECONDS);
    $hours = floor(($time_diff % DAY_IN_SECONDS) / HOUR_IN_SECONDS);
    $minutes = floor(($time_diff % HOUR_IN_SECONDS) / MINUTE_IN_SECONDS);
    $seconds = $time_diff % MINUTE_IN_SECONDS;

    $message = '';
    if ($countdown_type === 'start') {
      $message = __('Sale starts in:', 'mns-navasan-plus');
    } else {
      $message = __('Sale ends in:', 'mns-navasan-plus');
    }

    $wrapper_class = $is_archive ? 'mnsnp-archive-countdown' : 'mnsnp-single-countdown';
    ?>
    <div class="mnsnp-sale-countdown <?php echo esc_attr(
      $wrapper_class,
    ); ?>" data-end-time="<?php echo esc_attr($target_timestamp); ?>">
      <div class="mnsnp-countdown-message"><?php echo esc_html($message); ?></div>
      <div class="mnsnp-countdown-timer">
        <span class="mnsnp-days"><?php echo esc_html(
          $days,
        ); ?><span class="mnsnp-label"><?php _e('Days', 'mns-navasan-plus'); ?></span></span>
        <span class="mnsnp-hours"><?php echo esc_html(
          $hours,
        ); ?><span class="mnsnp-label"><?php _e('Hours', 'mns-navasan-plus'); ?></span></span>
        <span class="mnsnp-minutes"><?php echo esc_html(
          $minutes,
        ); ?><span class="mnsnp-label"><?php _e('Mins', 'mns-navasan-plus'); ?></span></span>
        <span class="mnsnp-seconds"><?php echo esc_html(
          $seconds,
        ); ?><span class="mnsnp-label"><?php _e('Secs', 'mns-navasan-plus'); ?></span></span>
      </div>
    </div>
    <?php
  }

  /**
   * Check if discount is currently active
   */
  private function is_discount_active(int $product_id): bool {
    // Bypass time restrictions during category updates
    if (!empty($GLOBALS['mnsnp_category_update_in_progress'])) {
      return true;
    }

    $db = DB::instance();
    $start_time = get_post_meta($product_id, $db->full_meta_key('discount_start_time'), true);
    $end_time = get_post_meta($product_id, $db->full_meta_key('discount_end_time'), true);

    $now = time();

    // Check start time
    if ($start_time && strtotime($start_time) > $now) {
      return false; // Discount hasn't started yet
    }

    // Check end time
    if ($end_time && strtotime($end_time) < $now) {
      return false; // Discount has expired
    }

    // Check category time restrictions
    $terms = get_the_terms($product_id, 'product_cat');
    if (!empty($terms) && is_array($terms)) {
      foreach ($terms as $term) {
        if (!$this->is_category_discount_active($term->term_id)) {
          return false; // Category discount is not active
        }
      }
    }

    return true; // Discount is active
  }

  /**
   * Disable expired discounts
   */
  public function maybe_disable_expired_discounts($override, int $product_id) {
    // If discount is not active, return empty array to disable discounts
    if (!$this->is_discount_active($product_id)) {
      return [
        'profit_percentage' => 0,
        'profit_fixed' => 0,
        'charge_percentage' => 0,
        'charge_fixed' => 0,
      ];
    }

    return $override;
  }

  /**
   * Process expired discounts (for backward compatibility and cleanup)
   */
  public function process_expired_discounts(): void {
    // Get all products with discount end times
    $args = [
      'post_type' => ['product', 'product_variation'],
      'posts_per_page' => -1,
      'meta_query' => [
        [
          'key' => '_mns_navasan_plus_discount_end_time',
          'value' => '',
          'compare' => '!=',
        ],
      ],
    ];

    $products = get_posts($args);

    foreach ($products as $product) {
      $end_time = get_post_meta($product->ID, '_mns_navasan_plus_discount_end_time', true);

      // Check if end time is in the past
      if ($end_time && strtotime($end_time) < time()) {
        // For backward compatibility, we can clear the discount values
        // but the main logic now uses the filter approach above
      }
    }

    // Also check categories with discount end times
    $categories = get_terms([
      'taxonomy' => 'product_cat',
      'hide_empty' => false,
      'meta_query' => [
        [
          'key' => 'discount_end_time',
          'value' => '',
          'compare' => '!=',
        ],
      ],
    ]);

    // No action needed for categories as the main logic uses filters
  }

  /**
   * Add time fields to category "Add New" form
   */
  public function add_category_time_fields($taxonomy): void {
    ?>
      <div class="form-field term-discount-start-time-wrap">
          <label for="mnsnp_discount_start_time"><?php _e(
            'Discount Start Time',
            'mns-navasan-plus',
          ); ?></label>
          <input type="datetime-local" name="mnsnp_discount[start_time]" id="mnsnp_discount_start_time" value="" class="widefat">
          <p class="description"><?php _e(
            'Leave blank for no start time restriction',
            'mns-navasan-plus',
          ); ?></p>
      </div>
      <div class="form-field term-discount-end-time-wrap">
          <label for="mnsnp_discount_end_time"><?php _e(
            'Discount End Time',
            'mns-navasan-plus',
          ); ?></label>
          <input type="datetime-local" name="mnsnp_discount[end_time]" id="mnsnp_discount_end_time" value="" class="widefat">
          <p class="description"><?php _e(
            'Leave blank for no end time (permanent discount)',
            'mns-navasan-plus',
          ); ?></p>
      </div>
      <?php
  }

  /**
   * Add time fields to category "Edit" form
   */
  public function edit_category_time_fields(\WP_Term $term): void {
    $discount_start = get_term_meta($term->term_id, 'discount_start_time', true);
    $discount_end = get_term_meta($term->term_id, 'discount_end_time', true);
    ?>
      <tr class="form-field term-discount-start-time-wrap">
          <th scope="row"><label for="mnsnp_discount_start_time"><?php _e(
            'Discount Start Time',
            'mns-navasan-plus',
          ); ?></label></th>
          <td>
              <input type="datetime-local" name="mnsnp_discount[start_time]" id="mnsnp_discount_start_time" value="<?php echo esc_attr(
                $discount_start,
              ); ?>" class="widefat">
              <p class="description"><?php _e(
                'Leave blank for no start time restriction',
                'mns-navasan-plus',
              ); ?></p>
          </td>
      </tr>
      <tr class="form-field term-discount-end-time-wrap">
          <th scope="row"><label for="mnsnp_discount_end_time"><?php _e(
            'Discount End Time',
            'mns-navasan-plus',
          ); ?></label></th>
          <td>
              <input type="datetime-local" name="mnsnp_discount[end_time]" id="mnsnp_discount_end_time" value="<?php echo esc_attr(
                $discount_end,
              ); ?>" class="widefat">
              <p class="description"><?php _e(
                'Leave blank for no end time (permanent discount)',
                'mns-navasan-plus',
              ); ?></p>
          </td>
      </tr>
      <tr class="form-field">
          <th scope="row"><?php _e('Sync to WooCommerce', 'mns-navasan-plus'); ?></th>
          <td>
              <button type="button" id="mnsnp_sync_category_to_woocommerce" class="button button-primary">
                  <?php _e('↻ Sync All Products to WooCommerce', 'mns-navasan-plus'); ?>
              </button>
              <p class="description">
                  <?php _e('Click to sync these times to WooCommerce schedule for ALL products in this category', 'mns-navasan-plus'); ?>
              </p>
              <div id="mnsnp_category_sync_status" style="display: none; margin-top: 10px;"></div>
          </td>
      </tr>
      <script>
      jQuery(document).ready(function($) {
          $('#mnsnp_sync_category_to_woocommerce').on('click', function(e) {
              e.preventDefault();
              var btn = $(this);
              var status = $('#mnsnp_category_sync_status');
              var startTime = $('#mnsnp_discount_start_time').val();
              var endTime = $('#mnsnp_discount_end_time').val();
              var termId = <?php echo absint($term->term_id); ?>;

              if (!startTime && !endTime) {
                  status.html('<div class="notice notice-warning inline"><p>⚠ Please set start and/or end time first</p></div>').show();
                  return;
              }

              btn.prop('disabled', true).text('<?php _e('Syncing...', 'mns-navasan-plus'); ?>');
              status.html('<div class="notice notice-info inline"><p>⏳ Syncing all products in this category...</p></div>').show();

              $.ajax({
                  url: ajaxurl,
                  type: 'POST',
                  data: {
                      action: 'mnsnp_sync_category_wc_dates',
                      term_id: termId,
                      start_time: startTime,
                      end_time: endTime,
                      nonce: '<?php echo wp_create_nonce('mnsnp_sync_category_wc_dates'); ?>'
                  },
                  success: function(response) {
                      if (response.success) {
                          status.html('<div class="notice notice-success inline"><p>✓ ' + response.data.message + '</p></div>');
                          btn.prop('disabled', false).text('<?php _e('↻ Sync All Products to WooCommerce', 'mns-navasan-plus'); ?>');
                      } else {
                          status.html('<div class="notice notice-error inline"><p>✗ ' + response.data.message + '</p></div>');
                          btn.prop('disabled', false).text('<?php _e('↻ Sync All Products to WooCommerce', 'mns-navasan-plus'); ?>');
                      }
                  },
                  error: function() {
                      status.html('<div class="notice notice-error inline"><p>✗ <?php _e('Error syncing. Please try again.', 'mns-navasan-plus'); ?></p></div>');
                      btn.prop('disabled', false).text('<?php _e('↻ Sync All Products to WooCommerce', 'mns-navasan-plus'); ?>');
                  }
              });
          });
      });
      </script>
      <?php
  }

  /**
   * Save time fields for categories
   */
  public function save_category_time_fields(int $term_id): void {
    if (!isset($_POST['mnsnp_discount']) || !is_array($_POST['mnsnp_discount'])) {
      return;
    }

    $in = wp_unslash($_POST['mnsnp_discount']);

    // Save time-based discount fields
    $start_time = sanitize_text_field($in['start_time'] ?? '');
    $end_time = sanitize_text_field($in['end_time'] ?? '');

    update_term_meta($term_id, 'discount_start_time', $start_time);
    update_term_meta($term_id, 'discount_end_time', $end_time);

    // Also update all products in this category with the same time fields
    $this->update_products_with_category_times($term_id, $start_time, $end_time);
  }

  /**
   * Update all products in a category with the category's time fields
   */
  private function update_products_with_category_times(
    int $term_id,
    string $start_time,
    string $end_time,
  ): void {
    // Get all products AND variations in the category.
    $product_ids = get_posts([
      'post_type' => ['product', 'product_variation'],
      'posts_per_page' => -1,
      'fields' => 'ids',
      'suppress_filters' => true,
      'tax_query' => [
        [
          'taxonomy' => 'product_cat',
          'field' => 'term_id',
          'terms' => $term_id,
        ],
      ],
    ]);

    if (empty($product_ids)) {
      return;
    }

    $db = DB::instance();

    // Update each product with the category time fields
    foreach ($product_ids as $product_id) {
      update_post_meta($product_id, $db->full_meta_key('discount_start_time'), $start_time);
      update_post_meta($product_id, $db->full_meta_key('discount_end_time'), $end_time);

      // Sync with WooCommerce sale schedule
      $this->sync_product_with_woocommerce_sale_schedule($product_id, $start_time, $end_time);
    }
  }

  /**
   * Check if category discount is currently active
   */
  private function is_category_discount_active(int $term_id): bool {
    $start_time = get_term_meta($term_id, 'discount_start_time', true);
    $end_time = get_term_meta($term_id, 'discount_end_time', true);

    $now = time();

    // Check start time
    if ($start_time && strtotime($start_time) > $now) {
      return false; // Discount hasn't started yet
    }

    // Check end time
    if ($end_time && strtotime($end_time) < $now) {
      return false; // Discount has expired
    }

    return true; // Discount is active
  }

  /**
   * AJAX handler for manual WooCommerce sync button
   */
  public function ajax_sync_wc_dates(): void {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mnsnp_sync_wc_dates')) {
      wp_send_json_error(['message' => __('Security check failed', 'mns-navasan-plus')]);
    }

    // Check permissions
    if (!current_user_can('edit_products')) {
      wp_send_json_error(['message' => __('Permission denied', 'mns-navasan-plus')]);
    }

    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $start_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
    $end_time = isset($_POST['end_time']) ? sanitize_text_field($_POST['end_time']) : '';

    if (!$product_id) {
      wp_send_json_error(['message' => __('Invalid product ID', 'mns-navasan-plus')]);
    }

    // Save to plugin meta
    $db = DB::instance();
    update_post_meta($product_id, $db->full_meta_key('discount_start_time'), $start_time);
    update_post_meta($product_id, $db->full_meta_key('discount_end_time'), $end_time);

    // Convert to timestamps and save to WooCommerce meta
    $start_timestamp = $start_time ? strtotime($start_time) : '';
    $end_timestamp = $end_time ? strtotime($end_time) : '';

    if ($start_timestamp) {
      update_post_meta($product_id, '_sale_price_dates_from', $start_timestamp);
    } else {
      delete_post_meta($product_id, '_sale_price_dates_from');
    }

    if ($end_timestamp) {
      update_post_meta($product_id, '_sale_price_dates_to', $end_timestamp);
    } else {
      delete_post_meta($product_id, '_sale_price_dates_to');
    }

    // Update product object for immediate effect
    $product = wc_get_product($product_id);
    if ($product) {
      if ($start_timestamp) {
        $product->set_date_on_sale_from($start_timestamp);
      }
      if ($end_timestamp) {
        $product->set_date_on_sale_to($end_timestamp);
      }
      $product->save();
    }

    // Clear cache
    if (function_exists('wc_delete_product_transients')) {
      wc_delete_product_transients($product_id);
    }

    wp_send_json_success([
      'message' => __('Synced! Dates will appear in WooCommerce after you save the product.', 'mns-navasan-plus'),
      'start_timestamp' => $start_timestamp,
      'end_timestamp' => $end_timestamp
    ]);
  }

  /**
   * AJAX handler for category bulk sync button
   */
  public function ajax_sync_category_wc_dates(): void {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mnsnp_sync_category_wc_dates')) {
      wp_send_json_error(['message' => __('Security check failed', 'mns-navasan-plus')]);
    }

    // Check permissions
    if (!current_user_can('manage_categories')) {
      wp_send_json_error(['message' => __('Permission denied', 'mns-navasan-plus')]);
    }

    $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
    $start_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
    $end_time = isset($_POST['end_time']) ? sanitize_text_field($_POST['end_time']) : '';

    if (!$term_id) {
      wp_send_json_error(['message' => __('Invalid category ID', 'mns-navasan-plus')]);
    }

    // Save to category meta
    update_term_meta($term_id, 'discount_start_time', $start_time);
    update_term_meta($term_id, 'discount_end_time', $end_time);

    // Get all products in this category
    $product_ids = get_posts([
      'post_type' => ['product', 'product_variation'],
      'posts_per_page' => -1,
      'fields' => 'ids',
      'suppress_filters' => true,
      'tax_query' => [
        [
          'taxonomy' => 'product_cat',
          'field' => 'term_id',
          'terms' => $term_id,
        ],
      ],
    ]);

    if (empty($product_ids)) {
      wp_send_json_error(['message' => __('No products found in this category', 'mns-navasan-plus')]);
    }

    $db = DB::instance();
    $synced_count = 0;

    // Convert to timestamps once
    $start_timestamp = $start_time ? strtotime($start_time) : '';
    $end_timestamp = $end_time ? strtotime($end_time) : '';

    // Sync each product
    foreach ($product_ids as $product_id) {
      // Save to plugin meta
      update_post_meta($product_id, $db->full_meta_key('discount_start_time'), $start_time);
      update_post_meta($product_id, $db->full_meta_key('discount_end_time'), $end_time);

      // Save to WooCommerce meta
      if ($start_timestamp) {
        update_post_meta($product_id, '_sale_price_dates_from', $start_timestamp);
      } else {
        delete_post_meta($product_id, '_sale_price_dates_from');
      }

      if ($end_timestamp) {
        update_post_meta($product_id, '_sale_price_dates_to', $end_timestamp);
      } else {
        delete_post_meta($product_id, '_sale_price_dates_to');
      }

      // Update product object
      $product = wc_get_product($product_id);
      if ($product) {
        if ($start_timestamp) {
          $product->set_date_on_sale_from($start_timestamp);
        }
        if ($end_timestamp) {
          $product->set_date_on_sale_to($end_timestamp);
        }
        $product->save();
      }

      // Clear cache
      if (function_exists('wc_delete_product_transients')) {
        wc_delete_product_transients($product_id);
      }

      $synced_count++;
    }

    wp_send_json_success([
      'message' => sprintf(
        __('Synced %d products! Save the category to finalize.', 'mns-navasan-plus'),
        $synced_count
      ),
      'synced_count' => $synced_count
    ]);
  }
}
