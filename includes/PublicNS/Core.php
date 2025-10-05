<?php
namespace MNS\NavasanPlus\PublicNS;

use MNS\NavasanPlus\DB;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Core {

    /** @var Core|null */
    private static ?self $_instance = null;

    /**
     * Singleton
     */
    public static function instance(): self {
        if ( null === self::$_instance ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Register hooks
     */
    private function __construct() {
        // This hook is called on WooCommerce product queries
        add_action( 'woocommerce_product_query', [ $this, 'products_query' ], 9999 );
        
        // Initialize Currency Banner
        CurrencyBanner::instance();
    }

    /** (Optional) Loader::boot_public() calls this */
    public function run(): void {
        // Hooks are registered in __construct
    }

    /**
     * Filter product query based on currency/formula via GET parameters
     *
     * @param \WP_Query $q
     */
    public function products_query( $q ): void {
        // Don't do anything in admin
        if ( is_admin() ) {
            return;
        }

        // If object doesn't have get/set, exit (conservative approach)
        if ( ! is_object( $q ) || ! method_exists( $q, 'get' ) || ! method_exists( $q, 'set' ) ) {
            return;
        }

        // Meta keys with correct prefix
        // Note: If your actual meta names differ, change them here.
        $currency_key = DB::instance()->full_meta_key( 'currency_id' ); // -> _mns_navasan_plus_currency_id
        $formula_key  = DB::instance()->full_meta_key( 'formula_id' );  // -> _mns_navasan_plus_formula_id

        // Current meta_query of product query
        $meta_query = (array) $q->get( 'meta_query' );

        $added = false;

        // Filter based on currency ID related to product
        if ( isset( $_GET['base_currency'] ) ) {
            $cid = absint( wp_unslash( $_GET['base_currency'] ) );
            if ( $cid > 0 ) {
                $meta_query[] = [
                    'key'     => $currency_key,
                    'value'   => $cid,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ];
                $added = true;
            }
        }

        // Filter based on formula ID related to product
        if ( isset( $_GET['base_formula'] ) ) {
            $fid = absint( wp_unslash( $_GET['base_formula'] ) );
            if ( $fid > 0 ) {
                $meta_query[] = [
                    'key'     => $formula_key,
                    'value'   => $fid,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ];
                $added = true;
            }
        }

        if ( $added ) {
            // Ensure logical relation
            if ( empty( $meta_query['relation'] ) ) {
                $meta_query['relation'] = 'AND';
            }
            $q->set( 'meta_query', $meta_query );
        }
    }
}