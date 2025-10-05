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
        if ( ! $this->should_load_assets() ) {
            return;
        }

        wp_enqueue_style(
            'mns-currency-banner',
            Helpers::plugin_url( 'assets/css/currency-banner.css' ),
            [],
            MNS_NAVASAN_PLUS_VER
        );

        wp_enqueue_script(
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
     * Check if banner assets should be loaded
     */
    private function should_load_assets(): bool {
        global $post;
        
        // Always load assets if we're in admin (for preview functionality)
        if ( is_admin() ) {
            return true;
        }
        
        // Load if shortcode is detected in content
        if ( $post && has_shortcode( $post->post_content, 'mns_currency_banner' ) ) {
            return true;
        }
        
        // Load if current page might contain the shortcode
        if ( is_front_page() || is_home() || is_shop() || is_product() || is_account_page() ) {
            return true;
        }
        
        // Check if we're in a Woodmart header builder context
        if ( function_exists( 'woodmart_get_current_page_id' ) ) {
            $page_id = woodmart_get_current_page_id();
            if ( $page_id && has_shortcode( get_post_field( 'post_content', $page_id ), 'mns_currency_banner' ) ) {
                return true;
            }
        }
        
        // Check global header/footer areas
        $header_content = get_option( 'woodmart_main_header_content', '' );
        if ( has_shortcode( $header_content, 'mns_currency_banner' ) ) {
            return true;
        }
        
        // Check if shortcode exists in any widget areas
        $sidebars_widgets = wp_get_sidebars_widgets();
        if ( ! empty( $sidebars_widgets ) ) {
            foreach ( $sidebars_widgets as $sidebar => $widgets ) {
                if ( is_active_sidebar( $sidebar ) ) {
                    foreach ( $widgets as $widget ) {
                        $widget_instance = get_option( 'widget_' . $widget );
                        if ( $widget_instance ) {
                            foreach ( $widget_instance as $instance ) {
                                if ( is_array( $instance ) && isset( $instance['text'] ) && has_shortcode( $instance['text'], 'mns_currency_banner' ) ) {
                                    return true;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Render currency banner shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_shortcode( $atts = [] ): string {
        // Ensure assets are loaded when shortcode is used
        if ( ! wp_script_is( 'mns-currency-banner', 'enqueued' ) ) {
            $this->enqueue_assets();
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
        
        // Build custom styles
        $custom_styles = [];
        $wrapper_styles = [];
        
        // Width styling for wrapper
        if ( $atts['width'] !== '100%' ) {
            $wrapper_styles[] = 'width: ' . esc_attr( $atts['width'] );
            if ( strpos( $atts['width'], '%' ) === false && strpos( $atts['width'], 'px' ) === false && $atts['width'] !== 'auto' ) {
                $wrapper_styles[] = 'width: ' . intval( $atts['width'] ) . 'px';
            }
        }
        
        // Font styling for banner content
        if ( $atts['font_size'] !== '16' ) {
            $custom_styles[] = 'font-size: ' . intval( $atts['font_size'] ) . 'px !important';
        }
        if ( $atts['font_color'] !== '#333333' ) {
            $font_color = sanitize_hex_color( $atts['font_color'] );
            if ( $font_color ) {
                $custom_styles[] = 'color: ' . $font_color . ' !important';
            }
        }
        
        // Layout styling
        if ( $atts['border_radius'] !== '8' ) {
            $custom_styles[] = 'border-radius: ' . intval( $atts['border_radius'] ) . 'px';
        }
        if ( $atts['padding'] !== '20' ) {
            $custom_styles[] = 'padding: ' . intval( $atts['padding'] ) . 'px';
        }
        if ( $atts['margin'] !== '10' ) {
            $custom_styles[] = 'margin: ' . intval( $atts['margin'] ) . 'px';
        }
        
        // Shadow styling
        $shadow_styles = [
            'none'   => 'box-shadow: none',
            'light'  => 'box-shadow: 0 2px 8px rgba(0,0,0,0.1)',
            'medium' => 'box-shadow: 0 4px 16px rgba(0,0,0,0.15)',
            'heavy'  => 'box-shadow: 0 8px 32px rgba(0,0,0,0.2)'
        ];
        if ( isset( $shadow_styles[ $atts['shadow'] ] ) ) {
            $custom_styles[] = $shadow_styles[ $atts['shadow'] ];
        }
        
        // Handle custom background color and other styling
        $inline_style = '';
        if ( $atts['background'] === 'custom' && ! empty( $atts['background_color'] ) ) {
            $bg_color = sanitize_hex_color( $atts['background_color'] );
            if ( $bg_color ) {
                $custom_styles[] = 'background: ' . $bg_color . ' !important';
            }
        }
        
        if ( ! empty( $custom_styles ) ) {
            $inline_style = ' style="' . esc_attr( implode( '; ', $custom_styles ) ) . '"';
        }
        
        $wrapper_inline_style = '';
        if ( ! empty( $wrapper_styles ) ) {
            $wrapper_inline_style = ' style="' . esc_attr( implode( '; ', $wrapper_styles ) ) . '"';
        }
        
        echo '<div class="mns-currency-banner-wrapper"' . $wrapper_inline_style . '>';
        
        // Add item spacing styles if custom
        if ( $atts['item_spacing'] !== '15' ) {
            $spacing = intval( $atts['item_spacing'] );
            echo '<style>';
            echo '#' . esc_attr( $banner_id ) . ' .mns-currency-item { margin-right: ' . $spacing . 'px; }';
            echo '#' . esc_attr( $banner_id ) . ' .mns-currency-item:last-child { margin-right: 0; }';
            echo '@media (max-width: 768px) { #' . esc_attr( $banner_id ) . ' .mns-currency-item { margin-right: 0; margin-bottom: ' . $spacing . 'px; } }';
            echo '</style>';
        }
        
        // Add font styling that affects all text elements
        if ( $atts['font_size'] !== '16' || $atts['font_color'] !== '#333333' ) {
            echo '<style>';
            echo '#' . esc_attr( $banner_id ) . ' .mns-currency-name,';
            echo '#' . esc_attr( $banner_id ) . ' .mns-currency-code,';
            echo '#' . esc_attr( $banner_id ) . ' .mns-price-value,';
            echo '#' . esc_attr( $banner_id ) . ' .mns-change-value,';
            echo '#' . esc_attr( $banner_id ) . ' .mns-update-time { ';
            if ( $atts['font_size'] !== '16' ) {
                echo 'font-size: ' . intval( $atts['font_size'] ) . 'px !important; ';
            }
            if ( $atts['font_color'] !== '#333333' ) {
                $font_color = sanitize_hex_color( $atts['font_color'] );
                if ( $font_color ) {
                    echo 'color: ' . $font_color . ' !important; ';
                }
            }
            echo '}';
            echo '</style>';
        }
        
        echo '<div id="' . esc_attr( $banner_id ) . '" class="mns-currency-banner ' . esc_attr( $style_class . ' ' . $height_class . ' ' . $bg_class . ' ' . $animation_class . ' ' . $column_class ) . '"';
        echo ' data-auto-refresh="' . esc_attr( $atts['auto_refresh'] ) . '"';
        echo ' data-show-change="' . esc_attr( $atts['show_change'] ) . '"';
        echo ' data-show-time="' . esc_attr( $atts['show_time'] ) . '"';
        echo ' data-show-symbol="' . esc_attr( $atts['show_symbol'] ) . '"';
        echo $inline_style . '>';
        
        echo '<div class="mns-banner-container">';
        
        foreach ( $currencies as $index => $item ) {
            $currency = $item['currency'];
            $data = $item['data'];
            $change_class = $data['change_pct'] > 0 ? 'positive' : ($data['change_pct'] < 0 ? 'negative' : 'neutral');
            
            echo '<div class="mns-currency-item" data-currency-id="' . esc_attr( $currency->get_id() ) . '">';
            
            // Currency info
            echo '<div class="mns-currency-info">';
            echo '<div class="mns-currency-name">' . esc_html( $currency->get_name() ) . '</div>';
            if ( $currency->get_code() ) {
                echo '<div class="mns-currency-code">' . esc_html( $currency->get_code() ) . '</div>';
            }
            echo '</div>';
            
            // Price display - handle symbol visibility
            echo '<div class="mns-currency-price">';
            if ( $atts['show_symbol'] === 'yes' ) {
                echo '<div class="mns-price-value" data-rate="' . esc_attr( $data['rate'] ) . '">' . esc_html( number_format( $data['rate'], 0 ) . ' ' . $currency->get_symbol() ) . '</div>';
            } else {
                echo '<div class="mns-price-value" data-rate="' . esc_attr( $data['rate'] ) . '">' . esc_html( number_format( $data['rate'], 0 ) ) . '</div>';
            }
            
            if ( $atts['show_change'] === 'yes' ) {
                echo '<div class="mns-price-change mns-change-' . esc_attr( $change_class ) . '">';
                $change_symbol = $data['change_pct'] > 0 ? '+' : '';
                echo '<span class="mns-change-value">' . esc_html( $change_symbol . number_format( $data['change_pct'], 2 ) . '%' ) . '</span>';
                echo '</div>';
            }
            echo '</div>';
            
            echo '</div>';
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