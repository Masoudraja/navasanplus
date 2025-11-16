<?php
/**
 * Uninstall script for MNS Navasan Plus
 *
 * This file is executed when the plugin is deleted via the WordPress admin.
 * It removes all plugin options, post meta, user meta, and custom post type entries.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Check if user wants to preserve data on uninstall
$preserve_data = get_option( 'mns_navasan_plus_preserve_data_on_uninstall', false );

// If preserve data option is enabled, don't delete anything
if ( $preserve_data ) {
    return;
}

// Define our DB prefix (in case it's not already defined)
if ( ! defined( 'MNS_NAVASAN_PLUS_DB_PREFIX' ) ) {
    define( 'MNS_NAVASAN_PLUS_DB_PREFIX', 'mns_navasan_plus' );
}

global $wpdb;

// 1) Delete all options with our prefix
$option_like = $wpdb->esc_like( MNS_NAVASAN_PLUS_DB_PREFIX . '_' ) . '%';
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $option_like
    )
);

// 2) Delete all postmeta entries with our prefix
$meta_like = $wpdb->esc_like( '_' . MNS_NAVASAN_PLUS_DB_PREFIX . '_' ) . '%';
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
        $meta_like
    )
);

// 3) Delete all usermeta entries with our prefix
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        $meta_like
    )
);

// 4) Delete all custom post type posts (Currency, Formula, Chart)
$post_types = [ 'mnsnp_currency', 'mnsnp_formula', 'mnsnp_chart' ];
foreach ( $post_types as $pt ) {
    $posts = get_posts( [
        'post_type'      => $pt,
        'post_status'    => 'any',
        'numberposts'    => -1,
        'fields'         => 'ids',
        'suppress_filters'=> true,
    ] );
    if ( ! empty( $posts ) ) {
        foreach ( $posts as $post_id ) {
            wp_delete_post( $post_id, true );
        }
    }
}