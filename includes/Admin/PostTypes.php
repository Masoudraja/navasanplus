<?php
namespace MNS\NavasanPlus\Admin;

use MNS\NavasanPlus\Templates\Classes\Snippets;
use MNS\NavasanPlus\DB;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the Currency, Formula and Chart post‐types
 * and customizes their admin list tables.
 */
class PostTypes {

    public function run() {
        add_action( 'init', [ __CLASS__, 'register_post_types' ] );

        // <<< CHANGE: All hook names updated from 'mnswmc' to 'mnsnp_currency'
        add_filter( 'manage_mnsnp_currency_posts_columns',      [ __CLASS__, 'currency_columns' ] );
        add_filter( 'manage_edit-mnsnp_currency_sortable_columns', [ __CLASS__, 'currency_sortable_columns' ] );
        add_action( 'manage_mnsnp_currency_posts_custom_column', [ __CLASS__, 'currency_columns_content' ], 10, 2 );
        add_action( 'pre_get_posts',                             [ __CLASS__, 'currency_sortable_columns_query' ] );

        // <<< CHANGE: All hook names updated from 'mnswmc-formula' to 'mnsnp_formula'
        add_filter( 'manage_mnsnp_formula_posts_columns',       [ __CLASS__, 'formula_columns' ] );
        add_action( 'manage_mnsnp_formula_posts_custom_column', [ __CLASS__, 'formula_columns_content' ], 10, 2 );

        // <<< CHANGE: All hook names updated from 'mnswmc-chart' to 'mnsnp_chart'
        add_filter( 'manage_mnsnp_chart_posts_columns',         [ __CLASS__, 'chart_columns' ] );
        add_action( 'manage_mnsnp_chart_posts_custom_column',   [ __CLASS__, 'chart_columns_content' ], 10, 2 );

        add_filter( 'post_row_actions',                         [ __CLASS__, 'row_actions' ], 10, 2 );
        add_action( 'pre_get_posts',                            [ __CLASS__, 'filter_products_list' ] );
    }

