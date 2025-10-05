<?php
/**
 * Admin\CurrencyBannerPage
 *
 * Admin page for currency banner management and shortcode generation
 *
 * File: includes/Admin/CurrencyBannerPage.php
 */

namespace MNS\NavasanPlus\Admin;

use MNS\NavasanPlus\Templates\Classes\Snippets;
use MNS\NavasanPlus\PublicNS\Currency;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CurrencyBannerPage {

    public function run(): void {
        add_action( 'admin_menu', [ $this, 'add_submenu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_ajax_mns_banner_preview', [ $this, 'ajax_banner_preview' ] );
    }

    public function add_submenu(): void {
        add_submenu_page(
            Menu::SLUG,
            __( 'بنر ارز', 'mns-navasan-plus' ),
            __( 'بنر ارز', 'mns-navasan-plus' ),
            'manage_woocommerce',
            'mnsnp-currency-banner',
            [ $this, 'render_page' ]
        );
    }

    public function enqueue_admin_assets( $hook ): void {
        if ( $hook !== 'navasan-plus_page_mnsnp-currency-banner' ) {
            return;
        }

        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        
        // Add admin JavaScript
        wp_add_inline_script( 'jquery', '
            jQuery(document).ready(function($) {
                // Ensure form submission works
                $("form").on("submit", function() {
                    $(this).find("input[type=submit]").prop("disabled", true);
                });
                
                // Preview banner
                $(".mns-preview-banner").on("click", function() {
                    const shortcode = $("#mns-generated-shortcode").val();
                    
                    // Open preview in new window/tab
                    const previewWindow = window.open("", "_blank", "width=1200,height=600,scrollbars=yes,resizable=yes");
                    previewWindow.document.write(`
                        <html>
                            <head>
                                <title>Currency Banner Preview</title>
                                <style>body { padding: 20px; font-family: Arial, sans-serif; }</style>
                            </head>
                            <body>
                                <h2>Currency Banner Preview</h2>
                                <p><strong>Shortcode:</strong> <code>${shortcode}</code></p>
                                <div id="banner-preview">Loading preview...</div>
                                <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                            </body>
                        </html>
                    `);
                    previewWindow.document.close();
                    
                    // Load preview content via AJAX
                    $.post(ajaxurl, {
                        action: "mns_banner_preview",
                        shortcode: shortcode,
                        _wpnonce: "' . wp_create_nonce( 'mns_banner_preview' ) . '"
                    }, function(response) {
                        if (response.success) {
                            previewWindow.document.getElementById("banner-preview").innerHTML = response.data;
                        }
                    });
                });

                // Reset settings
                $(".mns-reset-settings").on("click", function() {
                    if (confirm("آیا مطمئن هستید می‌خواهید تمام تنظیمات بنر را به پیش‌فرض بازگردانید؟")) {
                        // Reset form to defaults
                        $("#mns_banner_style").val("modern");
                        $("#mns_banner_height").val("auto");
                        $("#mns_banner_background").val("gradient");
                        $("#mns_banner_columns").val("auto");
                        $("#mns_banner_auto_refresh").val("30");
                        $("#mns_banner_show_change").prop("checked", true);
                        $("#mns_banner_show_time").prop("checked", true);
                        $("#mns_banner_animation").val("slide");
                        
                        // Clear selected currencies
                        $("#mns-selected-currencies .mns-currency-card").each(function() {
                            $(this).find(".mns-remove-currency").click();
                        });
                        
                        // Update shortcode
                        if (typeof updateShortcode === "function") {
                            updateShortcode();
                        }
                    }
                });
            });
        ' );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'دسترسی ممنوع.', 'mns-navasan-plus' ) );
        }

        // Handle form submission
        if ( $_POST && check_admin_referer( 'mns_currency_banner_settings', 'mns_currency_banner_nonce' ) ) {
            $this->save_settings();
            // Redirect to prevent resubmission
            wp_redirect( add_query_arg( 'settings-updated', 'true', $_SERVER['REQUEST_URI'] ) );
            exit;
        }
        
        // Show success message
        if ( isset( $_GET['settings-updated'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __( 'تنظیمات بنر با موفقیت ذخیره شد!', 'mns-navasan-plus' ) . '</p></div>';
        }

        $this->render_page_content();
    }

    private function save_settings(): void {
        $banner_name = sanitize_text_field( $_POST['banner_name'] ?? 'Default Banner' );
        $selected_currencies = array_filter( array_map( 'intval', $_POST['mns_selected_currencies'] ?? [] ) );
        
        $settings = [
            'name'               => $banner_name,
            'selected_currencies' => $selected_currencies,
            'style'              => sanitize_text_field( $_POST['mns_banner_style'] ?? 'modern' ),
            'height'             => sanitize_text_field( $_POST['mns_banner_height'] ?? 'auto' ),
            'background'         => sanitize_text_field( $_POST['mns_banner_background'] ?? 'gradient' ),
            'background_color'   => sanitize_hex_color( $_POST['mns_banner_background_color'] ?? '#667eea' ),
            'columns'            => sanitize_text_field( $_POST['mns_banner_columns'] ?? 'auto' ),
            'auto_refresh'       => intval( $_POST['mns_banner_auto_refresh'] ?? 30 ),
            'show_change'        => isset( $_POST['mns_banner_show_change'] ) ? 'yes' : 'no',
            'show_time'          => isset( $_POST['mns_banner_show_time'] ) ? 'yes' : 'no',
            'show_symbol'        => isset( $_POST['mns_banner_show_symbol'] ) ? 'yes' : 'no',
            'animation'          => sanitize_text_field( $_POST['mns_banner_animation'] ?? 'slide' ),
            'font_size'          => intval( $_POST['mns_banner_font_size'] ?? 16 ),
            'font_color'         => sanitize_hex_color( $_POST['mns_banner_font_color'] ?? '#333333' ),
            'border_radius'      => intval( $_POST['mns_banner_border_radius'] ?? 8 ),
            'padding'            => intval( $_POST['mns_banner_padding'] ?? 20 ),
            'margin'             => intval( $_POST['mns_banner_margin'] ?? 10 ),
            'item_spacing'       => intval( $_POST['mns_banner_item_spacing'] ?? 15 ),
            'shadow'             => sanitize_text_field( $_POST['mns_banner_shadow'] ?? 'light' ),
            'width'              => sanitize_text_field( $_POST['mns_banner_width'] ?? '100%' )
        ];

        // Get existing banners
        $banners = get_option( 'mns_currency_banners', [] );
        $banner_id = sanitize_text_field( $_POST['banner_id'] ?? '' );
        
        if ( empty( $banner_id ) ) {
            // Create new banner
            $banner_id = 'banner_' . time() . '_' . wp_generate_password( 8, false, false );
        }
        
        $banners[ $banner_id ] = $settings;
        update_option( 'mns_currency_banners', $banners );
        
        // Also update the main settings for backward compatibility
        update_option( 'mns_currency_banner_settings', $settings );
    }

    private function get_settings(): array {
        $defaults = [
            'name'               => 'Default Banner',
            'selected_currencies' => [],
            'style'              => 'modern',
            'height'             => 'auto',
            'background'         => 'gradient',
            'background_color'   => '#667eea',
            'columns'            => 'auto',
            'auto_refresh'       => 30,
            'show_change'        => 'yes',
            'show_time'          => 'yes',
            'show_symbol'        => 'no',
            'animation'          => 'slide'
        ];

        $settings = get_option( 'mns_currency_banner_settings', [] );
        return wp_parse_args( $settings, $defaults );
    }

    private function render_page_content(): void {
        $settings = $this->get_settings();
        ?>
        <div class="wrap">
            <h1><?php _e( 'بنر ارز', 'mns-navasan-plus' ); ?></h1>
            <p class="description">
                <?php _e( 'بنرهای زیبای ارز برای نمایش قیمت‌های لحظه‌ای در هر قسمت از سایت با استفاده از شورتکد بسازید.', 'mns-navasan-plus' ); ?>
            </p>

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <div class="postbox">
                            <div class="postbox-header">
                                <h2 class="hndle"><?php _e( 'پیکربندی بنر', 'mns-navasan-plus' ); ?></h2>
                            </div>
                            <div class="inside">
                                <form method="post" action="">
                                    <?php wp_nonce_field( 'mns_currency_banner_settings', 'mns_currency_banner_nonce' ); ?>
                                    
                                    <?php 
                                    Snippets::load_template( 'currency-banner-admin', [
                                        'selected' => $settings['selected_currencies'],
                                        'context'  => 'admin_page'
                                    ]);
                                    ?>

                                    <div class="mns-form-actions">
                                        <input type="submit" class="button-primary" value="<?php _e( 'ذخیره تنظیمات', 'mns-navasan-plus' ); ?>">
                                        <button type="button" class="button button-secondary mns-preview-banner">
                                            <?php _e( 'پیش‌نمایش بنر', 'mns-navasan-plus' ); ?>
                                        </button>
                                        <button type="button" class="button button-secondary mns-reset-settings">
                                            <?php _e( 'بازگردانی به پیش‌فرض', 'mns-navasan-plus' ); ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div id="postbox-container-1" class="postbox-container">
                        <div class="postbox">
                            <div class="postbox-header">
                                <h2 class="hndle"><?php _e( 'راهنمای سریع', 'mns-navasan-plus' ); ?></h2>
                            </div>
                            <div class="inside">
                                <h4><?php _e( 'استفاده پایه', 'mns-navasan-plus' ); ?></h4>
                                <p><?php _e( '۱. ارزها را از لیست موجود انتخاب کنید', 'mns-navasan-plus' ); ?></p>
                                <p><?php _e( '۲. ظاهر و رفتار بنر را پیکربندی کنید', 'mns-navasan-plus' ); ?></p>
                                <p><?php _e( '۳. شورت‌کد تولید شده را کپی کنید', 'mns-navasan-plus' ); ?></p>
                                <p><?php _e( '۴. آن را در هر پست، صفحه یا ویجت قرار دهید', 'mns-navasan-plus' ); ?></p>

                                <h4><?php _e( 'نمونه‌های شورت‌کد', 'mns-navasan-plus' ); ?></h4>
                                <div class="mns-code-examples">
                                    <p><strong><?php _e( 'بنر پایه:', 'mns-navasan-plus' ); ?></strong></p>
                                    <code>[mns_currency_banner]</code>

                                    <p><strong><?php _e( 'ارزهای مشخص:', 'mns-navasan-plus' ); ?></strong></p>
                                    <code>[mns_currency_banner currencies="1,2,3"]</code>

                                    <p><strong><?php _e( 'سبک مینیمال:', 'mns-navasan-plus' ); ?></strong></p>
                                    <code>[mns_currency_banner style="minimal" height="compact"]</code>

                                    <p><strong><?php _e( 'ستون‌های سفارشی:', 'mns-navasan-plus' ); ?></strong></p>
                                    <code>[mns_currency_banner columns="4" background="solid"]</code>
                                </div>

                                <h4><?php _e( 'پارامترهای شورت‌کد', 'mns-navasan-plus' ); ?></h4>
                                <ul class="mns-parameter-list">
                                    <li><strong>currencies</strong> - <?php _e( 'شناسه‌های ارز جدا شده با کاما', 'mns-navasan-plus' ); ?></li>
                                    <li><strong>style</strong> - <?php _e( 'مدرن، مینیمال، کلاسیک', 'mns-navasan-plus' ); ?></li>
                                    <li><strong>height</strong> - <?php _e( 'خودکار، فشرده، بلند', 'mns-navasan-plus' ); ?></li>
                                    <li><strong>background</strong> - <?php _e( 'گرادیان، یکدست، شفاف', 'mns-navasan-plus' ); ?></li>
                                    <li><strong>columns</strong> - <?php _e( 'خودکار، ۲، ۳، ۴، ۵، ۶', 'mns-navasan-plus' ); ?></li>
                                    <li><strong>auto_refresh</strong> - <?php _e( 'فاصله بروزرسانی به ثانیه (۰=خاموش)', 'mns-navasan-plus' ); ?></li>
                                    <li><strong>show_change</strong> - <?php _e( 'بله/خیر - نمایش درصد تغییر', 'mns-navasan-plus' ); ?></li>
                                    <li><strong>show_time</strong> - <?php _e( 'بله/خیر - نمایش زمان بروزرسانی', 'mns-navasan-plus' ); ?></li>
                                    <li><strong>animation</strong> - <?php _e( 'لغزش، محو، هیچ', 'mns-navasan-plus' ); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .mns-form-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .mns-form-actions .button {
            margin-right: 10px;
        }

        .mns-code-examples {
            background: #f1f1f1;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }

        .mns-code-examples code {
            display: block;
            background: white;
            padding: 8px 12px;
            margin: 5px 0;
            border-radius: 3px;
            font-family: monospace;
            border-left: 3px solid #0073aa;
        }

        .mns-parameter-list {
            list-style: none;
            padding: 0;
        }

        .mns-parameter-list li {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }

        .mns-currency-stats-table {
            width: 100%;
            border-collapse: collapse;
        }

        .mns-currency-stats-table th,
        .mns-currency-stats-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .mns-currency-stats-table th {
            background: #f9f9f9;
            font-weight: 600;
        }

        .mns-rate-value {
            font-family: monospace;
            color: #0073aa;
        }

        .mns-rate-updated {
            font-size: 11px;
            color: #666;
        }
        </style>
        <?php
    }

    private function render_currency_stats(): void {
        $currencies = get_posts([
            'post_type'      => 'mnsnp_currency',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC'
        ]);

        if ( empty( $currencies ) ) {
            echo '<p>' . __( 'هیچ ارزی یافت نشد. ابتدا برخی ارزها را ایجاد کنید.', 'mns-navasan-plus' ) . '</p>';
            return;
        }

        echo '<table class="mns-currency-stats-table">';
        echo '<thead><tr>';
        echo '<th>' . __( 'ارز', 'mns-navasan-plus' ) . '</th>';
        echo '<th>' . __( 'نرخ', 'mns-navasan-plus' ) . '</th>';
        echo '<th>' . __( 'آخرین بروزرسانی', 'mns-navasan-plus' ) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ( $currencies as $post ) {
            $currency = new Currency( $post );
            echo '<tr>';
            echo '<td>';
            echo '<strong>' . esc_html( $currency->get_name() ) . '</strong>';
            if ( $currency->get_code() ) {
                echo ' <small>(' . esc_html( $currency->get_code() ) . ')</small>';
            }
            echo '</td>';
            echo '<td><span class="mns-rate-value">' . esc_html( $currency->display_rate() ) . '</span></td>';
            echo '<td><span class="mns-rate-updated">' . esc_html( $currency->format_update_time() ) . '</span></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * AJAX handler for banner preview
     */
    public function ajax_banner_preview(): void {
        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'mns_banner_preview' ) ) {
            wp_die( 'Security check failed' );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Access denied' );
        }

        $shortcode = sanitize_text_field( $_POST['shortcode'] ?? '[mns_currency_banner]' );
        
        // Start output buffering to capture styles and scripts
        ob_start();
        
        // Load banner CSS
        $css_file = MNS_NAVASAN_PLUS_PATH . 'assets/css/currency-banner.css';
        if ( file_exists( $css_file ) ) {
            echo '<style>' . file_get_contents( $css_file ) . '</style>';
        }
        
        // Generate banner output
        $banner_output = do_shortcode( $shortcode );
        echo $banner_output;
        
        // Add initialization script
        echo '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>';
        $js_file = MNS_NAVASAN_PLUS_PATH . 'assets/js/currency-banner.js';
        if ( file_exists( $js_file ) ) {
            echo '<script>' . file_get_contents( $js_file ) . '</script>';
        }
        
        $output = ob_get_clean();
        
        wp_send_json_success( $output );
    }
}