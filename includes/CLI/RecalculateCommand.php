<?php
namespace MNS\NavasanPlus\CLI;

if ( ! defined( 'ABSPATH' ) || ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
    exit;
}

use MNS\NavasanPlus\Services\PriceCalculator;

/**
 * Recalculates and applies Navasan Plus prices for all products.
 */
final class RecalculateCommand {

    /**
     * Recalculates and saves the formula-based price for all products.
     *
     * This command iterates through all published products and variations,
     * calculates their price using the PriceCalculator service, and updates
     * the standard WooCommerce price meta fields (_regular_price, _sale_price, _price).
     *
     * ## EXAMPLES
     *
     * wp mnsnp recalc-prices
     *
     */
    public function recalc_prices() {
        if ( ! class_exists( PriceCalculator::class ) ) {
            \WP_CLI::error( 'PriceCalculator service is not available. Is the plugin active?' );
            return;
        }

        \WP_CLI::log( 'Fetching all published products and variations...' );

        $product_ids = get_posts([
            'post_type'      => ['product', 'product_variation'],
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'suppress_filters' => true,
        ]);

        if ( empty( $product_ids ) ) {
            \WP_CLI::success( 'No published products found to update.' );
            return;
        }

        $count = count( $product_ids );
        \WP_CLI::log( "Found {$count} products. Starting recalculation..." );

        $progress = \WP_CLI\Utils\make_progress_bar( 'Recalculating Prices', $count );
        $updated_count = 0;
        $error_count = 0;

        foreach ( $product_ids as $pid ) {
            try {
                $res = PriceCalculator::instance()->calculate( $pid );

                if ( $res === null ) {
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
                update_post_meta( $pid, '_price', ($sale_i > 0 ? (string)$sale_i : ($regular_i > 0 ? (string)$regular_i : '')) );

                if ( function_exists('wc_delete_product_transients') ) {
                    wc_delete_product_transients( $pid );
                }
                $updated_count++;
            } catch ( \Throwable $e ) {
                \WP_CLI::warning( "Failed to process product ID {$pid}: " . $e->getMessage() );
                $error_count++;
            }
            $progress->tick();
        }

        $progress->finish();
        \WP_CLI::success( "Process complete. {$updated_count} products updated. {$error_count} products failed." );
    }
}
\WP_CLI::add_command( 'mnsnp', \MNS\NavasanPlus\CLI\RecalculateCommand::class );