    public static function register_post_types() {
        $parent_menu = class_exists( '\MNS\NavasanPlus\Admin\Menu' )
            ? \MNS\NavasanPlus\Admin\Menu::SLUG
            : 'woocommerce';

        // <<< CHANGE: CPT slug changed from 'mnswmc' to 'mnsnp_currency'
        register_post_type( 'mnsnp_currency', [
            'label'               => __( 'Currency', 'mns-navasan-plus' ),
            'description'         => __( 'Rate Based Currencies', 'mns-navasan-plus' ),
            'labels'              => [
                'name'               => _x( 'Currencies', 'Post Type General Name',   'mns-navasan-plus' ),
                'singular_name'      => _x( 'Currency',  'Post Type Singular Name',  'mns-navasan-plus' ),
                'menu_name'          => __( 'Currencies',                 'mns-navasan-plus' ),
                'name_admin_bar'     => __( 'Currency',                   'mns-navasan-plus' ),
                'all_items'          => __( 'Currencies',                 'mns-navasan-plus' ),
                'add_new_item'       => __( 'Add New Currency',           'mns-navasan-plus' ),
                'edit_item'          => __( 'Edit Currency',              'mns-navasan-plus' ),
                'view_item'          => __( 'View Currency',              'mns-navasan-plus' ),
                'search_items'       => __( 'Search Currency',            'mns-navasan-plus' ),
                'not_found'          => __( 'Not found',                  'mns-navasan-plus' ),
                'not_found_in_trash' => __( 'Not found in Trash',         'mns-navasan-plus' ),
            ],
            'supports'            => [ 'title', 'thumbnail' ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => $parent_menu,
            'has_archive'         => false,
            'exclude_from_search' => true,
        ] );

        // <<< CHANGE: CPT slug changed from 'mnswmc-formula' to 'mnsnp_formula'
        register_post_type( 'mnsnp_formula', [
            'label'               => __( 'Formula', 'mns-navasan-plus' ),
            'description'         => __( 'Rate Based Formulas', 'mns-navasan-plus' ),
            'labels'              => [
                'name'               => _x( 'Formulas', 'Post Type General Name',   'mns-navasan-plus' ),
                'singular_name'      => _x( 'Formula',  'Post Type Singular Name',  'mns-navasan-plus' ),
                'menu_name'          => __( 'Formulas',                'mns-navasan-plus' ),
                'name_admin_bar'     => __( 'Formula',                 'mns-navasan-plus' ),
                'all_items'          => __( 'Formulas',                'mns-navasan-plus' ),
                'add_new_item'       => __( 'Add New Formula',         'mns-navasan-plus' ),
                'edit_item'          => __( 'Edit Formula',            'mns-navasan-plus' ),
                'view_item'          => __( 'View Formula',            'mns-navasan-plus' ),
                'search_items'       => __( 'Search Formula',          'mns-navasan-plus' ),
                'not_found'          => __( 'Not found',               'mns-navasan-plus' ),
                'not_found_in_trash' => __( 'Not found in Trash',      'mns-navasan-plus' ),
            ],
            'supports'            => [ 'title', 'thumbnail' ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => $parent_menu,
            'has_archive'         => false,
            'exclude_from_search' => true,
        ] );

        // <<< CHANGE: CPT slug changed from 'mnswmc-chart' to 'mnsnp_chart'
        register_post_type( 'mnsnp_chart', [
            'label'               => __( 'Chart', 'mns-navasan-plus' ),
            'description'         => __( 'Rate Based Charts', 'mns-navasan-plus' ),
            'labels'              => [
                'name'               => _x( 'Charts', 'Post Type General Name',   'mns-navasan-plus' ),
                'singular_name'      => _x( 'Chart',  'Post Type Singular Name',  'mns-navasan-plus' ),
                'menu_name'          => __( 'Charts',                 'mns-navasan-plus' ),
                'name_admin_bar'     => __( 'Chart',                  'mns-navasan-plus' ),
                'all_items'          => __( 'Charts',                 'mns-navasan-plus' ),
                'add_new_item'       => __( 'Add New Chart',          'mns-navasan-plus' ),
                'edit_item'          => __( 'Edit Chart',             'mns-navasan-plus' ),
                'view_item'          => __( 'View Chart',             'mns-navasan-plus' ),
                'search_items'       => __( 'Search Chart',           'mns-navasan-plus' ),
                'not_found'          => __( 'Not found',              'mns-navasan-plus' ),
                'not_found_in_trash' => __( 'Not found in Trash',     'mns-navasan-plus' ),
            ],
            'supports'            => [ 'title', 'thumbnail' ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'has_archive'         => false,
            'exclude_from_search' => true,
            'show_in_rest'        => true,
        ] );
    }

    // ===== Currency list table =====

    public static function currency_columns( $columns ) {
        unset( $columns['date'] );
        $columns['currency_id']           = __( 'ID',               'mns-navasan-plus' );
        $columns['currency_rate']         = __( 'Final Rate',       'mns-navasan-plus' );
        $columns['currency_attributes']   = __( 'Attributes',       'mns-navasan-plus' );
        $columns['currency_check_time']   = __( 'Last check time',  'mns-navasan-plus' );
        $columns['currency_update_time']  = __( 'Last update time', 'mns-navasan-plus' );
        return $columns;
    }

    public static function currency_sortable_columns( $columns ) {
        $columns['currency_rate'] = 'currency_rate';
        return $columns;
    }

    public static function currency_columns_content( $column, $post_id ) {
        // This function's logic does not need changes as it's not CPT-specific.
        $db   = DB::instance();
        $rate = (float) $db->read_post_meta( $post_id, 'currency_value', 0 );
        $hist = $db->read_post_meta( $post_id, 'currency_history', [] );
        $hist = is_array( $hist ) ? $hist : [];

        $last_ts = 0; $prev_val = null;
        if ( ! empty( $hist ) ) {
            ksort( $hist );
            $keys = array_keys( $hist );
            $count= count( $keys );
            $last_ts  = $keys[ $count - 1 ];
            $prev_val = $count > 1 ? (float) $hist[ $keys[ $count - 2 ] ] : null;
        }

        switch ( $column ) {
            case 'currency_id':
                echo esc_html( (string) $post_id );
                break;
            case 'currency_rate':
                echo esc_html( number_format_i18n( $rate, 2 ) );
                if ( $prev_val !== null ) {
                    echo ' ' . Snippets::percentage_change( $prev_val, $rate );
                }
                break;
            case 'currency_attributes':
                $opts = get_option( 'mns_navasan_plus_options', [] );
                $svc  = $opts['api_service'] ?? '';
                if ( $svc ) {
                    printf('%s: %s', esc_html__( 'Auto update', 'mns-navasan-plus' ), esc_html( ucfirst( str_replace( '_', ' ', $svc ) ) ) );
                } else {
                    esc_html_e( 'Manual update', 'mns-navasan-plus' );
                }
                break;
            case 'currency_check_time':
                echo $last_ts ? esc_html( wp_date( 'Y-m-d H:i', (int) $last_ts ) ) : '&mdash;';
                break;
            case 'currency_update_time':
                echo $last_ts ? esc_html( wp_date( 'Y-m-d H:i', (int) $last_ts ) ) : '&mdash;';
                break;
        }
    }

    public static function currency_sortable_columns_query( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }
        // <<< CHANGE: Updated CPT name check from 'mnswmc' to 'mnsnp_currency'
        if ( 'mnsnp_currency' !== $query->get( 'post_type' ) ) {
            return;
        }
        if ( 'currency_rate' === $query->get( 'orderby' ) ) {
            $query->set( 'meta_key', DB::instance()->full_meta_key( 'currency_value' ) );
            $query->set( 'orderby', 'meta_value_num' );
        }
    }

    // ===== Formula list table =====

    public static function formula_columns( $columns ) {
        unset( $columns['date'] );
        $columns['formula_id']         = __( 'ID',          'mns-navasan-plus' );
        $columns['formula_variables']  = __( 'Variables',   'mns-navasan-plus' );
        $columns['formula_components'] = __( 'Components',  'mns-navasan-plus' );
        return $columns;
    }

    public static function formula_columns_content( $column, $post_id ) {
        // This function's logic does not need changes.
        $post = get_post( $post_id );
        if ( ! $post ) {
            echo '&mdash;';
            return;
        }

        $formula_class = '\\MNS\\NavasanPlus\\PublicNS\\Formula';
        $vars_list = $comps_list = [];

        if ( class_exists( $formula_class ) ) {
            $formula = new $formula_class( $post );
            foreach ( $formula->get_variables() as $v ) {
                $vars_list[] = $v->get_name();
            }
            foreach ( $formula->get_components() as $c ) {
                $comps_list[] = $c->get_name();
            }
        }

        switch ( $column ) {
            case 'formula_id':
                echo esc_html( (string) $post_id );
                break;
            case 'formula_variables':
                echo ! empty( $vars_list ) ? esc_html( implode( ', ', $vars_list ) ) : esc_html__( 'No variables', 'mns-navasan-plus' );
                break;
            case 'formula_components':
                echo ! empty( $comps_list ) ? esc_html( implode( ', ', $comps_list ) ) : esc_html__( 'No components', 'mns-navasan-plus' );
                break;
        }
    }

    // ===== Chart list table =====

    public static function chart_columns( $columns ) {
        unset( $columns['date'] );
        $columns['chart_id']        = __( 'ID',        'mns-navasan-plus' );
        $columns['chart_items']     = __( 'Items',     'mns-navasan-plus' );
        $columns['chart_shortcode'] = __( 'Shortcode', 'mns-navasan-plus' );
        return $columns;
    }

    public static function chart_columns_content( $column, $post_id ) {
        switch ( $column ) {
            case 'chart_id':
                echo esc_html( (string) $post_id );
                break;
            case 'chart_items':
                echo '&mdash;';
                break;
            case 'chart_shortcode':
                echo esc_html( '[mns_chart id="' . (int) $post_id . '"]' );
                break;
        }
    }

    public static function row_actions( $actions, $post ) {
        // <<< CHANGE: Updated CPT names in the array check
        if ( ! in_array( $post->post_type, [ 'mnsnp_currency', 'mnsnp_formula' ], true ) ) {
            return $actions;
        }
        unset( $actions['inline hide-if-no-js'] );

        // This dynamic parameter will now correctly use the new CPT names
        $param = 'filter_' . $post->post_type;
        $link  = admin_url( 'edit.php?post_type=product&' . $param . '=' . $post->ID );

        $actions['mnsnp_products'] = sprintf( '<a href="%s">%s</a>', esc_url( $link ), esc_html__( 'Products', 'mns-navasan-plus' ) );
        return $actions;
    }

    public static function filter_products_list( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() || 'product' !== $query->get( 'post_type' ) ) {
            return;
        }
        if ( $query->get( 'mnsnp_filtered' ) ) {
            return;
        }
        $query->set( 'mnsnp_filtered', 1 );

        // <<< CHANGE: Updated the $_GET parameter keys to match the new CPT names
        $currency_id = isset( $_GET['filter_mnsnp_currency'] ) ? absint( $_GET['filter_mnsnp_currency'] ) : 0;
        $formula_id  = isset( $_GET['filter_mnsnp_formula'] ) ? absint( $_GET['filter_mnsnp_formula'] ) : 0;

        if ( ! $currency_id && ! $formula_id ) {
            return;
        }

        $db         = DB::instance();
        $meta_query = (array) $query->get( 'meta_query', [] );

        // This anonymous function does not need changes
        $add_if_missing = static function( $key, $value ) use ( &$meta_query ) {
            foreach ( $meta_query as $cond ) {
                if ( isset( $cond['key'], $cond['value'] ) && $cond['key'] === $key && (string) $cond['value'] === (string) $value ) {
                    return;
                }
            }
            $meta_query[] = [ 'key' => $key, 'value' => $value, 'compare' => '=', 'type' => 'NUMERIC' ];
        };

        if ( $currency_id ) $add_if_missing( $db->full_meta_key( 'currency_id' ), $currency_id );
        if ( $formula_id )  $add_if_missing( $db->full_meta_key( 'formula_id' ), $formula_id );
        
        $query->set( 'meta_query', $meta_query );
    }
}