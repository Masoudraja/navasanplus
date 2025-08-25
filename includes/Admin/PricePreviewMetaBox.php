<?php
namespace MNS\NavasanPlus\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\Services\PriceCalculator;

final class PricePreviewMetaBox {

    public function run(): void {
        add_action( 'add_meta_boxes', [ $this, 'add_box' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_mnsnp_preview_price', [ $this, 'ajax_preview_price' ] );
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

        // اسکریپت ادمین افزونه (از Loader ثبت شده)
        wp_enqueue_script( 'mns-navasan-plus-admin' );

        // داده‌های AJAX
        wp_localize_script( 'mns-navasan-plus-admin', 'MNSNP_Preview', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'mnsnp_preview_price' ),
        ] );
    }

    public function render( \WP_Post $post ): void {
        $result = null;
        if ( class_exists( PriceCalculator::class ) && method_exists( PriceCalculator::class, 'instance' ) ) {
            try {
                $result = PriceCalculator::instance()->calculate( (int) $post->ID );
            } catch ( \Throwable $e ) {
                $result = null;
            }
        }

        $final  = null;   // float|null
        $profit = null;   // float|null
        $charge = null;   // float|null

        if ( is_array( $result ) ) {
            if ( isset( $result['price'] ) ) {
                $final = (float) $result['price'];
            } elseif ( isset( $result['profit'], $result['charge'] ) ) {
                $profit = (float) $result['profit'];
                $charge = (float) $result['charge'];
                $final  = $profit + $charge;
            }
        } elseif ( is_numeric( $result ) ) {
            $final = (float) $result;
        }
        ?>
        <div id="mnsnp-price-preview" style="line-height:1.7">
            <p style="margin:0 0 6px;">
                <strong><?php esc_html_e( 'Current calculated price', 'mns-navasan-plus' ); ?>:</strong>
                <span id="mnsnp-final-val">
                    <?php
                    echo $final !== null
                        ? wp_kses_post( wc_price( $final ) )
                        : '—';
                    ?>
                </span>
            </p>

            <?php if ( $profit !== null || $charge !== null ) : ?>
                <p style="margin:0 0 6px; color:#555">
                    <?php if ( $profit !== null ) : ?>
                        <span>
                            <?php esc_html_e( 'Profit', 'mns-navasan-plus' ); ?>:
                            <strong id="mnsnp-profit-val"><?php echo wp_kses_post( wc_price( (float) $profit ) ); ?></strong>
                        </span><br/>
                    <?php endif; ?>
                    <?php if ( $charge !== null ) : ?>
                        <span>
                            <?php esc_html_e( 'Charge', 'mns-navasan-plus' ); ?>:
                            <strong id="mnsnp-charge-val"><?php echo wp_kses_post( wc_price( (float) $charge ) ); ?></strong>
                        </span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <button type="button" class="button" id="mnsnp-recalc">
                <?php esc_html_e( 'Recalculate now', 'mns-navasan-plus' ); ?>
            </button>

            <p id="mnsnp-preview-msg" style="margin-top:8px;color:#666;"></p>
        </div>

        <script>
        (function($){
            $(document).on('click', '#mnsnp-recalc', function(){
                var $btn = $(this), $msg = $('#mnsnp-preview-msg');
                $btn.prop('disabled', true);
                $msg.text('<?php echo esc_js( __( 'Calculating…', 'mns-navasan-plus' ) ); ?>');

                $.post(MNSNP_Preview.ajaxUrl, {
                    action: 'mnsnp_preview_price',
                    nonce:  MNSNP_Preview.nonce,
                    product_id: $('#post_ID').val()
                }).done(function(res){
                    if (res && res.success && res.data) {
                        // price
                        if (typeof res.data.price_html !== 'undefined') {
                            $('#mnsnp-final-val').html(res.data.price_html);
                        } else if (typeof res.data.price !== 'undefined') {
                            $('#mnsnp-final-val').text(res.data.price);
                        }
                        // profit
                        if (typeof res.data.profit_html !== 'undefined') {
                            $('#mnsnp-profit-val').html(res.data.profit_html);
                        } else if (typeof res.data.profit !== 'undefined') {
                            $('#mnsnp-profit-val').text(res.data.profit);
                        }
                        // charge
                        if (typeof res.data.charge_html !== 'undefined') {
                            $('#mnsnp-charge-val').html(res.data.charge_html);
                        } else if (typeof res.data.charge !== 'undefined') {
                            $('#mnsnp-charge-val').text(res.data.charge);
                        }

                        $msg.text('<?php echo esc_js( __( 'Updated.', 'mns-navasan-plus' ) ); ?>');
                    } else {
                        $msg.text((res && res.data) ? res.data : '<?php echo esc_js( __( 'Failed to calculate.', 'mns-navasan-plus' ) ); ?>');
                    }
                }).fail(function(){
                    $msg.text('<?php echo esc_js( __( 'Request failed.', 'mns-navasan-plus' ) ); ?>');
                }).always(function(){
                    $btn.prop('disabled', false);
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    public function ajax_preview_price(): void {
        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( __( 'Access denied.', 'mns-navasan-plus' ) );
        }
        check_ajax_referer( 'mnsnp_preview_price', 'nonce' );

        $pid = isset($_POST['product_id']) ? absint( $_POST['product_id'] ) : 0;
        if ( $pid <= 0 ) {
            wp_send_json_error( __( 'Invalid product id.', 'mns-navasan-plus' ) );
        }

        try {
            $result = null;
            if ( class_exists( PriceCalculator::class ) && method_exists( PriceCalculator::class, 'instance' ) ) {
                $result = PriceCalculator::instance()->calculate( $pid );
            }

            $out = [];

            if ( is_array( $result ) ) {
                if ( isset( $result['price'] ) ) {
                    $out['price']      = (float) $result['price'];
                    $out['price_html'] = wc_price( $out['price'] );
                }
                if ( isset( $result['profit'] ) ) {
                    $out['profit']      = (float) $result['profit'];
                    $out['profit_html'] = wc_price( $out['profit'] );
                }
                if ( isset( $result['charge'] ) ) {
                    $out['charge']      = (float) $result['charge'];
                    $out['charge_html'] = wc_price( $out['charge'] );
                }
                if ( ! isset( $out['price'] ) && isset( $out['profit'], $out['charge'] ) ) {
                    $out['price']      = (float) ( $out['profit'] + $out['charge'] );
                    $out['price_html'] = wc_price( $out['price'] );
                }
            } elseif ( is_numeric( $result ) ) {
                $out['price']      = (float) $result;
                $out['price_html'] = wc_price( $out['price'] );
            } else {
                wp_send_json_error( __( 'Calculator not available.', 'mns-navasan-plus' ) );
            }

            wp_send_json_success( $out );
        } catch ( \Throwable $e ) {
            wp_send_json_error( $e->getMessage() );
        }
    }
}