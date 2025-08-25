<?php
/**
 * Currency Chart Meta‐box template for MNS Navasan Plus
 *
 * Renders a line chart of historical rates for the current Currency CPT.
 *
 * Variables passed in:
 * @var int   $post_id   The currency post ID.
 */

use MNS\NavasanPlus\DB;
use MNS\NavasanPlus\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Security nonce
wp_nonce_field( 'mns_navasan_plus_currency_chart', '_mns_navasan_plus_currency_chart_nonce' );

// How many days back to show (fallback to 30)
$max_records = DB::instance()->read_option( 'currency_max_records', 30 );
$time        = time();
$days        = [];

// Build an array of timestamps ⇒ formatted labels
while ( count( $days ) < $max_records ) {
    $days[ $time ] = wp_date( 'j F Y', $time );
    $time         -= DAY_IN_SECONDS;
}

// Reverse to chronological order
$labels = array_values( array_reverse( $days ) );

// Pull the stored history from post_meta (expecting an array timestamp ⇒ rate)
$history = DB::instance()->read_post_meta( $post_id, 'currency_history', [] );

$data = [];
foreach ( array_keys( $days ) as $ts ) {
    $data[] = isset( $history[ $ts ] ) ? floatval( $history[ $ts ] ) : 0;
}
$data = array_reverse( $data );
?>

<div class="mns-currency-chart-container" style="position: relative; height:200px;">
    <canvas id="mns-navasan-plus-currency-chart-<?php echo esc_attr( $post_id ); ?>"></canvas>
</div>

<script>
document.addEventListener( 'DOMContentLoaded', function() {
    // Ensure Chart.js is enqueued as a dependency in your admin assets
    var ctx   = document.getElementById( 'mns-navasan-plus-currency-chart-<?php echo esc_js( $post_id ); ?>' ).getContext( '2d' );
    var chart = new Chart( ctx, {
        type:    'line',
        data:    {
            labels:   <?php echo wp_json_encode( $labels ); ?>,
            datasets: [ {
                label:             '<?php echo esc_js( get_the_title( $post_id ) ); ?>',
                data:              <?php echo wp_json_encode( $data ); ?>,
                borderColor:       'rgba(52, 152, 219, 1)',
                backgroundColor:   'rgba(52, 152, 219, 0.2)',
                pointRadius:       3,
                fill:              false,
                tension:           0.1,
            } ]
        },
        options: {
            responsive:           true,
            maintainAspectRatio:  false,
            scales: {
                x: {
                    display: true,
                    title:   { display: true, text: '<?php _e( 'Date', 'mns-navasan-plus' ); ?>' }
                },
                y: {
                    display: true,
                    title:   { display: true, text: '<?php _e( 'Rate', 'mns-navasan-plus' ); ?>' }
                }
            }
        }
    } );
} );
</script>