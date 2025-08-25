<?php
/**
 * Advanced Currencies Table Template for MNS Navasan Plus
 *
 * Variables passed in:
 * @var array $columns     Array of column configs. Each item must contain:
 *                           - 'column'          => string: 'name'|'rate'|'change'|'time'
 *                           - 'title'           => string: column header label
 *                           - 'image'           => 'yes'|'no' (for 'name' column)
 *                           - 'change_decimals' => int|null (for 'change' column)
 *                           - 'time'            => 'human'|'time'|'date'|'datetime'|'custom'
 *                           - 'time_format'     => string (when 'time' === 'custom')
 * @var array $currencies  Array of rows. Each row is an array with:
 *                           - 'currency'    => Currency object
 *                           - 'rates_diff'  => array of floats (diff % for each rate column)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<table class="widefat mns-currencies-advanced-table">
    <thead>
        <tr>
            <?php foreach ( $columns as $column ) : ?>
                <th><?php echo esc_html( $column['title'] ); ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( $currencies as $row ) : ?>
            <tr>
                <?php 
                // reset rate index for each row
                $rate_index = 0;
                foreach ( $columns as $column ) : ?>
                    <td>
                        <?php
                        $curr = $row['currency'];
                        switch ( $column['column'] ) {
                            case 'name':
                                if ( $column['image'] === 'yes' ) {
                                    if ( $thumb_id = get_post_thumbnail_id( $curr->get_post() ) ) {
                                        echo wp_get_attachment_image( $thumb_id, [32,32], false, [ 'class'=>'mns-currency-image' ] );
                                    }
                                }
                                echo esc_html( $curr->get_name() );
                                break;

                            case 'rate':
                                $diff  = $row['rates_diff'][ $rate_index ] ?? 0;
                                $ratio = ( $diff / 100 ) + 1;
                                $rate  = $curr->get_rate() * $ratio;
                                echo wp_kses_post( $curr->display_rate( $rate ) );
                                $rate_index++;
                                break;

                            case 'change':
                                $decimals = ! empty( $column['change_decimals'] ) 
                                    ? (int) $column['change_decimals'] 
                                    : 2;
                                echo wp_kses_post(
                                    \MNS\NavasanPlus\Templates\Classes\Snippets::percentage_change(
                                        $curr->get_prev_mean(),
                                        $curr->get_rate(),
                                        $decimals
                                    )
                                );
                                break;

                            case 'time':
                                $update_time = $curr->get_update_time();
                                switch ( $column['time'] ) {
                                    case 'human':
                                        echo sprintf(
                                            __( '%s ago', 'mns-navasan-plus' ),
                                            human_time_diff( $update_time )
                                        );
                                        break;

                                    case 'time':
                                        echo esc_html( wp_date( 'H:i:s', $update_time ) );
                                        break;

                                    case 'date':
                                        echo esc_html( wp_date( 'j F Y', $update_time ) );
                                        break;

                                    case 'datetime':
                                        echo esc_html( sprintf(
                                            __( '%1$s at %2$s', 'mns-navasan-plus' ),
                                            wp_date( 'j F Y', $update_time ),
                                            wp_date( 'H:i:s', $update_time )
                                        ) );
                                        break;

                                    case 'custom':
                                        echo esc_html( wp_date( $column['time_format'], $update_time ) );
                                        break;
                                }
                                break;
                        }
                        ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>