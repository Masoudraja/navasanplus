<?php
namespace MNS\NavasanPlus\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\DB;

final class AssignFormulaPage {

    private const SLUG = 'mnsnp-assign-formula';

    public function run(): void {
        add_action( 'admin_menu', [ $this, 'menu' ] );
        add_action( 'admin_post_mnsnp_assign_formula', [ $this, 'handle' ] );
    }

    public function menu(): void {
        // If you have an independent plugin menu, use it as parent
        $parent = class_exists('\MNS\NavasanPlus\Admin\Menu')
            ? \MNS\NavasanPlus\Admin\Menu::SLUG
            : 'woocommerce';

        add_submenu_page(
            $parent,
            __( 'Assign Formula to Products', 'mns-navasan-plus' ),
            __( 'Tools: Assign Formula', 'mns-navasan-plus' ),
            'manage_woocommerce',
            self::SLUG,
            [ $this, 'render' ]
        );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Access denied.', 'mns-navasan-plus' ) );
        }

        $formulas = get_posts( [
            'post_type'      => 'mnsnp_formula',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ] );

        $cats = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
        $tags = get_terms( [ 'taxonomy' => 'product_tag', 'hide_empty' => false ] );

        if ( isset( $_GET['mnsnp_done'] ) ) {
            $ok   = $_GET['mnsnp_done'] === 'ok';
            $msg  = isset( $_GET['mnsnp_msg'] ) ? wp_unslash( $_GET['mnsnp_msg'] ) : '';
            printf(
                '<div class="notice %s"><p>%s</p></div>',
                $ok ? 'notice-success' : 'notice-error',
                esc_html( $msg ?: ( $ok ? __( 'Done.', 'mns-navasan-plus' ) : __( 'Failed.', 'mns-navasan-plus' ) ) )
            );
        }

        echo '<div class="wrap"><h1>' . esc_html__( 'Assign Formula to Products', 'mns-navasan-plus' ) . '</h1>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="max-width:900px">';
        wp_nonce_field( 'mnsnp_assign_formula', '_mnsnp_nonce' );
        echo '<input type="hidden" name="action" value="mnsnp_assign_formula" />';

        echo '<table class="form-table"><tbody>';

        // Formula
        echo '<tr><th><label for="mnsnp_formula_id">' . esc_html__( 'Formula', 'mns-navasan-plus' ) . '</label></th><td>';
        echo '<select id="mnsnp_formula_id" name="mnsnp_formula_id" required>';
        echo '<option value="">' . esc_html__( '— Select —', 'mns-navasan-plus' ) . '</option>';
        foreach ( $formulas as $fid ) {
            printf('<option value="%d">%s</option>', (int)$fid, esc_html( get_the_title($fid) ) );
        }
        echo '</select></td></tr>';

        // IDs
        echo '<tr><th><label for="mnsnp_ids">' . esc_html__( 'Product IDs', 'mns-navasan-plus' ) . '</label></th><td>';
        echo '<textarea id="mnsnp_ids" name="mnsnp_ids" rows="3" class="large-text" placeholder="e.g. 12, 34-40, 99"></textarea>';
        echo '<p class="description">' . esc_html__( 'Optional. Comma-separated. Ranges allowed (10-15).', 'mns-navasan-plus' ) . '</p>';
        echo '</td></tr>';

        // Cats
        echo '<tr><th><label for="mnsnp_cats">' . esc_html__( 'Categories', 'mns-navasan-plus' ) . '</label></th><td>';
        echo '<select id="mnsnp_cats" name="mnsnp_cats[]" multiple size="6" class="regular-text">';
        foreach ( $cats as $t ) printf('<option value="%d">%s</option>', (int)$t->term_id, esc_html($t->name));
        echo '</select></td></tr>';

