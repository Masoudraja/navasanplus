<?php
/**
 * Currency Meta-box template for MNS Navasan Plus
 *
 * Available variable:
 * @var array $currency {
 *   @type string $rate_symbol
 *   @type float  $value
 *   @type string $calculation_type
 *   @type string $formula_text
 *   @type float  $profit
 *   @type float  $fee
 *   @type float  $ratio
 *   @type float  $fixed
 *   @type string $update_type
 *   @type string $relation
 *   @type string $connection
 *   @type string $operation
 *   @type int    $calculation_order
 *   @type float  $lowest_rate
 * }
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use MNS\NavasanPlus\Templates\Classes\Fields;
use MNS\NavasanPlus\Helpers;

// Nonce for security
wp_nonce_field( 'mns_navasan_plus_currency', '_mns_navasan_plus_currency_nonce' );

// Rate Symbol
Fields::text(
    'mns_navasan_plus_currency_rate_symbol',
    'mns_navasan_plus_currency_rate_symbol',
    $currency['rate_symbol'],
    __( 'Rate Symbol', 'mns-navasan-plus' )
);

// Value
Fields::number(
    'mns_navasan_plus_currency_value',
    'mns_navasan_plus_currency_value',
    $currency['value'],
    __( 'Value', 'mns-navasan-plus' )
);

// Calculation Type
$calculation_types = [
    'manual'  => __( 'Manual', 'mns-navasan-plus' ),
    'formula' => __( 'Formula', 'mns-navasan-plus' ),
];
Fields::select(
    'mns_navasan_plus_currency_calculation_type',
    'mns_navasan_plus_currency_calculation_type',
    $calculation_types,
    $currency['calculation_type'],
    __( 'Calculation Type', 'mns-navasan-plus' )
);

// Formula Text
Fields::textarea(
    'mns_navasan_plus_currency_formula_text',
    'mns_navasan_plus_currency_formula_text',
    $currency['formula_text'],
    __( 'Formula Text', 'mns-navasan-plus' ),
    [ 'rows' => 4 ]
);

// Profit (%)
Fields::number(
    'mns_navasan_plus_currency_profit',
    'mns_navasan_plus_currency_profit',
    $currency['profit'],
    __( 'Profit (%)', 'mns-navasan-plus' )
);

// Fee (%)
Fields::number(
    'mns_navasan_plus_currency_fee',
    'mns_navasan_plus_currency_fee',
    $currency['fee'],
    __( 'Fee (%)', 'mns-navasan-plus' )
);

// Ratio
Fields::number(
    'mns_navasan_plus_currency_ratio',
    'mns_navasan_plus_currency_ratio',
    $currency['ratio'],
    __( 'Ratio', 'mns-navasan-plus' )
);

// Fixed Amount
Fields::number(
    'mns_navasan_plus_currency_fixed',
    'mns_navasan_plus_currency_fixed',
    $currency['fixed'],
    __( 'Fixed Amount', 'mns-navasan-plus' )
);

// Update Type
$update_types = [
    'none'   => __( 'None', 'mns-navasan-plus' ),
    'manual' => __( 'Manual', 'mns-navasan-plus' ),
    'auto'   => __( 'Auto', 'mns-navasan-plus' ),
];
Fields::select(
    'mns_navasan_plus_currency_update_type',
    'mns_navasan_plus_currency_update_type',
    $update_types,
    $currency['update_type'],
    __( 'Update Type', 'mns-navasan-plus' )
);

// Relation (link to another currency)
$all_currencies = get_posts([
    'post_type'      => 'mnsnp_currency',
    'posts_per_page' => -1,
    'post__not_in'   => [ get_the_ID() ],
]);
$relation_opts = [ '' => __( '-- None --', 'mns-navasan-plus' ) ];
foreach ( $all_currencies as $cur ) {
    $relation_opts[ $cur->ID ] = $cur->post_title;
}
Fields::select(
    'mns_navasan_plus_currency_relation',
    'mns_navasan_plus_currency_relation',
    $relation_opts,
    $currency['relation'],
    __( 'Relation Currency', 'mns-navasan-plus' )
);

// Connection (base currency for formula)
$connection_opts = $relation_opts;
Fields::select(
    'mns_navasan_plus_currency_connection',
    'mns_navasan_plus_currency_connection',
    $connection_opts,
    $currency['connection'],
    __( 'Connection Currency', 'mns-navasan-plus' )
);

// Operation (+ / -)
$operation_opts = [
    '+' => __( 'Add (+)', 'mns-navasan-plus' ),
    '-' => __( 'Subtract (-)', 'mns-navasan-plus' ),
];
Fields::select(
    'mns_navasan_plus_currency_operation',
    'mns_navasan_plus_currency_operation',
    $operation_opts,
    $currency['operation'],
    __( 'Operation', 'mns-navasan-plus' )
);

// Calculation Order
Fields::number(
    'mns_navasan_plus_currency_calculation_order',
    'mns_navasan_plus_currency_calculation_order',
    $currency['calculation_order'],
    __( 'Calculation Order', 'mns-navasan-plus' )
);

// Lowest Rate
Fields::number(
    'mns_navasan_plus_currency_lowest_rate',
    'mns_navasan_plus_currency_lowest_rate',
    $currency['lowest_rate'],
    __( 'Lowest Rate', 'mns-navasan-plus' )
);