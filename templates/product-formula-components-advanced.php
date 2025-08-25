<?php
/**
 * Product Formula Components – Advanced Breakdown
 *
 * @var \MNS\NavasanPlus\PublicNS\Product $product
 * @var float                            $value  مقدار ورودی پایه (مثلاً وزن/تعداد)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// CSS عمومی
wp_enqueue_style( 'mns-navasan-plus-public' );

// 1) ورودی و گاردها
$input_value = isset( $value ) ? (float) $value : 1.0;

$variables  = ( is_object( $product ) && method_exists( $product, 'get_formula_variables' ) )
    ? (array) $product->get_formula_variables( $input_value )
    : [];

$components = ( is_object( $product ) && method_exists( $product, 'get_formula_components' ) )
    ? (array) $product->get_formula_components()
    : [];

// تلاش برای گرفتن فرمول اصلی از محصول (اختیاری)
$mainExpr = '';
if ( is_object( $product ) ) {
    if ( method_exists( $product, 'get_formula_expression' ) ) {
        $mainExpr = (string) $product->get_formula_expression();
    } elseif ( method_exists( $product, 'get_formula' ) ) {
        $f = $product->get_formula();
        if ( is_object($f) && method_exists($f, 'get_expression') ) {
            $mainExpr = (string) $f->get_expression();
        }
    }
}

// 2) تحلیل با سرویس (در صورت وجود) + فالس‌بک امن
$rows  = [];
$total = 0.0;

// آماده‌سازی موتور فرمول (اختیاری)
$engine = null;
if ( class_exists('\\MNS\\NavasanPlus\\Services\\FormulaEngine') ) {
    $engine = method_exists('\\MNS\\NavasanPlus\\Services\\FormulaEngine','instance')
        ? \MNS\NavasanPlus\Services\FormulaEngine::instance()
        : new \MNS\NavasanPlus\Services\FormulaEngine();
}

if ( class_exists('\\MNS\\NavasanPlus\\Services\\ComponentBreakdown') ) {
    $report = \MNS\NavasanPlus\Services\ComponentBreakdown::breakdown(
        $mainExpr,
        $components,
        $variables,
        $engine
    );
    $rows  = (array) ( $report['rows']  ?? [] );
    $total = (float)  ( $report['total'] ?? 0 );
} else {
    // فالس‌بک: هر کامپوننت را اجرا کن و خلاصه بساز
    foreach ( $components as $c ) {
        if ( ! is_object($c) || ! method_exists($c,'execute') ) { continue; }
        $name   = method_exists($c,'get_name')   ? (string) $c->get_name()   : '';
        $symbol = method_exists($c,'get_symbol') ? (string) $c->get_symbol() : '';
        $value  = (float) $c->execute( $variables );
        $rows[] = [
            'name'    => $name,
            'symbol'  => $symbol,
            'value'   => $value,
            // درصد را بعداً و پس از محاسبه total پر می‌کنیم
        ];
        $total += $value;
    }
    // درصدها
    if ( $total > 0 ) {
        foreach ( $rows as $i => $r ) {
            $rows[$i]['percent'] = ( (float) ( $r['value'] ?? 0 ) ) / $total * 100;
        }
    } else {
        foreach ( $rows as $i => $r ) {
            $rows[$i]['percent'] = 0.0;
        }
    }
}
?>
<div class="mns-formula-components mns-formula-components--advanced">
  <?php if ( empty( $components ) ) : ?>
    <p class="notice" style="margin:0;"><?php esc_html_e( 'No components defined for this formula.', 'mns-navasan-plus' ); ?></p>
  <?php else : ?>
    <table class="mns-formula-parts-table widefat striped">
      <thead>
        <tr>
          <th><?php esc_html_e( 'Component', 'mns-navasan-plus' ); ?></th>
          <th style="text-align:right;"><?php esc_html_e( 'Value', 'mns-navasan-plus' ); ?></th>
          <th style="text-align:right;"><?php esc_html_e( 'Share', 'mns-navasan-plus' ); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $rows as $r ) :
          $name    = (string) ($r['name'] ?? '');
          $symbol  = (string) ($r['symbol'] ?? '');
          $value   = (float)  ($r['value'] ?? 0);
          $percent = (float)  ($r['percent'] ?? 0);
          $formattedVal = number_format_i18n( $value, 2 );
        ?>
          <tr>
            <td>
              <?php echo esc_html( $name ); ?>
              <?php if ( $symbol !== '' ) : ?>
                <span class="mns-symbol" style="opacity:.7"> (<?php echo esc_html( $symbol ); ?>)</span>
              <?php endif; ?>
            </td>
            <td style="text-align:right;"><?php echo esc_html( $formattedVal ); ?></td>
            <td style="text-align:right;"><?php echo esc_html( number_format_i18n( $percent, 2 ) ); ?>%</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <th style="text-align:left;"><?php esc_html_e( 'Total', 'mns-navasan-plus' ); ?></th>
          <th style="text-align:right;"><?php echo esc_html( number_format_i18n( (float) $total, 2 ) ); ?></th>
          <th style="text-align:right;">100%</th>
        </tr>
      </tfoot>
    </table>
  <?php endif; ?>
</div>