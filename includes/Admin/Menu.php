<?php
namespace MNS\NavasanPlus\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Menu {
    public const SLUG = 'mnsnp-main';

    public function run(): void {
        add_action('admin_menu', [$this, 'register']);
    }

    public function register(): void {
        add_menu_page(
            __( 'Navasan Plus', 'mns-navasan-plus' ),
            __( 'Navasan Plus', 'mns-navasan-plus' ),
            'manage_woocommerce',
            self::SLUG,
            [$this, 'landing'],
            'dashicons-chart-line',
            56
        );

        // Add Export/Import submenu
        add_submenu_page(
            self::SLUG,
            __( 'Export / Import', 'mns-navasan-plus' ),
            __( 'Export / Import', 'mns-navasan-plus' ),
            'manage_options',
            'mns-navasan-plus-export-import',
            [ $this, 'render_export_import_page' ]
        );
    }

    /**
     * Render Export/Import page
     */
    public function render_export_import_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Access denied.', 'mns-navasan-plus' ) );
        }

        if ( class_exists( 'MNS\NavasanPlus\Tools\ExportImport' ) ) {
            \MNS\NavasanPlus\Tools\ExportImport::instance()->render_page();
        }
    }

    public function landing(): void {
        if ( ! current_user_can('manage_woocommerce') ) {
            wp_die( __( 'Access denied.', 'mns-navasan-plus' ) );
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Navasan Plus', 'mns-navasan-plus' ) . '</h1>';
        echo '<p>' . esc_html__( 'Use the left submenu: Settings, Tools, Currencies, Formulas, Currency Banner.', 'mns-navasan-plus' ) . '</p>';
        
        // Show quick stats
        $currencies_count = wp_count_posts( 'mnsnp_currency' )->publish ?? 0;
        $formulas_count = wp_count_posts( 'mnsnp_formula' )->publish ?? 0;
        
        echo '<div class="mns-admin-dashboard">';
        echo '<div class="mns-stats-grid">';
        echo '<div class="mns-stat-card">';
        echo '<h3>' . __( 'Currencies', 'mns-navasan-plus' ) . '</h3>';
        echo '<span class="mns-stat-number">' . esc_html( $currencies_count ) . '</span>';
        echo '</div>';
        echo '<div class="mns-stat-card">';
        echo '<h3>' . __( 'Formulas', 'mns-navasan-plus' ) . '</h3>';
        echo '<span class="mns-stat-number">' . esc_html( $formulas_count ) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="mns-quick-actions">';
        echo '<h3>' . __( 'Quick Actions', 'mns-navasan-plus' ) . '</h3>';
        echo '<a href="' . admin_url( 'admin.php?page=mnsnp-currency-banner' ) . '" class="button button-primary">' . __( 'Create Currency Banner', 'mns-navasan-plus' ) . '</a> ';
        echo '<a href="' . admin_url( 'post-new.php?post_type=mnsnp_currency' ) . '" class="button button-secondary">' . __( 'Add Currency', 'mns-navasan-plus' ) . '</a> ';
        echo '<a href="' . admin_url( 'post-new.php?post_type=mnsnp_formula' ) . '" class="button button-secondary">' . __( 'Add Formula', 'mns-navasan-plus' ) . '</a>';
        echo '</div>';
        echo '</div>';
        
        // Simple dashboard styles
        echo '<style>';
        echo '.mns-admin-dashboard { margin-top: 20px; }';
        echo '.mns-stats-grid { display: flex; gap: 20px; margin-bottom: 30px; }';
        echo '.mns-stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; min-width: 150px; }';
        echo '.mns-stat-card h3 { margin: 0 0 10px 0; color: #666; font-size: 14px; }';
        echo '.mns-stat-number { font-size: 32px; font-weight: bold; color: #0073aa; }';
        echo '.mns-quick-actions { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }';
        echo '.mns-quick-actions h3 { margin-top: 0; }';
        echo '.mns-quick-actions .button { margin-right: 10px; }';
        echo '</style>';
        echo '</div>';
    }
}