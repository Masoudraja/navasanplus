<?php
/**
 * PublicNS\CurrencyBanner
 *
 * Beautiful full-width currency banner with real-time price display
 * Provides shortcode functionality to display selected currencies anywhere on the site
 *
 * File: includes/PublicNS/CurrencyBanner.php
 */

namespace MNS\NavasanPlus\PublicNS;

use MNS\NavasanPlus\DB;
use MNS\NavasanPlus\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CurrencyBanner {

    /** @var CurrencyBanner|null */
    private static ?self $instance = null;

    /** @var bool Flag to check if shortcode is used on the page */
    private bool $shortcode_is_used = false;

    /** @var array Stores custom styles for all banners on a page */
    private array $custom_styles_queue = [];

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
        add_action( 'wp_footer', [ $this, 'print_custom_styles' ] );
    }

    /**
     * Enqueue banner assets
     */
    public function enqueue_assets(): void {
        // Register assets to be enqueued later if the shortcode is used.
        wp_register_style(
            'mns-currency-banner',
            Helpers::plugin_url( 'assets/css/currency-banner.css' ),
            [],
            MNS_NAVASAN_PLUS_VER
        );

        wp_register_script(
            'mns-currency-banner',
            Helpers::plugin_url( 'assets/js/currency-banner.js' ),
            [ 'jquery' ],
            MNS_NAVASAN_PLUS_VER,
            true
        );

        wp_localize_script( 'mns-currency-banner', 'mnsCurrencyBanner', [
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
     * Render currency banner shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_shortcode( $atts = [] ): string {
        if ( ! $this->shortcode_is_used ) {
            $this->shortcode_is_used = true;
            wp_enqueue_style( 'mns-currency-banner' );
            wp_enqueue_script( 'mns-currency-banner' );
        }

        $atts = shortcode_atts( [
            'currencies'       => '',          // comma-separated currency IDs
            'style'            => 'modern',    // modern, minimal, classic
            'auto_refresh'     => '30',        // seconds (0 = disabled)
            'show_change'      => 'yes',       // yes/no
            'show_time'        => 'yes',       // yes/no
            'show_symbol'      => 'no',        // yes/no
            'animation'        => 'slide',     // slide, fade, none
            'height'           => 'auto',      // auto, compact, tall
            'background'       => 'gradient',  // gradient, solid, transparent, custom
            'background_color' => '#667eea',   // custom background color
            'columns'          => 'auto',      // auto, 2, 3, 4, 5, 6
            'font_size'        => '16',        // font size in px
            'font_color'       => '#333333',   // text color
            'border_radius'    => '8',         // border radius in px
            'padding'          => '20',        // padding in px
            'margin'           => '10',        // margin in px
            'item_spacing'     => '15',        // spacing between items in px
            'shadow'           => 'light',     // none, light, medium, heavy
            'width'            => '100%',      // banner width
        ], $atts, 'mns_currency_banner' );

        // Get currencies to display
        $currencies = $this->get_currencies_for_banner( $atts['currencies'] );
        
        if ( empty( $currencies ) ) {
            return '<div class="mns-currency-banner-error">' . __( 'هیچ ارزی برای نمایش موجود نیست.', 'mns-navasan-plus' ) . '</div>';
        }

        // Generate unique banner ID
        $banner_id = 'mns-currency-banner-' . wp_generate_password( 8, false, false );

        // Render banner
        ob_start();
        $this->render_banner_template( $banner_id, $currencies, $atts );
        return ob_get_clean();
    }

    /**
     * Get currencies for banner display
     */
    private function get_currencies_for_banner( string $currency_ids ): array {
        $currencies = [];
        
        if ( empty( $currency_ids ) ) {
            // Get all published currencies if none specified
            $posts = get_posts([
                'post_type'      => 'mnsnp_currency',
                'posts_per_page' => 10, // Limit for performance
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC'
            ]);
        } else {
            // Get specific currencies
            $ids = array_filter( array_map( 'intval', explode( ',', $currency_ids ) ) );
            $posts = get_posts([
                'post_type'    => 'mnsnp_currency',
                'post_status'  => 'publish',
                'post__in'     => $ids,
                'orderby'      => 'post__in'
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
     * Render banner template
     */
    private function render_banner_template( string $banner_id, array $currencies, array $atts ): void {
        $style_class = 'mns-banner-style-' . sanitize_html_class( $atts['style'] );
        $height_class = 'mns-banner-height-' . sanitize_html_class( $atts['height'] );
        $bg_class = 'mns-banner-bg-' . sanitize_html_class( $atts['background'] );
        $animation_class = 'mns-banner-anim-' . sanitize_html_class( $atts['animation'] );
        
        $columns = $atts['columns'] === 'auto' ? min( count( $currencies ), 6 ) : min( intval( $atts['columns'] ), 6 );
        $column_class = 'mns-banner-cols-' . $columns;        

        $this->generate_and_queue_styles( $banner_id, $atts );

        $wrapper_inline_style = $this->get_wrapper_style( $atts );
        $banner_inline_style = $this->get_banner_style( $atts );

        echo '<div class="mns-currency-banner-wrapper"' . $wrapper_inline_style . '>';

        $classes = [
            'mns-currency-banner',
            $style_class,
            $height_class,
            $bg_class,
            $animation_class,
            $column_class
        ];

        echo '<div id="' . esc_attr( $banner_id ) . '" class="' . esc_attr( implode( ' ', $classes ) ) . '"';
        echo ' data-auto-refresh="' . esc_attr( $atts['auto_refresh'] ) . '"';
        echo ' data-show-change="' . esc_attr( $atts['show_change'] ) . '"';
        echo ' data-show-time="' . esc_attr( $atts['show_time'] ) . '"';
        echo ' data-show-symbol="' . esc_attr( $atts['show_symbol'] ) . '"';
        echo $banner_inline_style . '>';
        
        echo '<div class="mns-banner-container">';
        
        foreach ( $currencies as $index => $item ) {
            $this->render_currency_item( $item['currency'], $item['data'], $atts );
        }
        
        echo '</div>'; // .mns-banner-container
        
        if ( $atts['show_time'] === 'yes' ) {
            echo '<div class="mns-banner-timestamp">';
            echo '<span class="mns-update-time">' . __( 'بروزرسانی شد', 'mns-navasan-plus' ) . ': <span class="mns-time-value">' . current_time( 'H:i' ) . '</span></span>';
            echo '</div>';
        }
        
        echo '</div>'; // .mns-currency-banner
        echo '</div>'; // .mns-currency-banner-wrapper
    }

    private function render_currency_item( Currency $currency, array $data, array $atts ): void {
        $change_class = $data['change_pct'] > 0 ? 'positive' : ( $data['change_pct'] < 0 ? 'negative' : 'neutral' );

        echo '<div class="mns-currency-item" data-currency-id="' . esc_attr( $currency->get_id() ) . '">';

        // Currency info
        echo '<div class="mns-currency-info">';
        echo '<div class="mns-currency-name">' . esc_html( $currency->get_name() ) . '</div>';
        if ( $currency->get_code() ) {
            echo '<div class="mns-currency-code">' . esc_html( $currency->get_code() ) . '</div>';
        }
        echo '</div>';

        // Price display
        echo '<div class="mns-currency-price">';
        $price_text = number_format( (float) $data['rate'], 0 );
        if ( $atts['show_symbol'] === 'yes' ) {
            $price_text .= ' ' . $currency->get_symbol();
        }
        echo '<div class="mns-price-value" data-rate="' . esc_attr( $data['rate'] ) . '">' . esc_html( $price_text ) . '</div>';

        if ( $atts['show_change'] === 'yes' ) {
            echo '<div class="mns-price-change mns-change-' . esc_attr( $change_class ) . '">';
            $change_symbol = $data['change_pct'] > 0 ? '+' : '';
            echo '<span class="mns-change-value">' . esc_html( $change_symbol . number_format( (float) $data['change_pct'], 2 ) . '%' ) . '</span>';
            echo '</div>';
        }
        echo '</div>';

        echo '</div>';
    }

    private function get_wrapper_style( array $atts ): string {
        $styles = [];
        if ( $atts['width'] !== '100%' ) {
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
        if ( $atts['border_radius'] !== '8' ) {
            $styles[] = 'border-radius: ' . intval( $atts['border_radius'] ) . 'px';
        }
        if ( $atts['padding'] !== '20' ) {
            $styles[] = 'padding: ' . intval( $atts['padding'] ) . 'px';
        }
        if ( $atts['margin'] !== '10' ) {
            $styles[] = 'margin: ' . intval( $atts['margin'] ) . 'px';
        }

        $shadow_map = [
            'light'  => '0 2px 8px rgba(0,0,0,0.1)',
            'medium' => '0 4px 16px rgba(0,0,0,0.15)',
            'heavy'  => '0 8px 32px rgba(0,0,0,0.2)',
            'none'   => 'none',
        ];
        if ( isset( $shadow_map[ $atts['shadow'] ] ) ) {
            $styles[] = 'box-shadow: ' . $shadow_map[ $atts['shadow'] ];
        }

        if ( $atts['background'] === 'custom' && ! empty( $atts['background_color'] ) ) {
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
        if ( $atts['item_spacing'] !== '15' ) {
            $spacing = intval( $atts['item_spacing'] );
            $css .= "$selector_prefix .mns-currency-item { margin-right: {$spacing}px; }";
            $css .= "$selector_prefix .mns-currency-item:last-child { margin-right: 0; }";
            $css .= "@media (max-width: 768px) { $selector_prefix .mns-currency-item { margin-right: 0; margin-bottom: {$spacing}px; } }";
        }

        // Font styling
        if ( $atts['font_size'] !== '16' || $atts['font_color'] !== '#333333' ) {
            $font_styles = '';
            if ( $atts['font_size'] !== '16' ) {
                $font_styles .= 'font-size: ' . intval( $atts['font_size'] ) . 'px;';
            }
            if ( $atts['font_color'] !== '#333333' ) {
                $font_color = sanitize_hex_color( $atts['font_color'] );
                if ( $font_color ) {
                    $font_styles .= 'color: ' . $font_color . ';';
                }
            }
            if ( $font_styles ) {
                $css .= "$selector_prefix .mns-currency-name, $selector_prefix .mns-currency-code, $selector_prefix .mns-price-value, $selector_prefix .mns-change-value, $selector_prefix .mns-update-time { $font_styles }";
            }
        }

        if ( ! empty( $css ) ) {
            $this->custom_styles_queue[] = $css;
        }
    }

    /**
     * Print all generated custom styles in the footer.
     */
    public function print_custom_styles(): void {
        if ( ! $this->shortcode_is_used || empty( $this->custom_styles_queue ) ) {
            return;
        }
        echo '<style type="text/css">' . implode( "\n", $this->custom_styles_queue ) . '</style>';
    }

    /**
     * AJAX handler for getting currency rates
     */
    public function ajax_get_rates(): void {
        if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'mns_currency_rates' ) ) {
            wp_die( 'Security check failed' );
        }

        $currency_ids = array_filter( array_map( 'intval', explode( ',', $_REQUEST['currency_ids'] ?? '' ) ) );
        
        if ( empty( $currency_ids ) ) {
            wp_send_json_error( 'No currency IDs provided' );
        }

        $rates = [];
        foreach ( $currency_ids as $currency_id ) {
            $post = get_post( $currency_id );
            if ( $post && $post->post_type === 'mnsnp_currency' ) {
                $currency = new Currency( $post );
                $rates[ $currency_id ] = $currency->to_array();
            }
        }

        wp_send_json_success([
            'rates' => $rates,
            'timestamp' => current_time( 'H:i' )
        ]);
    }

}