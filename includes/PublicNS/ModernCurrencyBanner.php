<?php
/**
 * PublicNS\ModernCurrencyBanner
 *
 * Modern currency banner with enhanced animations and design
 * Provides shortcode functionality to display selected currencies anywhere on the site
 *
 * File: includes/PublicNS/ModernCurrencyBanner.php
 */

namespace MNS\NavasanPlus\PublicNS;

use MNS\NavasanPlus\DB;
use MNS\NavasanPlus\Helpers;
use MNS\NavasanPlus\PublicNS\Currency;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ModernCurrencyBanner {

    /** @var ModernCurrencyBanner|null */
    private static ?self $instance = null;

    /** @var bool Flag to check if shortcode is used on the page */
    private bool $shortcode_is_used = false;

    /**
     * Singleton
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize hooks
     */
    private function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_shortcode( 'mns_currency_banner', [ $this, 'render_shortcode' ] );
        add_action( 'wp_ajax_mns_get_currency_rates', [ $this, 'ajax_get_rates' ] );
        add_action( 'wp_ajax_nopriv_mns_get_currency_rates', [ $this, 'ajax_get_rates' ] );
    }

    /**
     * Enqueue banner assets
     */
    public function enqueue_assets(): void {
        // Register modern assets only
        wp_register_style(
            'mns-currency-banner-modern',
            Helpers::plugin_url( 'assets/css/currency-banner-modern.min.css' ),
            [],
            MNS_NAVASAN_PLUS_VER
        );

        wp_register_script(
            'mns-currency-banner-modern',
            Helpers::plugin_url( 'assets/js/currency-banner-modern.min.js' ),
            [ 'jquery' ],
            MNS_NAVASAN_PLUS_VER,
            true
        );

        // Register Swiper CSS
        wp_register_style(
            'swiper',
            'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
            [],
            '11.0.0'
        );

        // Register Swiper JS
        wp_register_script(
            'swiper',
            'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
            [],
            '11.0.0',
            true
        );

        // Register GSAP
        wp_register_script(
            'gsap',
            'https://cdn.jsdelivr.net/npm/gsap@3.12.2/dist/gsap.min.js',
            [],
            '3.12.2',
            true
        );

        // Localize script with proper data
        wp_localize_script( 'mns-currency-banner-modern', 'mnsCurrencyBanner', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'mns_currency_rates' ),
            'i18n'    => [
                'loading'      => __( 'در حال بارگیری...', 'mns-navasan-plus' ),
                'error'        => __( 'خطا در بارگیری نرخ‌ها', 'mns-navasan-plus' ),
                'updated'      => __( 'بروزرسانی شد', 'mns-navasan-plus' ),
                'change_up'    => __( 'قیمت افزایش یافت', 'mns-navasan-plus' ),
                'change_down'  => __( 'قیمت کاهش یافت', 'mns-navasan-plus' ),
                'no_change'    => __( 'قیمت تغییر نکرد', 'mns-navasan-plus' )
            ]
        ]);
    }

    /**
     * Render modern currency banner shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_shortcode( $atts = [] ): string {
        if ( ! $this->shortcode_is_used ) {
            $this->shortcode_is_used = true;
            wp_enqueue_style( 'mns-currency-banner-modern' );
            wp_enqueue_script( 'mns-currency-banner-modern' );
            wp_enqueue_style( 'swiper' );
            wp_enqueue_script( 'swiper' );
            wp_enqueue_script( 'gsap' );
        }

        // Load saved settings from the database to use as defaults.
        $default_settings = get_option( 'mns_currency_banner_settings', [] );

        // Set default currencies from saved settings if available
        if ( ! empty( $default_settings['selected_currencies'] ) ) {
            $default_settings['currencies'] = implode( ',', $default_settings['selected_currencies'] );
        } else {
            $default_settings['currencies'] = '';
        }

        // Merge the shortcode attributes with the saved settings.
        // Attributes in the shortcode [mns_currency_banner style="minimal"] will override the saved settings.
        $atts = shortcode_atts( $default_settings, $atts, 'mns_currency_banner' );

        // Get currencies to display
        $currencies = $this->get_currencies_for_banner( $atts['currencies'], $atts );

        if ( empty( $currencies ) ) {
            return '<div class="mns-currency-banner-error-modern">' . __( 'هیچ ارزی برای نمایش موجود نیست.', 'mns-navasan-plus' ) . '</div>';
        }

        // Generate unique banner ID with fallback
        $random_suffix = wp_generate_password( 8, false, false );
        if ( empty( $random_suffix ) || strlen( $random_suffix ) < 4 ) {
            // Fallback to timestamp-based ID if wp_generate_password fails
            $random_suffix = substr( md5( microtime() . mt_rand() ), 0, 8 );
        }
        $banner_id = 'mns-currency-banner-modern-' . sanitize_html_class( $random_suffix );

        // Render banner
        ob_start();
        $this->render_banner_template( $banner_id, $currencies, $atts );
        return ob_get_clean();
    }

    /**
     * Get currencies for banner display
     */
    private function get_currencies_for_banner( ?string $currency_ids = '', array $atts = [] ): array {
        $currencies = [];
        
        // Get max currencies setting (default to 6)
        $max_currencies = intval( $atts['max_currencies'] ?? 6 );
        if ( $max_currencies <= 0 ) {
            $max_currencies = 6;
        }
        
        if ( empty( $currency_ids ) ) {
            // Get all published currencies if none specified (limit by max_currencies)
            $posts = get_posts([
                'post_type'      => 'mnsnp_currency',
                'posts_per_page' => $max_currencies,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC'
            ]);
        } else {
            // Get specific currencies (limit by max_currencies)
            $ids = array_filter( array_map( 'intval', explode( ',', $currency_ids ) ) );
            $posts = get_posts([
                'post_type'    => 'mnsnp_currency',
                'post_status'  => 'publish',
                'post__in'     => $ids,
                'orderby'      => 'post__in',
                'posts_per_page' => count( $ids ),
            ]);
        }

        foreach ( $posts as $post ) {
            $currency = new Currency( $post );
            $currencies[] = [
                'currency' => $currency,
                'data'     => $currency->to_array()
            ];
        }

        return $currencies;
    }

    /**
     * Render modern banner template
     */
    private function render_banner_template( string $banner_id, array $currencies, array $atts ): void {
        $animation_class = 'mns-banner-anim-' . sanitize_html_class( $atts['animation'] ?? 'slide' );
        
        $this->generate_and_queue_styles( $banner_id, $atts );

        $wrapper_inline_style = $this->get_wrapper_style( $atts );
        $banner_inline_style = $this->get_banner_style( $atts );

        // Get responsive view settings
        $mobile_animation = isset($atts['mobile_animation']) && in_array($atts['mobile_animation'], ['carousel', 'ticker', 'stacked', 'grid', 'none', 'swiper'])
            ? $atts['mobile_animation']
            : 'carousel';
        
        $tablet_animation = isset($atts['tablet_view']) && in_array($atts['tablet_view'], ['carousel', 'ticker', 'stacked', 'grid', 'none', 'swiper'])
            ? $atts['tablet_view']
            : 'grid';
        

        echo '<div class="mns-currency-banner-wrapper">';

        $classes = [
            'mns-currency-banner-modern',
            $animation_class,
            'mns-banner-mobile-anim-' . sanitize_html_class( $mobile_animation ),
            'mns-banner-tablet-anim-' . sanitize_html_class( $tablet_animation )
        ];

        // Add hover effect class if enabled
        if ( ( $atts['enable_hover_effect'] ?? 'yes' ) === 'yes' ) {
            $classes[] = 'mns-hover-effects-enabled';
        }

        echo '<div id="' . esc_attr( $banner_id ) . '" class="' . esc_attr( implode( ' ', $classes ) ) . '"';
        echo ' data-auto-refresh="' . esc_attr( $atts['auto_refresh'] ?? '30' ) . '"';
        echo ' data-refresh-interval="' . esc_attr( $atts['refresh_interval'] ?? '30' ) . '"';
        echo ' data-show-change="' . esc_attr( $atts['show_change'] ?? 'yes' ) . '"';
        echo ' data-show-time="' . esc_attr( $atts['show_time'] ?? 'yes' ) . '"';
        echo ' data-show-symbol="' . esc_attr( $atts['show_symbol'] ?? 'no' ) . '"';
        echo ' data-animation="' . esc_attr( $atts['animation'] ?? 'slide' ) . '"';
        echo ' data-mobile-animation="' . esc_attr($mobile_animation) . '"';
        echo ' data-tablet-animation="' . esc_attr($tablet_animation) . '"';
        echo ' data-style="' . esc_attr( $atts['style'] ?? 'modern' ) . '"';
        echo $banner_inline_style . '>';
        
        echo '<div class="mns-banner-container-modern">';
        
        foreach ( $currencies as $index => $item ) {
            $this->render_currency_item( $item['currency'], $item['data'], $atts );
        }
        
        echo '</div>'; // .mns-banner-container-modern
        
        if ( ($atts['show_time'] ?? 'yes') === 'yes' ) {
            echo '<div class="mns-banner-timestamp-modern">';
            echo '<span class="mns-update-time-modern">' . __( 'بروزرسانی شد', 'mns-navasan-plus' ) . ': <span class="mns-time-value-modern">' . current_time( 'H:i' ) . '</span></span>';
            echo '</div>';
        }
        
        echo '</div>'; // .mns-currency-banner-modern
        echo '</div>'; // .mns-currency-banner-wrapper
    }

    private function render_currency_item( Currency $currency, array $data, array $atts ): void {
        $change_class = $data['change_pct'] > 0 ? 'positive' : ( $data['change_pct'] < 0 ? 'negative' : 'neutral' );

        echo '<div class="mns-currency-item-modern" data-currency-id="' . esc_attr( $currency->get_id() ) . '">';

        // Currency icon
        echo '<div class="mns-currency-icon-modern">';
        // Use the currency code as text instead of image since get_image_url() doesn't exist
        echo '<div class="mns-currency-code-text">' . esc_html( $currency->get_code() ) . '</div>';
        echo '</div>';

        // Currency info
        echo '<div class="mns-currency-info-modern">';
        echo '<div class="mns-currency-name-modern">' . esc_html( $currency->get_name() ) . '</div>';
        if ( $currency->get_code() ) {
            echo '<div class="mns-currency-code-modern">' . esc_html( $currency->get_code() ) . '</div>';
        }
        echo '</div>';

        // Price display
        echo '<div class="mns-currency-price-modern">';
        $price_text = number_format( (float) $data['rate'], 0 );
        if ( ($atts['show_symbol'] ?? 'no') === 'yes' ) {
            $price_text .= ' ' . $currency->get_symbol();
        }
        echo '<div class="mns-price-value-modern" data-rate="' . esc_attr( $data['rate'] ) . '">' . esc_html( $price_text ) . '</div>';

        if ( ($atts['show_change'] ?? 'yes') === 'yes' ) {
            echo '<div class="mns-price-change-modern mns-change-' . esc_attr( $change_class ) . '-modern">';
            $change_symbol = $data['change_pct'] > 0 ? '+' : '';
            echo '<span class="mns-change-value-modern">' . esc_html( $change_symbol . number_format( (float) $data['change_pct'], 2 ) . '%' ) . '</span>';
            echo '</div>';
        }
        echo '</div>';

        echo '</div>';
    }

    private function get_wrapper_style( array $atts ): string {
        $styles = [];
        if ( !empty($atts['width']) && $atts['width'] !== '100%' ) {
            $width = esc_attr( $atts['width'] );
            if ( is_numeric( $width ) ) {
                $width .= 'px';
            }
            $styles[] = 'width: ' . $width;
        }
        return empty( $styles ) ? '' : ' style="' . esc_attr( implode( '; ', $styles ) ) . '"';
    }

    private function get_banner_style( array $atts ): string {
        $styles = [];
        if ( !empty($atts['border_radius']) && $atts['border_radius'] !== '8' ) {
            $styles[] = 'border-radius: ' . intval( $atts['border_radius'] ) . 'px';
        }
        if ( !empty($atts['padding']) && $atts['padding'] !== '20' ) {
            $styles[] = 'padding: ' . intval( $atts['padding'] ) . 'px';
        }
        if ( !empty($atts['margin']) && $atts['margin'] !== '10' ) {
            $styles[] = 'margin: ' . intval( $atts['margin'] ) . 'px';
        }

        $shadow_map = [
            'light'  => '0 2px 8px rgba(0,0,0,0.1)',
            'medium' => '0 4px 16px rgba(0,0,0,0.15)',
            'heavy'  => '0 8px 32px rgba(0,0,0,0.2)',
            'none'   => 'none',
        ];
        if ( isset( $shadow_map[ $atts['shadow'] ?? 'light' ] ) ) {
            $styles[] = 'box-shadow: ' . $shadow_map[ $atts['shadow'] ?? 'light' ];
        }

        if ( !empty($atts['background']) && $atts['background'] === 'custom' && ! empty( $atts['background_color'] ) ) {
            $bg_color = sanitize_hex_color( $atts['background_color'] );
            if ( $bg_color ) {
                $styles[] = 'background: ' . $bg_color . ' !important';
            }
        }

        return empty( $styles ) ? '' : ' style="' . esc_attr( implode( '; ', $styles ) ) . '"';
    }

    private function generate_and_queue_styles( string $banner_id, array $atts ): void {
        $css = '';
        $selector_prefix = '#' . esc_attr( $banner_id );

        // Item spacing
        if ( !empty($atts['item_spacing']) && $atts['item_spacing'] !== '15' ) {
            $spacing = intval( $atts['item_spacing'] );
            $css .= "$selector_prefix .mns-currency-item-modern { margin-right: {$spacing}px; }";
            $css .= "$selector_prefix .mns-currency-item-modern:last-child { margin-right: 0; }";
            $css .= "@media (max-width: 768px) { $selector_prefix .mns-currency-item-modern { margin-right: 0; margin-bottom: {$spacing}px; } }";
        }

        // Font styling
        if ( (!empty($atts['font_size']) && $atts['font_size'] !== '16') || (!empty($atts['font_color']) && $atts['font_color'] !== '#333333') ) {
            $font_styles = '';
            if ( !empty($atts['font_size']) && $atts['font_size'] !== '16' ) {
                $font_styles .= 'font-size: ' . intval( $atts['font_size'] ) . 'px;';
            }
            if ( !empty($atts['font_color']) && $atts['font_color'] !== '#333333' ) {
                $font_color = sanitize_hex_color( $atts['font_color'] );
                if ( $font_color ) {
                    $font_styles .= 'color: ' . $font_color . ';';
                }
            }
            if ( $font_styles ) {
                $css .= "$selector_prefix .mns-currency-name-modern, $selector_prefix .mns-currency-code-modern, $selector_prefix .mns-price-value-modern, $selector_prefix .mns-change-value-modern, $selector_prefix .mns-update-time-modern { $font_styles }";
            }
        }

        if ( ! empty( $css ) ) {
            wp_add_inline_style( 'mns-currency-banner-modern', $css );
        }
    }



    /**
     * AJAX handler for getting currency rates
     */
    public function ajax_get_rates(): void {
        if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_REQUEST['nonce'] ), 'mns_currency_rates' ) ) {
            wp_send_json_error( ['message' => 'Security check failed.'], 403 );
            return;
        }

        if ( ! isset( $_REQUEST['currency_ids'] ) || empty( $_REQUEST['currency_ids'] ) ) {
            wp_send_json_error( ['message' => 'No currency IDs provided.'], 400 );
            return;
        }

        $currency_ids_raw = sanitize_text_field( $_REQUEST['currency_ids'] );
        $currency_ids = array_filter( array_map( 'intval', explode( ',', $currency_ids_raw ) ) );

        if ( empty( $currency_ids ) ) {
            wp_send_json_error( ['message' => 'No valid currency IDs provided.'], 400 );
            return;
        }

        set_time_limit(30);

        $rates = [];
        $errors = [];

        $posts = get_posts([
            'post_type'      => 'mnsnp_currency',
            'post__in'       => $currency_ids,
            'posts_per_page' => count($currency_ids),
            'orderby'        => 'post__in',
        ]);

        $found_ids = [];

        foreach ($posts as $post) {
            try {
                $currency = new Currency($post);
                $rates[$post->ID] = $currency->to_array();
                $found_ids[] = $post->ID;
            } catch (\Exception $e) {
                $errors[$post->ID] = $e->getMessage();
            }
        }

        $missing_ids = array_diff($currency_ids, $found_ids);
        foreach ($missing_ids as $missing_id) {
            $errors[$missing_id] = 'Currency post not found';
        }

        if (empty($rates) && empty($errors)) {
            wp_send_json_error([
                'message' => 'No currency data found',
                'code' => 'no_data',
                'currency_ids' => $currency_ids
            ]);
            return;
        }

        $response = [
            'rates' => $rates,
            'timestamp' => current_time('H:i:s')
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        wp_send_json_success($response);
    }
}