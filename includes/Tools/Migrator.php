<?php
namespace MNS\NavasanPlus\Tools;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Dynamic and custom migration for transferring values from old "Navasan" plugin
 */
final class Migrator {

    public static function run( array $args = [] ): array {

        $args = wp_parse_args( $args, [
            'dry'            => true,
            'new_formula_id' => 0,
            'new_vazn_code'  => '',
            'new_ojrat_code' => '',
            'old_vazn_key'   => '',
            'old_ojrat_key'  => '',
        ]);

        $report = [
            'products_scanned' => 0,
            'products_updated' => 0,
            'vazn_found'       => 0,
            'ojrat_found'      => 0,
            'report_summary'   => '',
            'errors'           => [],
        ];
        
        // Stage 1: Input validation
        if ( empty($args['new_formula_id']) || empty($args['new_vazn_code']) || empty($args['new_ojrat_code']) || empty($args['old_vazn_key']) || empty($args['old_ojrat_key']) ) {
            $report['errors'][] = 'All setting fields are required. Please fill them all and try again.';
            return $report;
        }

        $new_formula_id = $args['new_formula_id'];
        $new_vazn_code  = $args['new_vazn_code'];
        $new_ojrat_code = $args['new_ojrat_code'];
        $old_vazn_key   = $args['old_vazn_key'];
        $old_ojrat_key  = $args['old_ojrat_key'];

        // Stage 2: Get products
        $product_ids = get_posts([
            'post_type'      => ['product', 'product_variation'],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_status'    => 'any',
        ]);

        if ( empty( $product_ids ) ) {
            $report['errors'][] = 'No products found.';
            return $report;
        }

        $actually_updated = 0;
        foreach ( $product_ids as $pid ) {
            $report['products_scanned']++;

            $vazn_val = get_post_meta( $pid, $old_vazn_key, true );
            $ojrat_val = get_post_meta( $pid, $old_ojrat_key, true );

            if ( $vazn_val === '' && $ojrat_val === '' ) {
                continue;
            }

            $new_vars = get_post_meta($pid, '_mns_navasan_plus_formula_variables', true);
            if ( ! is_array( $new_vars ) ) $new_vars = [];

            $updated = false;

            if ( $vazn_val !== '' ) {
                $report['vazn_found']++;
                $new_vars[$new_formula_id][$new_vazn_code]['regular'] = $vazn_val;
                $updated = true;
            }

            if ( $ojrat_val !== '' ) {
                $report['ojrat_found']++;
                $new_vars[$new_formula_id][$new_ojrat_code]['regular'] = $ojrat_val;
                $updated = true;
            }

            if ( $updated ) {
                $report['products_updated']++;
                if ( !$args['dry'] ) {
                    update_post_meta($pid, '_mns_navasan_plus_formula_variables', $new_vars);
                    $actually_updated++;
                }
            }
        }
        
        if ($args['dry']) {
            $report['report_summary'] = "Dry Run: {$report['products_updated']} products are ready to be updated.";
        } else {
            $report['report_summary'] = "Migration Complete: {$actually_updated} products were successfully updated.";
        }

        return $report;
    }
}