<?php
namespace MNS\NavasanPlus\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\Services\PriceCalculator;
use MNS\NavasanPlus\DB;

final class PricePreviewMetaBox {

    public function run(): void {
        add_action( 'add_meta_boxes', [ $this, 'add_box' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_mnsnp_preview_price', [ $this, 'ajax_preview_price' ] );
        add_action( 'wp_ajax_mnsnp_apply_price',   [ $this, 'ajax_apply_price' ] );
    }

    public function add_box(): void {
        add_meta_box(
            'mnsnp_price_preview',
            __( 'Navasan Plus – Price Preview', 'mns-navasan-plus' ),
            [ $this, 'render' ],
            'product',
            'side',
            'high'
        );
    }

    public function enqueue_assets( $hook ): void {
        if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ( ! $screen || $screen->post_type !== 'product' ) return;

        if ( wp_script_is( 'mns-navasan-plus-admin', 'registered' ) ) {
            wp_enqueue_script( 'mns-navasan-plus-admin' );
        }

        wp_localize_script( 'mns-navasan-plus-admin', 'MNSNP_Preview', [
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'mnsnp_preview_price' ),
            'applyNonce' => wp_create_nonce( 'mnsnp_apply_price' ),
            'i18n' => [
                'calculating' => __( 'Calculating…', 'mns-navasan-plus' ),
                'updated'     => __( 'Updated.', 'mns-navasan-plus' ),
                'applied'     => __( 'Applied.', 'mns-navasan-plus' ),
                'failed'      => __( 'Failed to calculate.', 'mns-navasan-plus' ),
                'applyFailed' => __( 'Failed to apply.', 'mns-navasan-plus' ),
                'requestFail' => __( 'Request failed.', 'mns-navasan-plus' ),
            ],
        ] );
    }

    public function render( \WP_Post $post ): void {
        // صفر اعشار
        $dec_filter = static function(){ return 0; };
        add_filter( 'wc_get_price_decimals', $dec_filter, 1000 );

        $price = $profit = $charge = null;

        try {
            if ( class_exists( PriceCalculator::class ) ) {
                $res = PriceCalculator::instance()->calculate( (int) $post->ID );
                if ( is_array( $res ) ) {
                    $price  = isset($res['price'])  ? (float)$res['price']  : null;
                    $profit = isset($res['profit']) ? (float)$res['profit'] : null;
                    $charge = isset($res['charge']) ? (float)$res['charge'] : null;
                } elseif ( is_numeric( $res ) ) {
                    $price = (float) $res;
                }
            }
        } catch ( \Throwable $e ) {}

        ?>
        <div id="mnsnp-price-preview" style="line-height:1.7">
            <p style="margin:0 0 6px;">
                <strong><?php esc_html_e( 'Current calculated price', 'mns-navasan-plus' ); ?>:</strong>
                <span id="mnsnp-final-val">
                    <?php echo ($price !== null) ? wp_kses_post( wc_price( floor($price) ) ) : '—'; ?>
                </span>
            </p>

            <p style="margin:0 0 6px; color:#555">
                <span>
                    <?php esc_html_e( 'Profit', 'mns-navasan-plus' ); ?>:
                    <strong id="mnsnp-profit-val">
                    <?php echo ($profit !== null) ? wp_kses_post( wc_price( (int) floor($profit) ) ) : '—'; ?>
                    </strong>
                </span><br/>
                <span>
                    <?php esc_html_e( 'Charge', 'mns-navasan-plus' ); ?>:
                    <strong id="mnsnp-charge-val">
                    <?php echo ($charge !== null) ? wp_kses_post( wc_price( (int) floor($charge) ) ) : '—'; ?>
                    </strong>
                </span>
            </p>

            <button type="button" class="button" id="mnsnp-recalc">
                <?php esc_html_e( 'Recalculate now', 'mns-navasan-plus' ); ?>
            </button>
            <button type="button" class="button button-primary" id="mnsnp-apply-to-prices" style="margin-top:6px;">
                <?php esc_html_e( 'Apply to product prices', 'mns-navasan-plus' ); ?>
            </button>

            <p id="mnsnp-preview-msg" style="margin-top:8px;color:#666;"></p>
        </div>
        <?php
        remove_filter( 'wc_get_price_decimals', $dec_filter, 1000 );
        ?>

        <script>
        (function($){
        function debounce(fn, wait){
            var t; return function(){ var ctx=this, args=arguments;
            clearTimeout(t); t=setTimeout(function(){ fn.apply(ctx,args); }, wait||350);
            };
        }

        function getCurrentDiscounts(){
            return {
            profit_percentage: parseFloat($('#mnsnp_discount_profit_percentage').val() || 0) || 0,
            profit_fixed:      parseFloat($('#mnsnp_discount_profit_fixed').val()      || 0) || 0,
            charge_percentage: parseFloat($('#mnsnp_discount_charge_percentage').val() || 0) || 0,
            charge_fixed:      parseFloat($('#mnsnp_discount_charge_fixed').val()      || 0) || 0
            };
        }

        function doRecalc(){
            var $btn = $('#mnsnp-recalc'), $msg = $('#mnsnp-preview-msg');
            $btn.prop('disabled', true);
            $msg.text(MNSNP_Preview.i18n.calculating);

            $.post(MNSNP_Preview.ajaxUrl, {
            action: 'mnsnp_preview_price',
            nonce:  MNSNP_Preview.nonce,
            product_id: $('#post_ID').val(),
            discounts: getCurrentDiscounts() // ⬅️ مهم: تخفیف‌های ذخیره‌نشده
            }).done(function(res){
            if (res && res.success && res.data){
                if (typeof res.data.price_html   !== 'undefined') $('#mnsnp-final-val').html(res.data.price_html);
                if (typeof res.data.profit_html  !== 'undefined') $('#mnsnp-profit-val').html(res.data.profit_html);
                if (typeof res.data.charge_html  !== 'undefined') $('#mnsnp-charge-val').html(res.data.charge_html);

                // این خروجی‌ها برای پر کردن فیلدهای قیمت ووکامرس
                if (typeof res.data.regular_raw !== 'undefined') $('#_regular_price').val(res.data.regular_raw).trigger('change');
                if (typeof res.data.sale_raw    !== 'undefined') $('#_sale_price').val(res.data.sale_raw).trigger('change');

                $msg.text(MNSNP_Preview.i18n.updated);
            } else {
                $msg.text((res && res.data) ? res.data : MNSNP_Preview.i18n.failed);
            }
            }).fail(function(){
            $msg.text(MNSNP_Preview.i18n.requestFail);
            }).always(function(){ $btn.prop('disabled', false); });
        }

        // محاسبه مجدد
        $(document).on('click', '#mnsnp-recalc', doRecalc);
        // تغییرات باکس تخفیف → محاسبه‌ی خودکار
        $(document).on('input change', 'input[name^="mnsnp_discount["]', debounce(doRecalc, 400));

        // اعمال به قیمت‌های محصول (متای ووکامرس)
        $(document).on('click', '#mnsnp-apply-to-prices', function(){
            var $btn = $(this), $msg = $('#mnsnp-preview-msg');
            $btn.prop('disabled', true);
            $msg.text(MNSNP_Preview.i18n.calculating);

            $.post(MNSNP_Preview.ajaxUrl, {
            action: 'mnsnp_apply_price',
            nonce:  MNSNP_Preview.applyNonce,
            product_id: $('#post_ID').val(),
            discounts: getCurrentDiscounts() // ⬅️ همان تخفیف‌های فعلی (ذخیره‌نشده)
            }).done(function(res){
            if (res && res.success && res.data){
                // فیلدهای ووکامرس را پر کن
                if (typeof res.data.regular_raw !== 'undefined') $('#_regular_price').val(res.data.regular_raw).trigger('change');
                if (typeof res.data.sale_raw    !== 'undefined') $('#_sale_price').val(res.data.sale_raw).trigger('change');
                $msg.text(MNSNP_Preview.i18n.applied);
            } else {
                $msg.text((res && res.data) ? res.data : MNSNP_Preview.i18n.applyFailed);
            }
            }).fail(function(){
            $msg.text(MNSNP_Preview.i18n.requestFail);
            }).always(function(){ $btn.prop('disabled', false); });
        });
        })(jQuery);
        </script>
        <?php
    }

    public function ajax_preview_price(): void {
        if ( ! current_user_can( 'edit_products' ) ) wp_send_json_error( __( 'Access denied.', 'mns-navasan-plus' ) );
        check_ajax_referer( 'mnsnp_preview_price', 'nonce' );

        $pid = isset($_POST['product_id']) ? absint( $_POST['product_id'] ) : 0;
        if ( $pid <= 0 ) wp_send_json_error( __( 'Invalid product id.', 'mns-navasan-plus' ) );

        // صفر اعشار برای قیمت‌ها در پیش‌نمایش
        $dec_filter = function(){ return 0; };
        add_filter( 'wc_get_price_decimals', $dec_filter, 1000 );

        // تخفیف‌های موقّت (از فرمِ باز)
        $in = $_POST['discounts'] ?? [];
        if ( is_string($in) ) {
            $tmp = json_decode( wp_unslash($in), true );
            if ( is_array($tmp) ) $in = $tmp;
        }
        $override = [];
        if ( is_array($in) ) {
            $override = [
                'profit_percentage' => (float) ($in['profit_percentage'] ?? 0),
                'profit_fixed'      => (float) ($in['profit_fixed']      ?? 0),
                'charge_percentage' => (float) ($in['charge_percentage'] ?? 0),
                'charge_fixed'      => (float) ($in['charge_fixed']      ?? 0),
            ];
        }

        // فیلتر موقّت: تا وقتی این درخواست اجرا می‌شود، apply() همین override را استفاده کند
        $pid_local = $pid;
        $override_local = $override;
        $ov_filter = function($v, $pid_arg) use ($pid_local, $override_local) {
            return ($pid_arg === $pid_local && !empty($override_local)) ? $override_local : $v;
        };
        add_filter('mnsnp/discounts/override_values', $ov_filter, 10, 2);

        try {
            $res = class_exists( \MNS\NavasanPlus\Services\PriceCalculator::class )
                ? \MNS\NavasanPlus\Services\PriceCalculator::instance()->calculate( $pid )
                : null;

            remove_filter('mnsnp/discounts/override_values', $ov_filter, 10);

            if ( $res === null ) {
                remove_filter( 'wc_get_price_decimals', $dec_filter, 1000 );
                wp_send_json_error( __( 'Calculator not available.', 'mns-navasan-plus' ) );
            }

            $out = [];

            // ... داخل try، پس از دریافت $res ...
            if ( is_array($res) ) {
                $price        = (float) ($res['price']        ?? 0);
                $price_before = (float) ($res['price_before'] ?? $price);
                $profit       = (float) ($res['profit']       ?? 0);
                $charge       = (float) ($res['charge']       ?? 0);

                // همه‌چیز integer (floor)
                $p_i  = (int) floor($price);
                $pb_i = (int) floor($price_before);
                $pr_i = (int) floor($profit);
                $ch_i = (int) floor($charge);

                $has_discount = ($pb_i > $p_i); // اگر قبل از تخفیف > بعد از تخفیف باشد

                $out = [
                    'price'       => $p_i,
                    'price_html'  => wc_price($p_i),
                    'profit_html' => wc_price($pr_i),
                    'charge_html' => wc_price($ch_i),
                    // برای پر کردن فیلدهای ووکامرس
                    'regular_raw' => $has_discount ? $pb_i : $p_i,
                    'sale_raw'    => $has_discount ? $p_i  : '',
                ];

                remove_filter('wc_get_price_decimals', $dec_filter, 1000);
                wp_send_json_success($out);
            } else {
                $price = (float) $res;
                $p_i   = (int) floor($price);
                $out = [
                    'price'       => $p_i,
                    'price_html'  => wc_price($p_i),
                    'profit_html' => wc_price(0),
                    'charge_html' => wc_price(0),
                    'regular_raw' => $p_i,
                    'sale_raw'    => '',
                ];
                remove_filter('wc_get_price_decimals', $dec_filter, 1000);
                wp_send_json_success($out);
            }
        } catch ( \Throwable $e ) {
            remove_filter('mnsnp/discounts/override_values', $ov_filter, 10);
            remove_filter( 'wc_get_price_decimals', $dec_filter, 1000 );
            wp_send_json_error( $e->getMessage() );
        }
    }

        /** خواندن مقدار تخفیف با fallback از ورییشن ← والد */
        private function read_discount_meta_with_fallback( int $pid, string $key ): float {
            $db = DB::instance();
            $val = get_post_meta( $pid, $db->full_meta_key( $key ), true );
            if ( $val === '' ) {
                $parent = (int) wp_get_post_parent_id( $pid );
                if ( $parent ) {
                    $val = get_post_meta( $parent, $db->full_meta_key( $key ), true );
                }
            }
            return ( $val === '' ) ? 0.0 : (float) $val;
        }

    public function ajax_apply_price(): void {
        if ( ! current_user_can( 'edit_products' ) ) wp_send_json_error( __( 'Access denied.', 'mns-navasan-plus' ) );
        check_ajax_referer( 'mnsnp_apply_price', 'nonce' );

        $pid = isset($_POST['product_id']) ? absint( $_POST['product_id'] ) : 0;
        if ( $pid <= 0 ) wp_send_json_error( __( 'Invalid product id.', 'mns-navasan-plus' ) );

        // تخفیف‌های لحظه‌ای (اختیاری)
        $in = $_POST['discounts'] ?? [];
        if ( is_string($in) ) {
            $tmp = json_decode( wp_unslash($in), true );
            if ( is_array($tmp) ) $in = $tmp;
        }
        $override = [];
        if ( is_array($in) ) {
            $override = [
                'profit_percentage' => (float) ($in['profit_percentage'] ?? 0),
                'profit_fixed'      => (float) ($in['profit_fixed']      ?? 0),
                'charge_percentage' => (float) ($in['charge_percentage'] ?? 0),
                'charge_fixed'      => (float) ($in['charge_fixed']      ?? 0),
            ];
        }

        // فیلتر موقت برای DiscountService
        $pid_local = $pid;
        $override_local = $override;
        $ov_filter = function($v, $pid_arg) use ($pid_local, $override_local) {
            return ($pid_arg === $pid_local && !empty($override_local)) ? $override_local : $v;
        };
        add_filter('mnsnp/discounts/override_values', $ov_filter, 10, 2);

        try {
            $res = class_exists( PriceCalculator::class ) ? PriceCalculator::instance()->calculate( $pid ) : null;
            remove_filter('mnsnp/discounts/override_values', $ov_filter, 10);

            if ( $res === null ) wp_send_json_error( __( 'Calculator not available.', 'mns-navasan-plus' ) );

            $price_after  = is_array($res) ? (float) ($res['price']        ?? 0) : (float) $res;
            $price_before = is_array($res) ? (float) ($res['price_before'] ?? $price_after) : $price_after;

            $p_i  = (int) floor($price_after);
            $pb_i = (int) floor($price_before);

            $has_discount = ($pb_i > $p_i);

            // ذخیره در متای ووکامرس
            $regular_i = $has_discount ? $pb_i : $p_i;
            $sale_i    = $has_discount ? $p_i  : 0;

            update_post_meta( $pid, '_regular_price', $regular_i > 0 ? (string)$regular_i : '' );
            update_post_meta( $pid, '_sale_price',    $sale_i    > 0 ? (string)$sale_i    : '' );
            update_post_meta( $pid, '_price',         ($sale_i > 0 ? (string)$sale_i : ($regular_i > 0 ? (string)$regular_i : '')) );

            if ( function_exists('wc_delete_product_transients') ) wc_delete_product_transients( $pid );

            // خروجی UI
            $dec_filter = static function(){ return 0; };
            add_filter( 'wc_get_price_decimals', $dec_filter, 1000 );
            $out = [
                'regular_raw'  => $regular_i,
                'sale_raw'     => $sale_i > 0 ? $sale_i : '',
                'regular_html' => wc_price( $regular_i ),
                'sale_html'    => $sale_i > 0 ? wc_price( $sale_i ) : '',
            ];
            remove_filter( 'wc_get_price_decimals', $dec_filter, 1000 );

            wp_send_json_success( $out );
        } catch ( \Throwable $e ) {
            remove_filter('mnsnp/discounts/override_values', $ov_filter, 10);
            wp_send_json_error( $e->getMessage() );
        }
    }
}