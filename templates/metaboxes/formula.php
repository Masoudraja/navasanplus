<?php
/**
 * Formula Meta‐box template (Navasan Plus)
 *
 * Vars:
 * @var array $formula {
 *   @type string $formul
 *   @type int    $variables_counter
 *   @type array  $variables  (assoc by variable code)
 * }
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\Templates\Classes\Fields;

// Nonce مطابق با formula_save()
wp_nonce_field( 'mns_navasan_plus_formula', '_mns_navasan_plus_formula_nonce' );
?>

<!-- Formula Expression -->
<p>
  <label for="mns_navasan_plus_formula_formul">
    <?php _e( 'Formula Expression', 'mns-navasan-plus' ); ?>
  </label>
  <textarea
    id="mns_navasan_plus_formula_formul"
    name="_mns_navasan_plus_formula_formul"
    rows="4"
    class="widefat"
  ><?php echo esc_textarea( $formula['formul'] ); ?></textarea>
  <small class="description">
    <?php _e( 'Use variable codes (e.g. rate, ratio, weight or your defined codes).', 'mns-navasan-plus' ); ?>
  </small>
</p>

<!-- Variables -->
<h4><?php _e( 'Formula Variables', 'mns-navasan-plus' ); ?></h4>

<input type="hidden"
       name="_mns_navasan_plus_formula_variables_counter"
       value="<?php echo (int) ($formula['variables_counter'] ?? 1); ?>" />

<div id="mns-navasan-plus-formula-variables-container">
  <?php
  $codes = array_keys( (array) $formula['variables'] );
  $total = max( 1, (int) ($formula['variables_counter'] ?? count( $codes )) );

  for ( $i = 0; $i < $total; $i++ ) :
      $code = isset( $codes[$i] ) ? (string) $codes[$i] : 'var_' . $i;

      $var  = $formula['variables'][ $code ] ?? [
          'name'         => '',
          'unit'         => 1,
          'unit_symbol'  => '',
          'value'        => 0,
          'value_symbol' => '',
      ];
  ?>
  <div class="mns-formula-variable" data-code="<?php echo esc_attr( $code ); ?>">
    <?php
    // نام متغیر (نمایشی)
    Fields::text(
        "_mns_navasan_plus_formula_variables[{$code}][name]",
        "mns_navasan_plus_formula_variables_{$code}_name",
        $var['name'],
        __( 'Variable Name', 'mns-navasan-plus' )
    );

    // مقدار واحد
    Fields::number(
        "_mns_navasan_plus_formula_variables[{$code}][unit]",
        "mns_navasan_plus_formula_variables_{$code}_unit",
        $var['unit'],
        __( 'Unit Value', 'mns-navasan-plus' ),
        [ 'step' => '0.0001' ]
    );

    // نماد واحد
    Fields::text(
        "_mns_navasan_plus_formula_variables[{$code}][unit_symbol]",
        "mns_navasan_plus_formula_variables_{$code}_unit_symbol",
        $var['unit_symbol'],
        __( 'Unit Symbol', 'mns-navasan-plus' )
    );

    // مقدار
    Fields::number(
        "_mns_navasan_plus_formula_variables[{$code}][value]",
        "mns_navasan_plus_formula_variables_{$code}_value",
        $var['value'],
        __( 'Value', 'mns-navasan-plus' ),
        [ 'step' => '0.0001' ]
    );

    // نماد مقدار
    Fields::text(
        "_mns_navasan_plus_formula_variables[{$code}][value_symbol]",
        "mns_navasan_plus_formula_variables_{$code}_value_symbol",
        $var['value_symbol'],
        __( 'Value Symbol', 'mns-navasan-plus' )
    );
    ?>

    <p>
      <button type="button" class="button remove-formula-variable">
        <?php _e( 'Remove Variable', 'mns-navasan-plus' ); ?>
      </button>
      <span class="description" style="margin-left:8px;">
        <?php
        printf(
          /* translators: %s: variable code */
          esc_html__( 'Code: %s (use this in expression)', 'mns-navasan-plus' ),
          '<code>' . esc_html( $code ) . '</code>'
        );
        ?>
      </span>
    </p>
    <hr/>
  </div>
  <?php endfor; ?>
</div>

<p>
  <button type="button" class="button add-formula-variable">
    <?php _e( 'Add Variable', 'mns-navasan-plus' ); ?>
  </button>
</p>