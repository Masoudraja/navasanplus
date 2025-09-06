<?php
/**
 * Currencies Table Template for MNS Navasan Plus
 *
 * Displays a simple table of currencies with their current rate and percentage change.
 *
 * Variables passed in:
 * @var array $currencies Array of Currency objects.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use MNS\NavasanPlus\Templates\Classes\Snippets;
?>

<table class="widefat mns-currencies-table">
    <thead>
        <tr>
            <th><?php esc_html_e( 'Currency', 'mns-navasan-plus' ); ?></th>
            <th><?php esc_html_e( 'Current Rate', 'mns-navasan-plus' ); ?></th>
            <th><?php esc_html_e( 'Change', 'mns-navasan-plus' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( $currencies as $currency ) : ?>
            <tr>
                <td><?php echo esc_html( $currency->get_name() ); ?></td>
                <td><?php echo wp_kses_post( $currency->display_rate() ); ?></td>
                <td>
                    <?php
                        // Show percentage change from previous mean to current rate
                        echo wp_kses_post(
                            Snippets::percentage_change(
                                $currency->get_prev_mean(),
                                $currency->get_rate()
                            )
                        );
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>