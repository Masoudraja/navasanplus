<?php
/**
 * Price Calculator Template for MNS Navasan Plus
 *
 * @var \MNS\NavasanPlus\PublicNS\Currency[]|array $currencies
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// از هندل‌های رجیسترشده استفاده می‌کنیم تا Loader بین .min/.js سوییچ کند
wp_enqueue_style( 'mns-navasan-plus-public' );
wp_enqueue_script( 'mns-navasan-plus-public' );

// شناسه یکتا
$uid = 'mns-price-calculator-' . wp_unique_id();

// اطمینان از آرایه بودن ورودی
$currencies = is_array( $currencies ) ? $currencies : [];
$has_items  = ! empty( $currencies );
?>
<div id="<?php echo esc_attr( $uid ); ?>" class="mns-price-calculator" <?php if ( ! $has_items ) echo 'aria-disabled="true"'; ?>>
  <?php if ( $has_items ) : ?>
    <div class="mns-price-calculator-row">
      <label for="<?php echo esc_attr( $uid . '-amount' ); ?>">
        <?php esc_html_e( 'Amount', 'mns-navasan-plus' ); ?>
      </label>
      <input
        type="number"
        id="<?php echo esc_attr( $uid . '-amount' ); ?>"
        class="mns-price-calculator-amount"
        step="0.01"
        min="0"
        value="1"
        inputmode="decimal"
      />
    </div>

    <div class="mns-price-calculator-row">
      <label for="<?php echo esc_attr( $uid . '-currency' ); ?>">
        <?php esc_html_e( 'Currency', 'mns-navasan-plus' ); ?>
      </label>
      <select
        id="<?php echo esc_attr( $uid . '-currency' ); ?>"
        class="mns-price-calculator-currency-select"
      >
        <?php foreach ( $currencies as $currency ) :
          // Extract safely from object or array:
          $name   = is_object($currency) && method_exists($currency,'get_name')   ? (string) $currency->get_name()   : (string) ($currency['name']   ?? '');
          $symbol = is_object($currency) && method_exists($currency,'get_symbol') ? (string) $currency->get_symbol() : (string) ($currency['symbol'] ?? '');
          $rate   = is_object($currency) && method_exists($currency,'get_rate')   ? (float)  $currency->get_rate()   : (float)  ($currency['rate']   ?? 0);
          $value  = is_object($currency) && method_exists($currency,'get_id')
                      ? $currency->get_id()
                      : sanitize_title( $name ?: 'cur' );
        ?>
          <option
            value="<?php echo esc_attr( $value ); ?>"
            data-rate="<?php echo esc_attr( $rate ); ?>"
            data-symbol="<?php echo esc_attr( $symbol ); ?>"
          >
            <?php echo esc_html( $name ?: sprintf( __( 'Currency #%s', 'mns-navasan-plus' ), $value ) ); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mns-price-calculator-actions">
      <button type="button" class="button mns-price-calculator-btn">
        <?php esc_html_e( 'Calculate', 'mns-navasan-plus' ); ?>
      </button>
    </div>

    <div class="mns-price-calculator-result" aria-live="polite">
      <?php esc_html_e( 'Result:', 'mns-navasan-plus' ); ?>
      <span class="mns-price-calculator-result-value">0</span>
    </div>
  <?php else : ?>
    <p class="notice notice-warning" style="margin:0;">
      <?php esc_html_e( 'No currencies available to convert.', 'mns-navasan-plus' ); ?>
    </p>
  <?php endif; ?>
</div>

<?php if ( $has_items ) : ?>
<script>
(function($){
  $(function(){
    const $calc     = $('#<?php echo esc_js( $uid ); ?>');
    const $amount   = $calc.find('.mns-price-calculator-amount');
    const $currency = $calc.find('.mns-price-calculator-currency-select');
    const $result   = $calc.find('.mns-price-calculator-result-value');

    function recalc() {
      const amt = parseFloat($amount.val());
      const $opt = $currency.find('option:selected');
      const rate = parseFloat($opt.data('rate'));
      const sym  = String($opt.data('symbol') || '');

      const a = Number.isFinite(amt)  ? amt  : 0;
      const r = Number.isFinite(rate) ? rate : 0;

      const converted = a * r;
      // نمایش locale-aware با 2 رقم اعشار
      const formatted = Number(converted).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      $result.text( formatted + (sym ? (' ' + sym) : '') );
    }

    // رویدادها: دکمه + تغییر مقدار/ارز → محاسبهٔ خودکار
    $calc.on('click', '.mns-price-calculator-btn', function(e){
      e.preventDefault();
      recalc();
    });
    $amount.on('input', recalc);
    $currency.on('change', recalc);

    // محاسبهٔ اولیه
    recalc();
  });
})(jQuery);
</script>
<?php endif; ?>