<?php
/**
 * Formula Order Template for MNS Navasan Plus
 *
 * Vars:
 * @var \MNS\NavasanPlus\PublicNS\Formula $formula
 * @var \WC_Product                      $product
 * @var bool                             $display_parts  (optional) default false
 * @var bool                             $display_text   (optional) default false
 * @var string                           $result_text    (optional) default __('Payable amount','mns-navasan-plus')
 * @var string                           $button_text    (optional) default __('Place order','mns-navasan-plus')
 * @var string                           $button_class   (optional) default 'button'
 * @var string                           $redirect       (optional) default 'none'
 * @var float                            $round_value    (optional) default 0
 * @var string                           $round_type     (optional) default 'zero' ('none'|'integer'|'zero')
 * @var string                           $round_side     (optional) default 'up'   ('close'|'up'|'down')
 * @var float                            $min            (optional) default 0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// enqueue registered handles (Loader picks .min in production)
wp_enqueue_style( 'mns-navasan-plus-public' );
wp_enqueue_script( 'mns-navasan-plus-formula-parser' );
wp_enqueue_script( 'mns-navasan-plus-public' );

$uid           = 'mns-formula-order-' . wp_unique_id();
$display_parts = ! empty( $display_parts );
$display_text  = ! empty( $display_text );
$result_text   = ! empty( $result_text ) ? $result_text : __( 'Payable amount', 'mns-navasan-plus' );
$button_text   = ! empty( $button_text ) ? $button_text : __( 'Place order', 'mns-navasan-plus' );
$button_class  = ! empty( $button_class ) ? $button_class : 'button';

// JS options
$options = [
    'formula_id' => (int) $formula->get_id(),
    'product_id' => (int) $product->get_id(),
    'hash'       => md5( $formula->get_id() . '|' . $product->get_id() ),
    'redirect'   => ! empty( $redirect ) ? (string) $redirect : 'none',
    'round'      => [
        'step' => isset( $round_value ) ? (float) $round_value : 0.0,
        'type' => isset( $round_type )  ? (string) $round_type  : 'zero',   // none|integer|zero
        'side' => isset( $round_side )  ? (string) $round_side  : 'up',     // close|up|down
    ],
    'min'        => isset( $min ) ? (float) $min : 0.0,
];
?>
<form id="<?php echo esc_attr( $uid ); ?>"
      class="mns-formula-order"
      data-options="<?php echo esc_attr( wp_json_encode( $options, JSON_UNESCAPED_UNICODE ) ); ?>">
    <?php foreach ( $formula->get_variables() as $variable ) : ?>
        <div class="mns-formula-field">
            <label for="<?php echo esc_attr( $uid . '-' . $variable->get_code() ); ?>">
                <?php echo esc_html( $variable->get_name() ); ?>
                (<?php echo esc_html( $variable->get_unit_symbol() ); ?>)
            </label>
            <input
                type="number"
                id="<?php echo esc_attr( $uid . '-' . $variable->get_code() ); ?>"
                data-code="<?php echo esc_attr( $variable->get_code() ); ?>"
                class="mns-formula-input"
                value="<?php echo esc_attr( $variable->get_value() ); ?>"
                step="0.01"
                inputmode="decimal"
            />
        </div>
    <?php endforeach; ?>

    <?php if ( $display_parts ) : ?>
        <div class="mns-formula-parts" aria-live="polite"></div>
    <?php endif; ?>

    <?php if ( $display_text ) : ?>
        <div class="mns-formula-result-text">
            <?php echo esc_html( $result_text ); ?>:
            <span class="mns-formula-result-value">0</span>
        </div>
    <?php endif; ?>

    <button type="button"
            class="<?php echo esc_attr( $button_class ); ?> mns-formula-calc-btn">
        <?php echo esc_html( $button_text ); ?>
    </button>
</form>

<script>
(function($){
  $(function(){
    const $form    = $('#<?php echo esc_js( $uid ); ?>');
    const optsJson = $form.attr('data-options') || '{}';
    let   opts     = {};
    try { opts = JSON.parse(optsJson); } catch(e){ opts = {}; }

    const expr     = <?php echo wp_json_encode( $formula->get_expression() ); ?>;
    const $inputs  = $form.find('input.mns-formula-input');
    const $result  = $form.find('.mns-formula-result-value').attr('aria-live','polite');
    const $parts   = $form.find('.mns-formula-parts');

    function evaluateWithFallback(expr, vars){
      const FP = window.FormulaParser || {};
      if (typeof FP.evaluate === 'function') return FP.evaluate(expr, vars);           // API Functionی
      if (typeof FP.Parser === 'function') {                                          // API کلاسی
        try { const p = new FP.Parser(); if (typeof p.evaluate==='function') return p.evaluate(expr, vars); } catch(e){}
      }
      return NaN;
    }

    function readVars() {
      const vars = {};
      $inputs.each(function(){
        const $inp = $(this);
        const code = String($inp.data('code') || '').trim();
        const val  = parseFloat($inp.val());
        vars[code] = Number.isFinite(val) ? val : 0;
      });
      return vars;
    }

    function roundValue(val) {
      const step = Number(opts?.round?.step || 0);
      const type = String(opts?.round?.type || 'zero'); // none|integer|zero
      const side = String(opts?.round?.side || 'up');   // close|up|down

      if (step > 0) {
        const q = val / step;
        if (side === 'up')    return Math.ceil(q)  * step;
        if (side === 'down')  return Math.floor(q) * step;
        return Math.round(q) * step; // close
      }
      if (type === 'integer') return Math.round(val);
      return val;
    }

    function renderParts(vars) {
      if (!$parts.length) return;
      const rows = Object.keys(vars).map(code =>
        '<tr><td>'+ String(code) +'</td><td>'+ (Number(vars[code]) || 0) +'</td></tr>'
      ).join('');
      $parts.html(
        '<table class="mns-formula-parts-table"><thead><tr>' +
        '<th><?php echo esc_js( __( 'Variable', 'mns-navasan-plus' ) ); ?></th>' +
        '<th><?php echo esc_js( __( 'Value', 'mns-navasan-plus' ) ); ?></th>' +
        '</tr></thead><tbody>' + rows + '</tbody></table>'
      );
    }

    $form.on('click', '.mns-formula-calc-btn', function(e){
      e.preventDefault();

      const vars = readVars();
      const raw  = evaluateWithFallback(expr, vars);
      let price  = Number.isFinite(raw) ? raw : 0;

      price = roundValue(price);
      const min = Number(opts?.min || 0);
      if (price < min) price = min;

      if ($result.length) {
        $result.text(price.toLocaleString(undefined, { maximumFractionDigits: 4 }));
      }

      renderParts(vars);

      const redirect = String(opts?.redirect || 'none');
      if (redirect && redirect !== 'none') {
        const sep = redirect.indexOf('?') > -1 ? '&' : '?';
        window.location.href = redirect + sep + 'price=' + encodeURIComponent(price);
      }
    });
  });
})(jQuery);
</script>