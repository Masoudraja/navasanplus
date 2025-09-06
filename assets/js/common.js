/**
 * Common utility functions for MNS Navasan Plus
 *
 * Expects a global `mnsNavasanPlusGlobal` with (optional):
 *  - restBase: '/wp-json/mnsnp/v1/'
 *  - restNonce: WP REST nonce
 *  - ajaxUrl: admin-ajax.php URL
 *  - token:   shared REST token (for /mnsnp/v1/rates)
 */

;(function($){

  // RTL بدون وابستگی به wp.i18n
  function isRTL(){
    return document && document.documentElement && document.documentElement.dir === 'rtl';
  }

  // پارسر را از هر دو نام سراسری پیدا کن
  function getParserCtor(){
    if (window.FormulaParser && window.FormulaParser.Parser) return window.FormulaParser.Parser;
    if (window.formulaParser && window.formulaParser.Parser) return window.formulaParser.Parser;
    return null;
  }

  const hasJqNumber = typeof $.number === 'function';

  window.mnsNavasanPlusFunctions = {

    numberToPersian(number){
      const map = {0:'۰',1:'۱',2:'۲',3:'۳',4:'۴',5:'۵',6:'۶',7:'۷',8:'۸',9:'۹'};
      return String(number).replace(/[0-9]/g, d => map[d]);
    },

    maybePersian(number){
      return isRTL() ? this.numberToPersian(number) : number;
    },

    moveUp(el){ if(el.previousElementSibling){ el.parentNode.insertBefore(el, el.previousElementSibling);} },
    moveDown(el){ if(el.nextElementSibling){ el.parentNode.insertBefore(el.nextElementSibling, el);} },

    generateRandomHEX(){
      const letters = '0123456789ABCDEF'; let c = '#';
      for (let i=0;i<6;i++) c += letters[(Math.random()*16)|0];
      return c;
    },

    precision(value){
      value = Number(value);
      if (!isFinite(value)) return 0;
      let e = 1, p = 0;
      while (p < 12 && Math.round(value*e)/e !== value) { e *= 10; p++; }
      return p;
    },

    trailingZeroDecimal(value){
      value = Math.abs(Number(value));
      if (!isFinite(value) || value === 0 || value >= 1) return 0;
      return Math.max(0, Math.floor(-Math.log10(value)));
    },

    numberLength(value){
      value = Math.abs(parseInt(value)) || 0;
      return value === 0 ? 1 : ((Math.log10(value) | 0) + 1);
    },

    dynamicDecimal(value){
      value = Math.abs(Number(value)) || 0;
      if (value === 0) return 2;
      const intLen = this.numberLength(value);
      if (value >= 1) return Math.max(0, 4 - intLen);
      return Math.min(8, this.trailingZeroDecimal(value) + 3);
    },

    /**
     * اگر jQuery.number نبود، از Intl استفاده می‌کنیم
     */
    formatNumbers(number, decimals=null, decimalSep='.', thousandSep=','){
      const n = Number(number);
      const d = (decimals === null) ? (Number.isInteger(n) ? 0 : this.dynamicDecimal(n)) : decimals;

      if (hasJqNumber) return $.number(n, d, decimalSep, thousandSep);

      try {
        return new Intl.NumberFormat(undefined, {
          minimumFractionDigits: d,
          maximumFractionDigits: d
        }).format(n);
      } catch (_) {
        return n.toFixed(d);
      }
    },

    operationNumber(number, operation='none'){
      switch (operation) {
        case 'invert': return 1/number;
        case 'radical_2': return Math.sqrt(number);
        case 'divide_10': return number/10;
        default: return number;
      }
    },

    roundNumber(number, round=0, type='zero', side='close'){
      if (!round) return number;
      switch (type) {
        case 'zero':
          if (side === 'close') number = round * Math.round(number/round);
          if (side === 'up')    number = round * Math.ceil(number/round);
          if (side === 'down')  number = round * Math.floor(number/round);
          break;
        case 'nine':
          if (side === 'close') number = round*10 * Math.round(number/(round*10)) - 1;
          if (side === 'up')    number = round*10 * Math.ceil(number/(round*10))  - 1;
          if (side === 'down')  number = round*10 * Math.floor(number/(round*10)) - 1;
          break;
        case 'hybrid':
          if (side === 'close') number = round*10 * Math.round(number/(round*10))  - round;
          if (side === 'up')    number = round*10 * Math.ceil(number/(round*10))   - round;
          if (side === 'down')  number = round*10 * Math.floor(number/(round*10))  - round;
          break;
      }
      return number;
    },

    prepareFormulaComponent($container, text){
      text = (text || '').toString();
      const matches = text.match(/{[A-Za-z0-9-]+}/g);
      if (!matches) return text;
      matches.forEach((token) => {
        const key = token.slice(1,-1);
        const [type,id] = key.split('-');
        const $el   = $container.find(`.variable-${id}`);
        const rate  = parseFloat($el.data('rate')) || 1;
        const value = parseFloat($el.find('input').val()) || parseFloat($el.find('input').attr('placeholder')) || 0;
        let replacement = rate * value;
        if (type === 'u') replacement = rate;
        if (type === 'v') replacement = value;
        text = text.replace(token, replacement);
      });
      return text;
    },

    prepareFormula($container){
      const $t = $container.find('.mns-navasan-plus-text');
      const raw = $t.length ? ($t.val() || '') : '';
      return this.prepareFormulaComponent($container, raw.trim());
    },

    runFormula($container){
      const Parser = getParserCtor();
      const $result = $container.find('.mns-navasan-plus-result');
      const text = this.prepareFormula($container);
      if (!Parser) { console.warn('[MNSNP] Parser not found'); $result.text(this.formatNumbers(0)); return 0; }
      const parser = new Parser();
      parser.parse(text);
      const result = (!parser.error && text.length) ? Math.max(0, parser.result) : 0;
      $result.text(this.formatNumbers(result));
      return result;
    },

    calculateFormulaComponent($container, componentText){
      const Parser = getParserCtor();
      if (!Parser) return 0;
      const parser = new Parser();
      parser.parse(this.prepareFormulaComponent($container, componentText));
      return (!parser.error && componentText && componentText.length) ? Math.max(0, parser.result) : 0;
    },

    applyMargin(number, profitValue=0, profitType='percent', offset=1){
      number = Number(number);
      if (profitType === 'percent') {
        const factor = Math.max(-100, Number(profitValue)) / 100 + 1;
        return number * factor;
      }
      if (profitType === 'fixed') {
        return number + Math.max(0, Number(profitValue)) / (offset || 1);
      }
      return number;
    },

    calculateRateNumber(number, rate, round, roundType, roundSide, profitValue, profitType){
      let n = this.applyMargin(Number(number), profitValue, profitType, Number(rate));
      n = n * Number(rate);
      return this.roundNumber(n, Number(round)||0, String(roundType||'zero'), String(roundSide||'close'));
    },

    calculateRatePrice(price, rate, round, roundType, roundSide, profitValue, profitType){
      const val = this.calculateRateNumber(price, rate, round, roundType, roundSide, profitValue, profitType);
      return val < 0 ? 0 : val;
    },

    async restRequest(route, body={}){
      const g = window.mnsNavasanPlusGlobal || {};
      const base = (g.restBase || '/wp-json/mnsnp/v1/').replace(/\/+$/, '');
      let url = base + '/' + String(route||'').replace(/^\/+/, '');
      if (g.token) url += (url.includes('?') ? '&' : '?') + 'token=' + encodeURIComponent(g.token);

      const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          ...(g.restNonce ? { 'X-WP-Nonce': g.restNonce } : {})
        },
        body: JSON.stringify(body)
      });
      const json = await res.json().catch(()=> ({}));
      if (!res.ok || (json && json.code && json.message)) throw json || { message: 'REST error' };
      return json;
    },

    async sendRequest(action, body={}){
      const g = window.mnsNavasanPlusGlobal || {};
      const res = await fetch(g.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action, ...body })
      });
      const json = await res.json().catch(()=> ({}));
      if (!json || json.success !== true) throw (json && json.data) || { message: 'AJAX error' };
      return json.data;
    }
  };

})(jQuery);