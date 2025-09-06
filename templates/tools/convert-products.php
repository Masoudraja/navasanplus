<?php
/**
 * Convert Products Tool Template for MNS Navasan Plus
 *
 * Provides an admin‐side interface to bulk‐convert existing WooCommerce products
 * from static pricing to rate‐based pricing, assigning them a base currency.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use MNS\NavasanPlus\DB;
use MNS\NavasanPlus\Helpers;

echo '<div class="wrap">';
echo '<h1>' . esc_html__( 'Convert Products to Rate-Based Pricing', 'mns-navasan-plus' ) . '</h1>';

// Handle form submission
if ( isset( $_POST['mns_navasan_plus_convert_nonce'] ) 
     && wp_verify_nonce( $_POST['mns_navasan_plus_convert_nonce'], 'mns_navasan_plus_convert' ) ) {

    $cat_slug    = sanitize_text_field( $_POST['mns_navasan_plus_cat'] ?? '' );
    $currency_id = intval( $_POST['mns_navasan_plus_currency'] ?? 0 );
    $updated     = 0;

    // Build query args
    $query_args = [
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
    ];
    if ( $cat_slug !== '' ) {
        $query_args['tax_query'] = [[
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $cat_slug,
        ]];
    }

    $products = get_posts( $query_args );
    foreach ( $products as $product ) {
        $pid = $product->ID;

        // Activate rate-based pricing
        DB::instance()->update_post_meta( $pid, 'active', 1 );

        // Assign the selected currency
        if ( $currency_id > 0 ) {
            DB::instance()->update_post_meta( $pid, 'currency_id', $currency_id );
        }

        $updated++;
    }

    printf(
        '<div class="updated notice"><p>%s</p></div>',
        esc_html( sprintf(
            _n( 'Converted %d product.', 'Converted %d products.', $updated, 'mns-navasan-plus' ),
            $updated
        ) )
    );
}

// Fetch all product categories
$categories = get_terms( [
    'taxonomy'   => 'product_cat',
    'hide_empty' => false,
] );

// Fetch all currencies (Currency CPT)
$currencies = get_posts( [
    'post_type'      => 'mnsnp_currency',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
] );
?>

<form method="post">
    <?php wp_nonce_field( 'mns_navasan_plus_convert', 'mns_navasan_plus_convert_nonce' ); ?>
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="mns_navasan_plus_cat">
                    <?php esc_html_e( 'Filter by Category', 'mns-navasan-plus' ); ?>
                </label>
            </th>
            <td>
                <select name="mns_navasan_plus_cat" id="mns_navasan_plus_cat">
                    <option value=""><?php esc_html_e( '-- All Categories --', 'mns-navasan-plus' ); ?></option>
                    <?php foreach ( $categories as $cat ) : ?>
                        <option value="<?php echo esc_attr( $cat->slug ); ?>">
                            <?php echo esc_html( $cat->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">
                    <?php esc_html_e( 'Only products in this category will be converted.', 'mns-navasan-plus' ); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="mns_navasan_plus_currency">
                    <?php esc_html_e( 'Assign Currency', 'mns-navasan-plus' ); ?>
                </label>
            </th>
            <td>
                <select name="mns_navasan_plus_currency" id="mns_navasan_plus_currency">
                    <option value="0"><?php esc_html_e( '-- Select Currency --', 'mns-navasan-plus' ); ?></option>
                    <?php foreach ( $currencies as $cur ) : ?>
                        <option value="<?php echo esc_attr( $cur->ID ); ?>">
                            <?php echo esc_html( $cur->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">
                    <?php esc_html_e( 'Choose the base currency for all converted products.', 'mns-navasan-plus' ); ?>
                </p>
            </td>
        </tr>
    </table>

    <?php submit_button( esc_html__( 'Convert Products', 'mns-navasan-plus' ) ); ?>
</form>
</div>