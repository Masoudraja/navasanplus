<?php
namespace MNS\NavasanPlus\Templates\Classes;

use MNS\NavasanPlus\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper to render small UI snippets/templates
 * - Safe template name (no path traversal)
 * - Theme override support: wp-content/themes/your-theme/mns-navasan-plus/{template}.php
 * - Optional $return = true -> returns HTML instead of direct output
 * - Optional enqueue of asset handles (string or array)
 */
class Snippets {

    /**
     * Render a template file from /templates
     *
     * @param string $template   e.g. 'metaboxes/product-formula'
     * @param array  $args       variables extracted into template
     * @param array  $assets     ['styles'=>string|array, 'scripts'=>string|array]
     * @param bool   $return     true => return HTML string; false => echo (default)
     * @return string|void
     */
    public static function load_template( string $template, array $args = [], array $assets = [], bool $return = false ) {
        // 1) Sanitize template name (allow letters, numbers, _, -, /)
        $template = ltrim( $template, '/\\' );
        if ( ! preg_match( '/^[A-Za-z0-9_\-\/]+$/', $template ) ) {
            return;
        }

        // 2) Enqueue assets if provided
        if ( ! empty( $assets ) ) {
            // styles
            if ( ! empty( $assets['styles'] ) ) {
                $styles = is_array( $assets['styles'] ) ? $assets['styles'] : [ $assets['styles'] ];
                foreach ( $styles as $style ) {
                    if ( is_string( $style ) && $style !== '' ) {
                        wp_enqueue_style( $style );
                    }
                }
            }
            // scripts
            if ( ! empty( $assets['scripts'] ) ) {
                $scripts = is_array( $assets['scripts'] ) ? $assets['scripts'] : [ $assets['scripts'] ];
                foreach ( $scripts as $script ) {
                    if ( is_string( $script ) && $script !== '' ) {
                        wp_enqueue_script( $script );
                    }
                }
            }
        }

        // 3) Resolve template path — theme override first
        $relative   = 'mns-navasan-plus/' . $template . '.php';
        $theme_file = function_exists( 'locate_template' ) ? locate_template( [ $relative ] ) : '';
        $file       = $theme_file ?: Helpers::plugin_path( 'templates/' . $template . '.php' );

        /**
         * Filter: allow 3rd-parties to override template path.
         * @param string $file
         * @param string $template
         * @param array  $args
         */
        $file = apply_filters( 'mnsnp/template_path', $file, $template, $args );

        if ( ! $file || ! file_exists( $file ) ) {
            return;
        }

        // 4) Allow filtering args before extract
        $args = apply_filters( 'mnsnp/template_args', $args, $template, $file );

        // 5) Render
        if ( $return ) {
            ob_start();
            extract( $args, EXTR_SKIP );
            include $file;
            return ob_get_clean();
        }

        extract( $args, EXTR_SKIP );
        include $file;
    }

    /**
     * Small helper: percentage change badge
     *
     * @param float $previous
     * @param float $current
     * @param int   $decimals
     * @return string
     */
    public static function percentage_change( $previous, $current, $decimals = 2 ) {
        $previous = (float) $previous;
        $current  = (float) $current;

        $change = ( $previous == 0.0 ) ? 0.0 : ( ( $current - $previous ) / $previous ) * 100.0;
        $direction = $change > 0 ? 'up' : ( $change < 0 ? 'down' : 'none' );
        $value     = Helpers::format_decimal( abs( $change ), (int) $decimals );

        return sprintf(
            '<span class="mns-percentage-change mns-percentage-%s">%s&#37;</span>',
            esc_attr( $direction ),
            esc_html( $value )
        );
    }
}