        // Tags
        echo '<tr><th><label for="mnsnp_tags">' . esc_html__( 'Tags', 'mns-navasan-plus' ) . '</label></th><td>';
        echo '<select id="mnsnp_tags" name="mnsnp_tags[]" multiple size="6" class="regular-text">';
        foreach ( $tags as $t ) printf('<option value="%d">%s</option>', (int)$t->term_id, esc_html($t->name));
        echo '</select></td></tr>';

        // Options
        echo '<tr><th>' . esc_html__( 'Options', 'mns-navasan-plus' ) . '</th><td>';
        echo '<label><input type="checkbox" name="mnsnp_dry" value="1" /> ' .
             esc_html__( 'Dry run (no changes)', 'mns-navasan-plus' ) . '</label><br/>';
        echo '<label><input type="checkbox" name="mnsnp_set_active" value="1" checked /> ' .
             esc_html__( 'Enable rate-based pricing', 'mns-navasan-plus' ) . '</label><br/>';
        echo '<label><input type="checkbox" name="mnsnp_set_advanced" value="1" checked /> ' .
             esc_html__( 'Set dependency to Advanced (Formula)', 'mns-navasan-plus' ) . '</label><br/>';
        echo '<label><input type="checkbox" name="mnsnp_overwrite" value="1" /> ' .
             esc_html__( 'Overwrite existing assigned formula (if any)', 'mns-navasan-plus' ) . '</label><br/>';
        echo '<label><input type="checkbox" name="mnsnp_copy_defaults" value="1" /> ' .
             esc_html__( 'Copy formula variables defaults into product overrides', 'mns-navasan-plus' ) . '</label><br/>';
        echo '<label><input type="checkbox" name="mnsnp_clear_simple" value="1" /> ' .
             esc_html__( 'Clear simple pricing fields', 'mns-navasan-plus' ) . '</label>';
        echo '</td></tr>';

