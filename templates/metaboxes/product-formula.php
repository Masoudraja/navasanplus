<?php
/**
 * Product‐Formula Meta‐box template (Navasan Plus)
 *
 * Inputs:
 *  - $post_id
 *  - $name_prefix      '' برای ساده | '_variable' برای ورییشن‌ها
 *  - $name_suffix      '' برای ساده | مثل '[3]' برای ورییشن 3
 *  - $formula_data     [fid => ['label'=>..., 'variables'=>[code => ['name','unit_symbol']]]]
 *  - $product_data     [fid => [code => ['regular'=>float]]]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\Templates\Classes\Fields;

// post id
if ( empty( $post_id ) ) {
    $post_id = (int) get_the_ID();
}

// selected formula
$selected_formula_id = (int) get_post_meta( $post_id, '_mns_navasan_plus_formula_id', true );

// nonce
wp_nonce_field( 'mns_navasan_plus_product_formula', '_mns_navasan_plus_product_formula_nonce' );

// normalize
$formula_data = is_array( $formula_data ?? null ) ? $formula_data : [];
$product_data = is_array( $product_data ?? null ) ? $product_data : [];
?>

<p class="form-field">
  <label for="<?php echo esc_attr( $name_prefix . '_mns_navasan_plus_formula_id' . $name_suffix ); ?>">
    <?php _e( 'Select Formula', 'mns-navasan-plus' ); ?>
  </label>
  <select
    id="<?php echo esc_attr( $name_prefix . '_mns_navasan_plus_formula_id' . $name_suffix ); ?>"
    name="<?php echo esc_attr( $name_prefix . '_mns_navasan_plus_formula_id' . $name_suffix ); ?>"
    class="short"
  >
    <option value="0"><?php _e( '-- None --', 'mns-navasan-plus' ); ?></option>
    <?php foreach ( $formula_data as $fid => $fdata ) : ?>
      <option value="<?php echo esc_attr( $fid ); ?>" <?php selected( $selected_formula_id, $fid ); ?>>
        <?php echo esc_html( $fdata['label'] ?? ( '#' . $fid ) ); ?>
      </option>
    <?php endforeach; ?>
  </select>
  <span class="description">
    <?php _e( 'Pick the formula used to compute base price for this product/variation.', 'mns-navasan-plus' ); ?>
  </span>
</p>

<?php
$has_vars = ( $selected_formula_id && ! empty( $formula_data[ $selected_formula_id ]['variables'] ) );
?>
<div class="mns-navasan-plus-formula-variables" style="<?php echo $has_vars ? '' : 'display:none'; ?>">
  <?php if ( $has_vars ) :
    $variables   = (array) $formula_data[ $selected_formula_id ]['variables'];
    $stored_vars = (array) ( $product_data[ $selected_formula_id ] ?? [] );

    foreach ( $variables as $code => $var ) :
        $code  = (string) $code;
        $label = trim( (string) ( $var['name'] ?? $code ) );
        $unit  = (string) ( $var['unit_symbol'] ?? '' );
        $reg   = isset( $stored_vars[ $code ]['regular'] ) ? $stored_vars[ $code ]['regular'] : '';
  ?>
    <fieldset class="form-field">
      <legend>
        <?php
          echo esc_html( $label );
          if ( $unit !== '' ) echo ' (' . esc_html( $unit ) . ')';
        ?>
        <span style="margin-left:.5em;color:#666">code: <code><?php echo esc_html( $code ); ?></code></span>
      </legend>

      <?php
      // نکته: $name_suffix فوراً بعد از کلید اصلی می‌آید تا آرایه‌ی ورییشن درست پُست شود
      $base_name = "{$name_prefix}_mns_navasan_plus_formula_variables{$name_suffix}";
      Fields::number(
        // name
        "{$base_name}[{$selected_formula_id}][{$code}][regular]",
        // id
        "{$name_prefix}_mns_navasan_plus_formula_variables{$name_suffix}_{$selected_formula_id}_{$code}_regular",
        $reg,
        __( 'Value', 'mns-navasan-plus' ),
        [ 'step' => '0.0001', 'class' => 'short' ]
      );
      ?>
    </fieldset>
  <?php endforeach; endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  var selId  = '<?php echo esc_js( $name_prefix . '_mns_navasan_plus_formula_id' . $name_suffix ); ?>';
  var select = document.getElementById(selId);
  var varsDiv = document.currentScript.previousElementSibling;
  if (select && varsDiv) {
    function toggle(){ varsDiv.style.display = (select.value && select.value !== '0') ? '' : 'none'; }
    select.addEventListener('change', toggle);
    toggle();
  }
});
</script>