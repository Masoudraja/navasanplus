<?php
/**
 * Formula Components Meta‐box template (Navasan Plus)
 *
 * Vars:
 * @var array $formula {
 *   @type int   $components_counter
 *   @type array $components  (indexed array: [ ['name'=>'','text'=>'','symbol'=>''], ... ])
 * }
 */
if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\Templates\Classes\Fields;

// Nonce باید با saver یکی باشد
wp_nonce_field( 'mns_navasan_plus_formula', '_mns_navasan_plus_formula_nonce' );

// مقدارهای امن پیش‌فرض
$components = is_array( $formula['components'] ?? null ) ? $formula['components'] : [];
$counter    = (int) ( $formula['components_counter'] ?? 1 );
$total      = max( 1, $counter, count( $components ) );
?>

<h4><?php _e( 'Formula Components', 'mns-navasan-plus' ); ?></h4>

<input type="hidden"
       name="_mns_navasan_plus_formula_components_counter"
       value="<?php echo (int) $total; ?>" />

<div id="mns-navasan-plus-formula-components-container">
  <?php for ( $i = 0; $i < $total; $i++ ) :
      $component = $components[ $i ] ?? [
        'name'   => '',
        'text'   => '',
        'symbol' => '',
      ];
  ?>
  <div class="mns-formula-component" data-index="<?php echo esc_attr( $i ); ?>">

    <?php
    // نام کامپوننت
    Fields::text(
      "_mns_navasan_plus_formula_components[{$i}][name]",
      "mns_navasan_plus_formula_components_{$i}_name",
      $component['name'],
      __( 'Component Name', 'mns-navasan-plus' )
    );

    // عبارت/متن کامپوننت (برای محاسبه زنده کلاس زیر لازم است)
    Fields::text(
      "_mns_navasan_plus_formula_components[{$i}][text]",
      "mns_navasan_plus_formula_components_{$i}_text",
      $component['text'],
      __( 'Component Text / Expression', 'mns-navasan-plus' ),
      '',
      [ 'class' => 'mns-component-expression' ]
    );

    // نماد (اختیاری – فقط نمایش کنار حاصل)
    Fields::text(
      "_mns_navasan_plus_formula_components[{$i}][symbol]",
      "mns_navasan_plus_formula_components_{$i}_symbol",
      $component['symbol'],
      __( 'Component Symbol', 'mns-navasan-plus' )
    );
    ?>

    <p class="description" style="margin:6px 0;">
      <?php _e( 'Total:', 'mns-navasan-plus' ); ?>
      <strong><span class="mns-component-total">—</span></strong>
      <?php if ( ! empty( $component['symbol'] ) ) : ?>
        <span class="mns-component-total-symbol"><?php echo ' ' . esc_html( $component['symbol'] ); ?></span>
      <?php endif; ?>
    </p>

    <p style="margin:6px 0 0;">
      <button type="button" class="button remove-formula-component">
        <?php _e( 'Remove Component', 'mns-navasan-plus' ); ?>
      </button>
    </p>

    <hr/>
  </div>
  <?php endfor; ?>
</div>

<p>
  <button type="button" class="button add-formula-component">
    <?php _e( 'Add Component', 'mns-navasan-plus' ); ?>
  </button>
</p>