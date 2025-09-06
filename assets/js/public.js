/*!
 * MNS Navasan Plus — public.js
 * Helper utilities + optional binders for front-end widgets:
 *  - Formula Calculator  (.mns-formula-calculator)
 *  - Formula Order       (form.mns-formula-order)
 *  - Price Calculator    (.mns-price-calculator)
 *  - Rate Converter      (.mns-rate-converter)
 *
 * NOTE: This file does NOT auto-bind handlers to avoid double-binding with any inline scripts.
 *       Use the exported binders if you want JS-only wiring:
 *         MNSNavasanPlus.bindFormulaCalculator(el)
 *         MNSNavasanPlus.bindFormulaOrder(form, { expression: '...', onResult: fn })
 *         MNSNavasanPlus.bindPriceCalculator(el)
 *         MNSNavasanPlus.bindRateConverter(el)
 *
 * Dependencies:
 *   - window.FormulaParser (provided by assets/js/formula-parser.js)
 */

(function (win, doc) {
  'use strict';

  // ------------------------- utils -------------------------

  function qs(root, sel)    { return (root || doc).querySelector(sel); }
  function qsa(root, sel)   { return Array.prototype.slice.call((root || doc).querySelectorAll(sel)); }
  function toNumber(val)    { var n = Number(val); return isNaN(n) ? 0 : n; }
  function fmt(n, min=2, max=2) {
    try {
      return Number(n).toLocaleString(undefined, {
        minimumFractionDigits: min,
        maximumFractionDigits: max
      });
    } catch (_) { return String(n); }
  }
  function parseJSONAttr(el, attr) {
    try {
      var v = el.getAttribute(attr);
      return v ? JSON.parse(v) : null;
    } catch (_) { return null; }
  }
  function getInputVars(container) {
    var vars = {};
    qsa(container, 'input[data-code], select[data-code], textarea[data-code]').forEach(function (inp) {
      var code = inp.getAttribute('data-code');
      if (!code) return;
      vars[code] = toNumber(inp.value || inp.getAttribute('placeholder') || 0);
    });
    return vars;
  }
  function evaluateFormula(expr, vars) {
    if (!win.FormulaParser || !win.FormulaParser.Parser) return NaN;
    var parser = new win.FormulaParser.Parser();
    return parser.evaluate(expr, vars || {});
  }
  // Rounding helper (step/type/side) — matches templates’ logic
  function roundValue(val, step, type, side) {
    step = Number(step) || 0;
    type = type || 'zero'; // 'zero'|'integer'|'custom'
    side = side || 'close';// 'close'|'up'|'down'
    if (step <= 0) return val;

    var factor = 1 / step, f;
    if (type === 'integer') {
      return Math.round(val);
    }
    if (type === 'zero') {
      if (side === 'close')  f = Math.round; else if (side === 'up') f = Math.ceil; else f = Math.floor;
      return f(val * factor) / factor;
    }
    // custom: same as 'zero' but kept for future extension
    if (side === 'close')  f = Math.round; else if (side === 'up') f = Math.ceil; else f = Math.floor;
    return f(val * factor) / factor;
  }

  // ------------------------- binders -------------------------

  /**
   * Bind a Formula Calculator box
   * Expected markup:
   *   <div class="mns-formula-calculator" data-formula="x + y">
   *     <input data-code="x"> <input data-code="y">
   *     <button class="mns-formula-calc-btn">...</button>
   *     <span class="mns-formula-result-value"></span>
   *   </div>
   */
  function bindFormulaCalculator(container) {
    if (!container || container.__mnsnpBound) return;
    container.__mnsnpBound = true;

    var expr   = container.getAttribute('data-formula') || '';
    var btn    = qs(container, '.mns-formula-calc-btn');
    var target = qs(container, '.mns-formula-result-value');

    if (!btn || !target) return;

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var vars = getInputVars(container);
      var out  = evaluateFormula(expr, vars);
      target.textContent = isNaN(out) ? '—' : fmt(out, 2, 2);
    });
  }

  /**
   * Bind a Formula Order form
   * Options:
   *   { expression: '...',   // required to evaluate final amount
   *     round: { step, type, side },
   *     min: number,
   *     onResult: function(number){},  // optional callback
   *     redirect: 'none' | 'https://...' }
   *
   * Markup (see templates/formula-order.php):
   *   <form class="mns-formula-order" data-options='{"round":[0,"zero","up"],"min":0,"redirect":"none"}'> ... </form>
   */
  function bindFormulaOrder(form, opts) {
    if (!form || form.__mnsnpBound) return;
    form.__mnsnpBound = true;

    var btn     = qs(form, '.mns-formula-calc-btn');
    var target  = qs(form, '.mns-formula-result-value');
    var data    = parseJSONAttr(form, 'data-options') || {};
    var options = Object.assign({ round:[0,'zero','up'], min:0, redirect:'none' }, data, (opts || {}));

    // Normalize round options: [step,type,side] or object
    var rStep = 0, rType = 'zero', rSide = 'up';
    if (Array.isArray(options.round)) {
      rStep = Number(options.round[0]) || 0;
      rType = options.round[1] || 'zero';
      rSide = options.round[2] || 'up';
    } else if (options.round && typeof options.round === 'object') {
      rStep = Number(options.round.step) || 0;
      rType = options.round.type || 'zero';
      rSide = options.round.side || 'up';
    }

    if (!btn) return;

    btn.addEventListener('click', function (e) {
      e.preventDefault();

      var vars = getInputVars(form);
      var expr = options.expression || form.getAttribute('data-expression') || '';
      var price = evaluateFormula(expr, vars);
      if (isNaN(price)) price = 0;

      price = roundValue(price, rStep, rType, rSide);
      if (options.min && price < Number(options.min)) price = Number(options.min);

      if (target) target.textContent = fmt(price, 2, 2);
      if (typeof options.onResult === 'function') {
        try { options.onResult(price); } catch (_) {}
      }
      if (options.redirect && options.redirect !== 'none') {
        win.location.href = options.redirect + (options.redirect.indexOf('?')>-1 ? '&' : '?') + 'price=' + encodeURIComponent(price);
      }
    });
  }

  /**
   * Bind Price Calculator widget
   * Markup:
   *   <div class="mns-price-calculator">
   *     <input .mns-price-calculator-amount>
   *     <select .mns-price-calculator-currency-select> option value=rate data-symbol=... </select>
   *     <button .mns-price-calculator-btn>...</button>
   *     <span .mns-price-calculator-result-value></span>
   *   </div>
   */
  function bindPriceCalculator(container) {
    if (!container || container.__mnsnpBound) return;
    container.__mnsnpBound = true;

    var $amount = qs(container, '.mns-price-calculator-amount');
    var $curr   = qs(container, '.mns-price-calculator-currency-select');
    var $btn    = qs(container, '.mns-price-calculator-btn');
    var $out    = qs(container, '.mns-price-calculator-result-value');

    if (!$amount || !$curr || !$btn || !$out) return;

    $btn.addEventListener('click', function (e) {
      e.preventDefault();
      var amt    = toNumber($amount.value);
      var rate   = toNumber($curr.value);
      var symbol = ($curr.options[$curr.selectedIndex] || {}).getAttribute('data-symbol') || '';

      var converted = amt * rate;
      $out.textContent = fmt(converted, 2, 2) + (symbol ? ' ' + symbol : '');
    });
  }

  /**
   * Bind Rate Converter widget
   * Markup:
   *   <div class="mns-rate-converter">
   *     <input .mns-rate-converter-amount>
   *     <select .mns-rate-converter-from> option value=rate data-symbol=... </select>
   *     <select .mns-rate-converter-to>   option value=rate data-symbol=... </select>
   *     <button .mns-rate-converter-btn>...</button>
   *     <span .mns-rate-converter-result-value></span>
   *   </div>
   */
  function bindRateConverter(container) {
    if (!container || container.__mnsnpBound) return;
    container.__mnsnpBound = true;

    var $amount = qs(container, '.mns-rate-converter-amount');
    var $from   = qs(container, '.mns-rate-converter-from');
    var $to     = qs(container, '.mns-rate-converter-to');
    var $btn    = qs(container, '.mns-rate-converter-btn');
    var $out    = qs(container, '.mns-rate-converter-result-value');

    if (!$amount || !$from || !$to || !$btn || !$out) return;

    $btn.addEventListener('click', function (e) {
      e.preventDefault();
      var amt     = toNumber($amount.value);
      var fromR   = toNumber($from.value);
      var toR     = toNumber($to.value);
      var symbol  = ($to.options[$to.selectedIndex] || {}).getAttribute('data-symbol') || '';

      var converted = (fromR > 0 && toR > 0) ? (amt * (fromR / toR)) : 0;
      $out.textContent = fmt(converted, 2, 2) + (symbol ? ' ' + symbol : '');
    });
  }

  // ------------------------- export -------------------------

  win.MNSNavasanPlus = win.MNSNavasanPlus || {};
  win.MNSNavasanPlus.utils = { fmt: fmt, evaluateFormula: evaluateFormula, roundValue: roundValue, getInputVars: getInputVars };
  win.MNSNavasanPlus.bindFormulaCalculator = bindFormulaCalculator;
  win.MNSNavasanPlus.bindFormulaOrder      = bindFormulaOrder;
  win.MNSNavasanPlus.bindPriceCalculator   = bindPriceCalculator;
  win.MNSNavasanPlus.bindRateConverter     = bindRateConverter;

})(window, document);