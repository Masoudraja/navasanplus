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
    }

    public function landing(): void {
        if ( ! current_user_can('manage_woocommerce') ) {
            wp_die( __( 'Access denied.', 'mns-navasan-plus' ) );
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Navasan Plus', 'mns-navasan-plus' ) . '</h1>';
        echo '<p>' . esc_html__( 'Use the left submenu: Settings, Tools, Currencies, Formulas.', 'mns-navasan-plus' ) . '</p>';
        echo '</div>';
    }
}