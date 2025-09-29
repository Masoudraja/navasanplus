<?php
/**
 * Formula Calculator Template for MNS Navasan Plus
 *
 * @var int    $formula_id
 * @var string $formula_expr
 * @var array  $variables -> objects with get_code(), get_name(), get_unit_symbol(), get_value()
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Use registered handles in Loader
wp_enqueue_style( 'mns-navasan-plus-public' );
wp_enqueue_script( 'mns-navasan-plus-formula-parser' );
wp_enqueue_script( 'mns-navasan-plus-public' );

// A unique identifier for this instance
$uid = 'mns-formula-calculator-' . wp_unique_id();
?>
<div class="mns-formula-calculator" id="<?php echo esc_attr( $uid ); ?>"
     data-formula="<?php echo esc_attr( $formula_expr ); ?>">
    <?php foreach ( $variables as $variable ) : ?>
        <div class="mns-formula-field">
            <label for="<?php echo esc_attr( $uid . '-' . $variable->get_code() ); ?>">
                <?php echo esc_html( $variable->get_name() ); ?>
                (<?php echo esc_html( $variable->get_unit_symbol() ); ?>)
            </label>
            <input type="number"
                   id="<?php echo esc_attr( $uid . '-' . $variable->get_code() ); ?>"
                   class="mns-formula-input"
                   data-code="<?php echo esc_attr( $variable->get_code() ); ?>"
                   value="<?php echo esc_attr( $variable->get_value() ); ?>"
                   step="0.01" />
        </div>
    <?php endforeach; ?>

    <button type="button" class="mns-formula-calc-btn button">
        <?php esc_html_e( 'Calculate', 'mns-navasan-plus' ); ?>
    </button>

    <div class="mns-formula-result">
        <?php esc_html_e( 'Result:', 'mns-navasan-plus' ); ?>
        <span class="mns-formula-result-value">0</span>
    </div>
</div>

<script>
(function(){
  const container = document.getElementById('<?php echo esc_js( $uid ); ?>');
  if (!container) return;

  const formulaExpr = container.dataset.formula;
  const inputs      = container.querySelectorAll('input.mns-formula-input');
  const btn         = container.querySelector('button.mns-formula-calc-btn');
  const resultEl    = container.querySelector('.mns-formula-result-value');

  function evaluateWithFallback(expr, vars) {
    const FP = window.FormulaParser || {};
    // 1) API Function: FormulaParser.evaluate(expr, vars)
    if (typeof FP.evaluate === 'function') {
      return FP.evaluate(expr, vars);
    }
    // 2) Class API: new FormulaParser.Parser().evaluate(expr, vars)
    if (typeof FP.Parser === 'function') {
      try {
        const p = new FP.Parser();
        if (typeof p.evaluate === 'function') {
          return p.evaluate(expr, vars);
        }
      } catch (e) {}
    }
    return NaN;
  }

  btn.addEventListener('click', () => {
    // Collect variable values
    const vars = {};
    inputs.forEach(input => {
      const code = input.dataset.code;
      const val  = parseFloat(input.value);
      vars[code] = Number.isFinite(val) ? val : 0;
    });

    const price = evaluateWithFallback(formulaExpr, vars);

    if (!Number.isFinite(price)) {
      console.error('[MNSNP] FormulaParser not available or failed to evaluate.');
      resultEl.textContent = '—';
      return;
    }

    // Display خوش‌فرم (محلی)
    resultEl.textContent = price.toLocaleString(undefined, {
      maximumFractionDigits: 4
    });
  });
})();
</script>