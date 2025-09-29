<?php
/**
 * Product Formula Values Table Template for MNS Navasan Plus
 *
 * @var \MNS\NavasanPlus\PublicNS\Formula $formula
 * @var \MNS\NavasanPlus\PublicNS\Product $product
 * @var array                             $atts      {
 *    @type string|array display  Comma-separated: name,rate,value,total
 *    @type float        value    Input base value for variables
 * }
 */

use MNS\NavasanPlus\Helpers;

if ( ! defined( 'ABSPATH' ) ) exit;

// If displaying on frontend, enqueue public styles (usually not needed in admin)
if ( ! is_admin() ) {
    wp_enqueue_style( 'mns-navasan-plus-public', Helpers::plugin_url( 'assets/css/public.css' ), [], '1.0.0' );
}

// Sanitize inputs
$display = is_array( $atts['display'] ?? null )
    ? array_map( 'trim', $atts['display'] )
    : array_filter( array_map( 'trim', explode( ',', $atts['display'] ?? '' ) ) );

$all_columns = [
    'name'  => __( 'Variable', 'mns-navasan-plus' ),
    'rate'  => __( 'Unit',     'mns-navasan-plus' ),
    'value' => __( 'Value',    'mns-navasan-plus' ),
    'total' => __( 'Total',    'mns-navasan-plus' ),
];

// Only requested columns
$columns = empty( $display )
    ? [ 'name' => $all_columns['name'], 'total' => $all_columns['total'] ] // Default
    : array_intersect_key( $all_columns, array_flip( $display ) );

// Base input value
$input_value = isset( $atts['value'] ) ? (float) $atts['value'] : null;

// Safety guard for inputs
if ( ! is_object( $formula ) || ! method_exists( $formula, 'get_variables' ) ) {
    return;
}
if ( ! is_object( $product ) || ! method_exists( $product, 'get_formula_variables' ) ) {
    return;
}

// Get variables once as code=>value array
$vars_map = $product->get_formula_variables( $input_value ); // e.g. ['gold_rate'=>123.45,...]
$vars_map = is_array( $vars_map ) ? $vars_map : [];

$variables = $formula->get_variables(); // Array of FormulaVariable
?>

<table class="widefat mns-formula-values-table">
    <thead>
        <tr>
            <?php foreach ( $columns as $col_label ) : ?>
                <th><?php echo esc_html( $col_label ); ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( $variables as $variable ) :
            // Calculate values
            $code        = method_exists( $variable, 'get_code' ) ? $variable->get_code() : '';
            $unit        = method_exists( $variable, 'get_unit' ) ? (float) $variable->get_unit() : 1.0;
            $unit_symbol = method_exists( $variable, 'get_unit_symbol' ) ? (string) $variable->get_unit_symbol() : '';
            $def_value   = method_exists( $variable, 'get_value' ) ? (float) $variable->get_value() : 0.0;
            $val_symbol  = method_exists( $variable, 'get_value_symbol' ) ? (string) $variable->get_value_symbol() : '';

            // Input value for this Variable from map (fallback to Variable's default value)
            $val = array_key_exists( $code, $vars_map ) ? (float) $vars_map[ $code ] : $def_value;

            $total = $unit * $val;
        ?>
            <tr>
                <?php foreach ( $columns as $col_key => $col_label ) : ?>
                    <td>
                        <?php
                        switch ( $col_key ) {
                            case 'name':
                                echo esc_html( method_exists( $variable, 'get_name' ) ? $variable->get_name() : $code );
                                break;

                            case 'rate':
                                printf(
                                    '%s %s',
                                    esc_html( Helpers::format_decimal( $unit, 2 ) ),
                                    esc_html( $unit_symbol )
                                );
                                break;

                            case 'value':
                                printf(
                                    '%s %s',
                                    esc_html( Helpers::format_decimal( $val, 2 ) ),
                                    esc_html( $val_symbol )
                                );
                                break;

                            case 'total':
                                echo esc_html( Helpers::format_decimal( $total, 2 ) );
                                break;
                        }
                        ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>