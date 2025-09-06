<?php
/**
 * Formula Components Meta-box (admin)
 * ذخیره با کلیدهای: name, text, symbol, role
 */
if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\Templates\Classes\Fields;

wp_nonce_field( 'mns_navasan_plus_formula', '_mns_navasan_plus_formula_nonce' );

$components = is_array( $formula['components'] ?? null ) ? $formula['components'] : [];
$counter    = (int) ( $formula['components_counter'] ?? 1 );
$total      = max( 1, $counter, count( $components ) );
?>
<div class="mns-formula-components-box">
  <p style="margin:0 0 8px;"><?php esc_html_e( 'Define component lines (name + expression + symbol).', 'mns-navasan-plus' ); ?></p>

  <div id="mns-navasan-plus-formula-components-container">
    <?php for ( $i = 0; $i < $total; $i++ ) :
      $component = $components[ $i ] ?? [ 'name' => '', 'text' => '', 'symbol' => '', 'role' => 'none' ];
      $role = in_array( ($component['role'] ?? 'none'), ['none','profit','charge'], true ) ? ($component['role'] ?? 'none') : 'none';
    ?>
      <div class="mns-formula-component" data-index="<?php echo esc_attr( $i ); ?>" style="border:1px solid #eee;border-radius:6px;padding:10px;margin-bottom:10px;">

        <?php
        Fields::text(
          "mns_navasan_plus_formula_components_{$i}_name",
          "_mns_navasan_plus_formula_components[{$i}][name]",
          $component['name'],
          __( 'Component Name', 'mns-navasan-plus' )
        );

        Fields::textarea(
          "mns_navasan_plus_formula_components_{$i}_text",
          "_mns_navasan_plus_formula_components[{$i}][text]",
          $component['text'],
          __( 'Component Expression', 'mns-navasan-plus' ),
          [ 'class' => 'mns-component-expression' ]
        );

        Fields::text(
          "mns_navasan_plus_formula_components_{$i}_symbol",
          "_mns_navasan_plus_formula_components[{$i}][symbol]",
          $component['symbol'],
          __( 'Component Symbol', 'mns-navasan-plus' )
        );

        // NEW: Role
        Fields::select(
          "mns_navasan_plus_formula_components_{$i}_role",
          "_mns_navasan_plus_formula_components[{$i}][role]",
          [
            'none'   => __( 'None', 'mns-navasan-plus' ),
            'profit' => __( 'Profit', 'mns-navasan-plus' ),
            'charge' => __( 'Charge', 'mns-navasan-plus' ),
          ],
          $role,
          __( 'Component Role', 'mns-navasan-plus' )
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
  <input type="hidden" name="_mns_navasan_plus_formula_components[_sentinel]" value="1" />
</div>