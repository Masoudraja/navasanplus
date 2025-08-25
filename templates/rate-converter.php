<?php
/**
 * Rate Converter Template for MNS Navasan Plus
 *
 * Displays a frontend widget to convert an amount from one currency to another.
 *
 * Variables passed in:
 * @var array|\MNS\NavasanPlus\PublicNS\Currency[] $currencies  Array of currency objects.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use MNS\NavasanPlus\Helpers;

// Enqueue public assets
wp_enqueue_style(
    'mns-navasan-plus-public',
    Helpers::plugin_url( 'assets/css/public.css' ),
    [],
    '1.0.0'
);
wp_enqueue_script(
    'mns-navasan-plus-public',
    Helpers::plugin_url( 'assets/js/public.js' ),
    [ 'jquery' ],
    '1.0.0',
    true
);

// Unique identifier for this converter instance
$uid = 'mns-rate-converter-' . wp_unique_id();

// Ensure $currencies is an array
$currencies = is_array( $currencies ) ? $currencies : [];
?>
<div id="<?php echo esc_attr( $uid ); ?>" class="mns-rate-converter">
    <div class="mns-rate-converter-row">
        <label for="<?php echo esc_attr( $uid . '-amount' ); ?>">
            <?php esc_html_e( 'Amount', 'mns-navasan-plus' ); ?>
        </label>
        <input
            type="number"
            id="<?php echo esc_attr( $uid . '-amount' ); ?>"
            class="mns-rate-converter-amount"
            step="0.01"
            value="1"
        />
    </div>

    <div class="mns-rate-converter-row">
        <label for="<?php echo esc_attr( $uid . '-from' ); ?>">
            <?php esc_html_e( 'From', 'mns-navasan-plus' ); ?>
        </label>
        <select
            id="<?php echo esc_attr( $uid . '-from' ); ?>"
            class="mns-rate-converter-from"
        >
            <?php foreach ( $currencies as $currency ) : ?>
                <option
                    value="<?php echo esc_attr( $currency->get_rate() ); ?>"
                    data-symbol="<?php echo esc_attr( $currency->get_symbol() ); ?>"
                >
                    <?php echo esc_html( $currency->get_name() ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mns-rate-converter-row">
        <label for="<?php echo esc_attr( $uid . '-to' ); ?>">
            <?php esc_html_e( 'To', 'mns-navasan-plus' ); ?>
        </label>
        <select
            id="<?php echo esc_attr( $uid . '-to' ); ?>"
            class="mns-rate-converter-to"
        >
            <?php foreach ( $currencies as $currency ) : ?>
                <option
                    value="<?php echo esc_attr( $currency->get_rate() ); ?>"
                    data-symbol="<?php echo esc_attr( $currency->get_symbol() ); ?>"
                >
                    <?php echo esc_html( $currency->get_name() ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="button" class="button mns-rate-converter-btn">
        <?php esc_html_e( 'Convert', 'mns-navasan-plus' ); ?>
    </button>

    <div class="mns-rate-converter-result">
        <?php esc_html_e( 'Result:', 'mns-navasan-plus' ); ?>
        <span class="mns-rate-converter-result-value">0</span>
    </div>
</div>

<script>
(function($){
    $(function(){
        var $widget    = $('#<?php echo esc_js( $uid ); ?>');
        var $amount    = $widget.find('.mns-rate-converter-amount');
        var $from      = $widget.find('.mns-rate-converter-from');
        var $to        = $widget.find('.mns-rate-converter-to');
        var $result    = $widget.find('.mns-rate-converter-result-value');

        $widget.on('click', '.mns-rate-converter-btn', function(e){
            e.preventDefault();
            var amt     = parseFloat( $amount.val() ) || 0;
            var rateFrom= parseFloat( $from.val() )   || 0;
            var rateTo  = parseFloat( $to.val() )     || 0;
            var symbol  = $to.find('option:selected').data('symbol') || '';

            // Convert: first to base currency, then to target
            var converted = ( rateFrom > 0 && rateTo > 0 )
                ? ( amt * ( rateFrom / rateTo ) )
                : 0;

            var formatted = converted.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            $result.text( formatted + ( symbol ? ' ' + symbol : '' ) );
        });
    });
})(jQuery);
</script>