        echo '</tbody></table>';
        submit_button( __( 'Assign Now', 'mns-navasan-plus' ) );
        echo '</form></div>';
    }

    public function handle(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Access denied.', 'mns-navasan-plus' ) );
        }
        check_admin_referer( 'mnsnp_assign_formula', '_mnsnp_nonce' );

        $db = DB::instance();

        $formula_id    = isset($_POST['mnsnp_formula_id']) ? (int) $_POST['mnsnp_formula_id'] : 0;
        $dry           = ! empty($_POST['mnsnp_dry']);
        $set_active    = ! empty($_POST['mnsnp_set_active']);
        $set_advanced  = ! empty($_POST['mnsnp_set_advanced']);
        $overwrite     = ! empty($_POST['mnsnp_overwrite']);
        $copy_defaults = ! empty($_POST['mnsnp_copy_defaults']);
        $clear_simple  = ! empty($_POST['mnsnp_clear_simple']);

        if ( $formula_id <= 0 || get_post_type( $formula_id ) !== 'mnsnp_formula' ) {
            $this->redir(false, __( 'Please select a valid formula.', 'mns-navasan-plus' ));
            return;
        }

        $ids = $this->parse_ids_list( isset($_POST['mnsnp_ids']) ? (string) wp_unslash($_POST['mnsnp_ids']) : '' );

        $tax_query = [];
        if ( ! empty($_POST['mnsnp_cats']) && is_array($_POST['mnsnp_cats']) ) {
            $tax_query[] = ['taxonomy'=>'product_cat','field'=>'term_id','terms'=>array_map('intval', $_POST['mnsnp_cats'])];
        }
        if ( ! empty($_POST['mnsnp_tags']) && is_array($_POST['mnsnp_tags']) ) {
            $tax_query[] = ['taxonomy'=>'product_tag','field'=>'term_id','terms'=>array_map('intval', $_POST['mnsnp_tags'])];
        }

        if ( empty($ids) ) {
            $q = new \WP_Query([
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'tax_query' => $tax_query ?: [],
            ]);
            $ids = $q->posts ?: [];
        }
        if ( empty($ids) ) {
            $this->redir(false, __( 'No products matched.', 'mns-navasan-plus' ));
            return;
        }

        $formula_vars = $copy_defaults ? $db->read_post_meta($formula_id, 'formula_variables', []) : [];
        if ( ! is_array($formula_vars) ) $formula_vars = [];

        $matched = count($ids);
        $skipped_existing = 0;
        $would_update = 0;
        $updated = 0;

        foreach ( $ids as $pid ) {
            $pid = (int) $pid; if ( $pid <= 0 ) continue;

            $current_fid = get_post_meta($pid, $db->full_meta_key('formula_id'), true);
            $has_existing = ! empty($current_fid);

            if ( $has_existing && ! $overwrite ) {
                $skipped_existing++;
                continue;
            }

            if ( $dry ) {
                $would_update++;
                continue;
            }

            // Write
            update_post_meta($pid, $db->full_meta_key('formula_id'), $formula_id);

            if ( $set_active ) {
                update_post_meta($pid, $db->full_meta_key('active'), 1);
            }
            if ( $set_advanced ) {
                update_post_meta($pid, $db->full_meta_key('dependence_type'), 'advanced');
            }
            if ( $clear_simple ) {
                delete_post_meta($pid, $db->full_meta_key('currency_id'));
                delete_post_meta($pid, $db->full_meta_key('profit_type'));
                delete_post_meta($pid, $db->full_meta_key('profit_value'));
                delete_post_meta($pid, $db->full_meta_key('rounding_type'));
                delete_post_meta($pid, $db->full_meta_key('rounding_value'));
                delete_post_meta($pid, $db->full_meta_key('rounding_side'));
                delete_post_meta($pid, $db->full_meta_key('ceil_price'));
                delete_post_meta($pid, $db->full_meta_key('floor_price'));
            }

            if ( $copy_defaults ) {
                $overrides = get_post_meta($pid, $db->full_meta_key('formula_variables'), true);
                if ( ! is_array($overrides) ) $overrides = [];
                $map = [];
                foreach ( $formula_vars as $code => $row ) {
                    $map[(string)$code] = ['regular' => (string)($row['value'] ?? '')];
                }
                $overrides[$formula_id] = $map;
                update_post_meta($pid, $db->full_meta_key('formula_variables'), $overrides);
            }

            $updated++;
        }

        if ( $dry ) {
            $msg = sprintf(
                __( 'Dry run: matched=%1$d, would_update=%2$d, skipped_existing=%3$d', 'mns-navasan-plus' ),
                (int)$matched, (int)$would_update, (int)$skipped_existing
            );
            $this->redir(true, $msg);
            return;
        }

        $msg = sprintf(
            __( 'Assigned to %1$d products. Skipped existing: %2$d', 'mns-navasan-plus' ),
            (int)$updated, (int)$skipped_existing
        );
        $this->redir(true, $msg);
    }

    private function redir( bool $ok, string $msg ): void {
        $url = add_query_arg( [
            'page'       => self::SLUG,
            'mnsnp_done' => $ok ? 'ok' : 'fail',
            'mnsnp_msg'  => rawurlencode( $msg ),
        ], admin_url( 'admin.php' ) );
        wp_safe_redirect( $url );
        exit;
    }

    private function parse_ids_list( string $raw ): array {
        $raw = trim($raw);
        if ($raw === '') return [];
        $parts = preg_split('/\s*,\s*/', $raw);
        $ids = [];
        foreach ( $parts as $p ) {
            if ( preg_match('/^\d+\-\d+$/', $p) ) {
                [ $a, $b ] = array_map('intval', explode('-', $p, 2) );
                if ( $a > 0 && $b >= $a ) { $ids = array_merge($ids, range($a, $b)); }
            } elseif ( ctype_digit($p) ) {
                $ids[] = (int)$p;
            }
        }
        return array_values( array_unique( array_filter( $ids ) ) );
    }
}