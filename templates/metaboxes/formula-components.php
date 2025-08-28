<?php
/**
 * Formula Components Meta-box (admin)
 * ذخیره با کلیدهای: name, text, symbol
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\Templates\Classes\Fields;

// nonce یکسان با saver
wp_nonce_field( 'mns_navasan_plus_formula', '_mns_navasan_plus_formula_nonce' );

// داده‌های قبلی
$components = is_array( $formula['components'] ?? null ) ? $formula['components'] : [];
$counter    = (int) ( $formula['components_counter'] ?? 1 );
$total      = max( 1, $counter, count( $components ) );
?>

<div class="mns-formula-components-box">
  <p style="margin:0 0 8px;"><?php esc_html_e( 'Define component lines (name + expression + symbol).', 'mns-navasan-plus' ); ?></p>

  <div id="mns-navasan-plus-formula-components-container">
    <?php for ( $i = 0; $i < $total; $i++ ) :
      $component = $components[ $i ] ?? [ 'name' => '', 'text' => '', 'symbol' => '' ];
    ?>
      <div class="mns-formula-component" data-index="<?php echo esc_attr( $i ); ?>" style="border:1px solid #eee;border-radius:6px;padding:10px;margin-bottom:10px;">
        <?php
        // نام کامپوننت
        Fields::text(
          "_mns_navasan_plus_formula_components[{$i}][name]",
          "mns_navasan_plus_formula_components_{$i}_name",
          $component['name'],
          __( 'Component Name', 'mns-navasan-plus' )
        );

        // عبارت کامپوننت — کلید ذخیره‌سازی: text
        // کلاس mns-component-expression برای محاسبه لحظه‌ای
        Fields::textarea(
          "_mns_navasan_plus_formula_components[{$i}][text]",
          "mns_navasan_plus_formula_components_{$i}_text",
          $component['text'],
          __( 'Component Expression', 'mns-navasan-plus' ),
          [ 'class' => 'mns-component-expression' ]
        );

        // نماد (اختیاری – برای نمایش کنار total)
        Fields::text(
          "_mns_navasan_plus_formula_components[{$i}][symbol]",
          "mns_navasan_plus_formula_components_{$i}_symbol",
          $component['symbol'],
          __( 'Component Symbol', 'mns-navasan-plus' )
        );
        ?>

        <div class="mns-component-total-row" style="margin-top:6px;">
          <strong><?php esc_html_e( 'Total:', 'mns-navasan-plus' ); ?></strong>
          <span class="mns-component-total">—</span>
          <span class="mns-component-total-symbol"><?php echo $component['symbol'] ? ' ' . esc_html( $component['symbol'] ) : ''; ?></span>
        </div>

        <div class="mns-component-actions" style="margin-top:8px;">
          <a href="#" class="button button-secondary add-formula-component"><?php esc_html_e( 'Add Component', 'mns-navasan-plus' ); ?></a>
          <a href="#" class="button remove-formula-component" style="margin-inline-start:6px;"><?php esc_html_e( 'Remove', 'mns-navasan-plus' ); ?></a>
        </div>
      </div>
    <?php endfor; ?>
  </div>

  <input type="hidden" name="_mns_navasan_plus_formula_components_counter" value="<?php echo esc_attr( $total ); ?>" />
</div>