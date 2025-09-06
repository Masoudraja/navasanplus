<?php
namespace MNS\NavasanPlus\Services;

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\DB;

/**
 * Connects RateSync to PriceCalculator.
 * Automatically finds and updates product prices when a currency rate changes.
 */
final class AutoPriceUpdater {

    /**
     * The hook for the one-off cron job that processes the update queue.
     */
    private const QUEUE_CRON_HOOK = 'mnsnp_process_price_update_queue';

    /**
     * The transient key used to store the queue of product IDs to update.
     */
    private const QUEUE_TRANSIENT_KEY = 'mnsnp_price_update_queue';

    /**
     * Hook into WordPress.
     */
    public function run(): void {
        // Listen for the signal from RateSync that a currency has been updated.
        add_action( 'mnsnp/rate_sync/updated_currency', [ $this, 'schedule_product_updates' ], 10, 1 );

        // Add the action that our one-off cron job will execute.
        add_action( self::QUEUE_CRON_HOOK, [ $this, 'process_price_update_queue' ] );
    }

    /**
     * Called when a currency is updated. Finds affected products and adds them to a queue.
     *
     * @param int $currency_id The post ID of the currency that was updated.
     */
    public function schedule_product_updates( int $currency_id ): void {
        if ( $currency_id <= 0 ) {
            return;
        }

        // Find all products that are affected by this currency change.
        $product_ids_to_update = $this->find_affected_products( $currency_id );

        if ( empty( $product_ids_to_update ) ) {
            return; // No products use this currency.
        }

        // Get the existing queue from the transient.
        $queue = get_transient( self::QUEUE_TRANSIENT_KEY );
        $queue = is_array( $queue ) ? $queue : [];

        // Add new product IDs to the queue and remove duplicates.
        $new_queue = array_unique( array_merge( $queue, $product_ids_to_update ) );

        // Save the updated queue back to the transient. It will expire in 1 hour for safety.
        set_transient( self::QUEUE_TRANSIENT_KEY, $new_queue, HOUR_IN_SECONDS );

        // Schedule a one-off event to process this queue in 2 minutes.
        // We check if it's already scheduled to avoid creating duplicate events.
        if ( ! wp_next_scheduled( self::QUEUE_CRON_HOOK ) ) {
            wp_schedule_single_event( time() + ( 2 * MINUTE_IN_SECONDS ), self::QUEUE_CRON_HOOK );
        }
    }

    /**
     * The main worker function, executed by the cron job.
     * It processes the queue and updates product prices.
     */
    public function process_price_update_queue(): void {
        $product_ids = get_transient( self::QUEUE_TRANSIENT_KEY );

        // Delete the transient immediately to prevent re-runs with the same data.
        delete_transient( self::QUEUE_TRANSIENT_KEY );

        if ( ! is_array( $product_ids ) || empty( $product_ids ) ) {
            return;
        }

        // Ensure the PriceCalculator is available.
        if ( ! class_exists( PriceCalculator::class ) ) {
            return;
        }

        foreach ( $product_ids as $pid ) {
            try {
                $pid = (int) $pid;
                if ( $pid <= 0 ) continue;

                $res = PriceCalculator::instance()->calculate( $pid );

                if ($res === null) continue;

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
                
                if ( function_exists('wc_delete_product_transients') ) {
                    wc_delete_product_transients( $pid );
                }
            } catch (\Throwable $e) {
                // Log error if needed, but continue the loop for other products.
            }
        }
    }

    /**
     * Finds all product and variation IDs that depend on a given currency.
     *
     * @param int $currency_id The post ID of the currency.
     * @return int[] An array of product IDs.
     */
    private function find_affected_products( int $currency_id ): array {
        $db = DB::instance();
        $product_ids = [];

        // 1. Find "Simple" dependency products
        $simple_products = get_posts([
            'post_type'      => ['product', 'product_variation'],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => $db->full_meta_key('currency_id'),
                    'value' => $currency_id,
                ],
                [
                    'key'   => $db->full_meta_key('dependence_type'),
                    'value' => 'simple',
                ],
            ],
        ]);
        if ( ! empty($simple_products) ) {
            $product_ids = array_merge( $product_ids, $simple_products );
        }

        // 2. Find "Advanced" (Formula) dependency products
        // First, find all formulas that use this currency in one of their variables.
        $formulas = get_posts([
            'post_type'      => 'mnsnp_formula',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        
        $affected_formula_ids = [];
        foreach ($formulas as $fid) {
            $vars = get_post_meta($fid, $db->full_meta_key('formula_variables'), true);
            if (is_array($vars)) {
                foreach ($vars as $var_data) {
                    if (isset($var_data['currency_id']) && (int) $var_data['currency_id'] === $currency_id) {
                        $affected_formula_ids[] = $fid;
                        break; // Move to the next formula
                    }
                }
            }
        }

        if ( ! empty($affected_formula_ids) ) {
            $advanced_products = get_posts([
                'post_type'      => ['product', 'product_variation'],
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => [
                    [
                        'key'     => $db->full_meta_key('formula_id'),
                        'value'   => $affected_formula_ids,
                        'compare' => 'IN',
                    ],
                ],
            ]);
            if ( ! empty($advanced_products) ) {
                $product_ids = array_merge( $product_ids, $advanced_products );
            }
        }

        return array_unique( $product_ids );
    }
}