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

// اگر در فرانت نمایش می‌دهی، استایل عمومی را enqueue کن (در ادمین معمولاً لازم نیست)
if ( ! is_admin() ) {
    wp_enqueue_style( 'mns-navasan-plus-public', Helpers::plugin_url( 'assets/css/public.css' ), [], '1.0.0' );
}

// امن‌سازی ورودی‌ها
$display = is_array( $atts['display'] ?? null )
    ? array_map( 'trim', $atts['display'] )
    : array_filter( array_map( 'trim', explode( ',', $atts['display'] ?? '' ) ) );

$all_columns = [
    'name'  => __( 'Variable', 'mns-navasan-plus' ),
    'rate'  => __( 'Unit',     'mns-navasan-plus' ),
    'value' => __( 'Value',    'mns-navasan-plus' ),
    'total' => __( 'Total',    'mns-navasan-plus' ),
];

// فقط ستون‌های خواسته‌شده
$columns = empty( $display )
    ? [ 'name' => $all_columns['name'], 'total' => $all_columns['total'] ] // پیش‌فرض
    : array_intersect_key( $all_columns, array_flip( $display ) );

// مقدار ورودی پایه
$input_value = isset( $atts['value'] ) ? (float) $atts['value'] : null;

// گارد ایمنی روی ورودی‌ها
if ( ! is_object( $formula ) || ! method_exists( $formula, 'get_variables' ) ) {
    return;
}
if ( ! is_object( $product ) || ! method_exists( $product, 'get_formula_variables' ) ) {
    return;
}

// یک‌بار متغیرها را به‌صورت آرایهٔ کُد=>مقدار بگیر
$vars_map = $product->get_formula_variables( $input_value ); // مثلاً ['gold_rate'=>123.45,...]
$vars_map = is_array( $vars_map ) ? $vars_map : [];

$variables = $formula->get_variables(); // آرایه‌ای از FormulaVariable
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
            // محاسبهٔ مقادیر
            $code        = method_exists( $variable, 'get_code' ) ? $variable->get_code() : '';
            $unit        = method_exists( $variable, 'get_unit' ) ? (float) $variable->get_unit() : 1.0;
            $unit_symbol = method_exists( $variable, 'get_unit_symbol' ) ? (string) $variable->get_unit_symbol() : '';
            $def_value   = method_exists( $variable, 'get_value' ) ? (float) $variable->get_value() : 0.0;
            $val_symbol  = method_exists( $variable, 'get_value_symbol' ) ? (string) $variable->get_value_symbol() : '';

            // مقدار ورودی برای این متغیر از نقشه (fallback به مقدار پیش‌فرض خود متغیر)
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