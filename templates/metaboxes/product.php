<?php
/**
 * Product Meta‐box template for MNS Navasan Plus
 *
 * Renders meta fields for rate‐based pricing on both
 * simple and variable products (admin only).
 *
 * Vars:
 * @var \WC_Product $product
 * @var int|null    $loop   (variation index; null for simple)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\Templates\Classes\Fields;
use MNS\NavasanPlus\Templates\Classes\Snippets;
use MNS\NavasanPlus\DB;

// ---------- Context ----------
$is_variation  = isset( $loop );
$product_type  = $is_variation ? 'variation' : 'simple';

$section_class = $is_variation
    ? 'mns-navasan-plus_variation_product_fields'
    // عمداً hidden/کلاس‌های مخفی ووکامرس حذف شده تا همیشه دیده شود
    : 'options_group show_if_simple mns-navasan-plus_simple_product_fields';

$data_loop     = $is_variation ? ' data-loop="' . esc_attr( (int) $loop ) . '"' : '';

$name_prefix   = $is_variation ? '_variable' : '';
$name_suffix   = $is_variation ? '[' . (int) $loop . ']' : ''; // برای محصول ساده خالی بماند

$id_suffix     = $is_variation ? '_' . (int) $loop : '';
$field_class   = $is_variation ? 'mns-navasan-plus_variation_field' : 'mns-navasan-plus_simple_field';
$wrapper_class = $is_variation ? 'form-row' : '';

// ---------- Global rounding (fallback) ----------
$global_rounding = [
    'type'  => get_option( 'mns_navasan_plus_rounding_type',  'none' ),
    'value' => get_option( 'mns_navasan_plus_rounding_value', '' ),
    'side'  => get_option( 'mns_navasan_plus_rounding_side',  'close' ),
];

// ---------- Per-product meta with safe fallbacks ----------
$active_meta = $product->get_meta( '_mns_navasan_plus_active', true );
$is_active   = ( $active_meta === '' ) ? true : (bool) $active_meta;

$alert_meta  = $product->get_meta( '_mns_navasan_plus_price_alert', true );
$price_alert = ( $alert_meta === '' ) ? false : (bool) $alert_meta;

$dep_meta = $product->get_meta( '_mns_navasan_plus_dependence_type', true );
$dep_type = ( $dep_meta === '' ) ? 'simple' : (string) $dep_meta;

$tmp = $product->get_meta( '_mns_navasan_plus_rounding_type', true );
$round_type  = ( $tmp === '' ) ? $global_rounding['type'] : (string) $tmp;

$tmp = $product->get_meta( '_mns_navasan_plus_rounding_value', true );
$round_value = ( $tmp === '' ) ? $global_rounding['value'] : $tmp;

$tmp = $product->get_meta( '_mns_navasan_plus_rounding_side', true );
$round_side  = ( $tmp === '' ) ? $global_rounding['side'] : (string) $tmp;

$tmp = $product->get_meta( '_mns_navasan_plus_currency_id', true );
$currency_id = ( $tmp === '' ) ? 0 : (int) $tmp;

$tmp = $product->get_meta( '_mns_navasan_plus_profit_type', true );
$profit_type = ( $tmp === '' ) ? 'percent' : (string) $tmp;

$tmp = $product->get_meta( '_mns_navasan_plus_profit_value', true );
$profit_value = ( $tmp === '' ) ? 0 : $tmp;

$ceil = $product->get_meta( '_mns_navasan_plus_ceil_price', true );
$floor = $product->get_meta( '_mns_navasan_plus_floor_price', true );

// ---------- Currency options (CPT: mnswmc) ----------
$currency_posts = get_posts([
    'post_type'        => 'mnsnp_currency',
    'posts_per_page'   => -1,
    'post_status'      => 'publish',
    'orderby'          => 'title',
    'order'            => 'ASC',
    'fields'           => 'ids',
    'suppress_filters' => true,
]);
$currency_options = [ 0 => __( '— select —', 'mns-navasan-plus' ) ];
foreach ( $currency_posts as $cid ) {
    $currency_options[ $cid ] = get_the_title( $cid );
}

// ---------- Formula options + per-product values ----------
$formula_posts = get_posts([
    'post_type'        => 'mnsnp_formula',
    'posts_per_page'   => -1,
    'post_status'      => 'publish',
    'orderby'          => 'title',
    'order'            => 'ASC',
    'suppress_filters' => true,
]);

// مقدار ذخیره‌شدهٔ متغیرهای فرمولِ همین محصول/ورییشن (آرایهٔ تجمیع‌شده)
$stored_formula_vars = $product->get_meta( '_mns_navasan_plus_formula_variables', true );
$stored_formula_vars = is_array( $stored_formula_vars ) ? $stored_formula_vars : [];

$formula_data = [];
$product_data = [];

foreach ( $formula_posts as $fp ) {
    $fid  = is_object( $fp ) ? (int) $fp->ID : (int) $fp;  // ✅
    $post = get_post( $fid );
    if ( ! $post ) continue;

    // آرایهٔ متغیرهای فرمول از متای فرمول
    $vars_meta = (array) get_post_meta(
        $fid,
        DB::instance()->full_meta_key( 'formula_variables' ),
        true
    );

    $vars_for_ui = [];
    foreach ( $vars_meta as $code => $row ) {
        $vars_for_ui[ $code ] = [
            'code'        => (string) $code,
            'name'        => (string) ( $row['name'] ?? $code ),
            'unit_symbol' => (string) ( $row['unit_symbol'] ?? '' ),
        ];
    }

    $formula_data[ $fid ] = [
        'label'     => get_the_title( $fid ),
        'variables' => $vars_for_ui,
    ];

    // از آرایهٔ ذخیره‌شده در خود محصول بخوان (فقط 'regular' برای هر code)
    $product_data[ $fid ] = [];
    foreach ( $vars_for_ui as $code => $_ ) {
        $regular = '';
        if ( ! empty( $stored_formula_vars[ $fid ][ $code ] ) ) {
            // سازگاری با داده‌های قدیمی: اگر آرایه است، از کلید 'regular' بخوان
            if ( is_array( $stored_formula_vars[ $fid ][ $code ] ) ) {
                $regular = $stored_formula_vars[ $fid ][ $code ]['regular'] ?? '';
            } else {
                // یا اگر به‌صورت scalar ذخیره شده بود
                $regular = $stored_formula_vars[ $fid ][ $code ];
            }
        }
        $product_data[ $fid ][ $code ] = [
            'regular' => $regular,
        ];
    }
}
?>
<div class="<?php echo esc_attr( $section_class ); ?>"<?php echo $data_loop; ?>>

  <?php
  // 1) فعال‌سازی قیمت‌گذاری بر پایه نرخ
  Fields::checkbox(
  'mns_navasan_plus_active' . $id_suffix,
  $name_prefix . '_mns_navasan_plus_active' . $name_suffix,
  $is_active,
  __( 'Rate Based', 'mns-navasan-plus' ),
  __( 'Enable rate‐based pricing for this product.', 'mns-navasan-plus' ),
  [
    'wrapper_class' => $wrapper_class . ' form-row-full mnsnp-checkbox',
    'class'         => $field_class,
  ]
);

  // 3) نوع وابستگی (Simple / Advanced)
  Fields::select(
      'mns_navasan_plus_dependence_type' . $id_suffix,
      $name_prefix . '_mns_navasan_plus_dependence_type' . $name_suffix,
      [ 'simple' => __( 'Simple', 'mns-navasan-plus' ), 'advanced' => __( 'Advanced (Formula)', 'mns-navasan-plus' ) ],
      $dep_type,
      __( 'Dependency Type', 'mns-navasan-plus' ),
      [ 'wrapper_class' => $wrapper_class . ' form-row-first show_if_mns_navasan_plus_active', 'class' => $field_class, 'desc_tip' => true ],
      __( 'Choose how to calculate this product’s price.', 'mns-navasan-plus' )
  );

  // 4) بلوک فرمول (زیرتمپلیت product-formula)
  Snippets::load_template( 'metaboxes/product-formula', [
      'post_id'      => $product->get_id(),
      'name_prefix'  => $name_prefix,   // '' برای ساده، '_variable' برای ورییشن
      'name_suffix'  => $name_suffix,   // '' برای ساده، '[i]' برای ورییشن
      'formula_data' => $formula_data,  // لیست فرمول‌ها + متغیرها
      'product_data' => $product_data,  // فقط مقدارهای regular ذخیره‌شده
  ] );

  // 5) انتخاب ارز (برای حالت simple)
  Fields::select(
      'mns_navasan_plus_currency_id' . $id_suffix,
      $name_prefix . '_mns_navasan_plus_currency_id' . $name_suffix,
      $currency_options,
      $currency_id,
      __( 'Currency', 'mns-navasan-plus' ),
      [ 'wrapper_class' => $wrapper_class . ' form-row-first show_if_mns_navasan_plus_dependence_type_simple show_if_mns_navasan_plus_active', 'class' => $field_class ]
  );

  // 6) Profit settings (برای حالت simple)
  Fields::select(
      'mns_navasan_plus_profit_type' . $id_suffix,
      $name_prefix . '_mns_navasan_plus_profit_type' . $name_suffix,
      [ 'percent' => __( 'Percent', 'mns-navasan-plus' ), 'fixed' => __( 'Fixed', 'mns-navasan-plus' ) ],
      $profit_type,
      __( 'Profit Type', 'mns-navasan-plus' ),
      [ 'wrapper_class' => $wrapper_class . ' show_if_mns_navasan_plus_currency_id show_if_mns_navasan_plus_dependence_type_simple show_if_mns_navasan_plus_active', 'class' => $field_class ]
  );
  Fields::number(
      'mns_navasan_plus_profit_value' . $id_suffix,
      $name_prefix . '_mns_navasan_plus_profit_value' . $name_suffix,
      $profit_value,
      __( 'Profit Value', 'mns-navasan-plus' ),
      [ 'wrapper_class' => $wrapper_class . ' show_if_mns_navasan_plus_currency_id show_if_mns_navasan_plus_dependence_type_simple show_if_mns_navasan_plus_active', 'class' => $field_class ]
  );

  // 7) Rounding (fallback سراسری + per-product)
  Fields::select(
      'mns_navasan_plus_rounding_type' . $id_suffix,
      $name_prefix . '_mns_navasan_plus_rounding_type' . $name_suffix,
      [ 'none' => __( 'None', 'mns-navasan-plus' ), 'zero' => __( 'Zero', 'mns-navasan-plus' ), 'integer' => __( 'Integer', 'mns-navasan-plus' ) ],
      $round_type,
      __( 'Rounding Type', 'mns-navasan-plus' ),
      [ 'wrapper_class' => $wrapper_class . ' form-row-full show_if_mns_navasan_plus_currency_id show_if_mns_navasan_plus_dependence_type_simple show_if_mns_navasan_plus_active', 'class' => $field_class ]
  );
  Fields::number(
      'mns_navasan_plus_rounding_value' . $id_suffix,
      $name_prefix . '_mns_navasan_plus_rounding_value' . $name_suffix,
      $round_value,
      __( 'Rounding Value', 'mns-navasan-plus' ),
      [ 'wrapper_class' => $wrapper_class . ' show_if_mns_navasan_plus_currency_id show_if_mns_navasan_plus_dependence_type_simple show_if_mns_navasan_plus_active', 'class' => $field_class ]
  );
  Fields::select(
      'mns_navasan_plus_rounding_side' . $id_suffix,
      $name_prefix . '_mns_navasan_plus_rounding_side' . $name_suffix,
      [ 'close' => __( 'Nearest', 'mns-navasan-plus' ), 'up' => __( 'Up', 'mns-navasan-plus' ), 'down'  => __( 'Down', 'mns-navasan-plus' ) ],
      $round_side,
      __( 'Rounding Direction', 'mns-navasan-plus' ),
      [ 'wrapper_class' => $wrapper_class . ' show_if_mns_navasan_plus_currency_id show_if_mns_navasan_plus_dependence_type_simple show_if_mns_navasan_plus_active', 'class' => $field_class ]
  );

  // 8) سقف و کف (فقط در حالت simple)
  Fields::number(
      'mns_navasan_plus_ceil_price' . $id_suffix,
      $name_prefix . '_mns_navasan_plus_ceil_price' . $name_suffix,
      ( $ceil === '' ? '' : $ceil ),
      __( 'Price Ceiling', 'mns-navasan-plus' ),
      [ 'wrapper_class' => $wrapper_class . ' form-row-first show_if_mns_navasan_plus_currency_id show_if_mns_navasan_plus_dependence_type_simple show_if_mns_navasan_plus_active', 'class' => $field_class ]
  );
  Fields::number(
      'mns_navasan_plus_floor_price' . $id_suffix,
      $name_prefix . '_mns_navasan_plus_floor_price' . $name_suffix,
      ( $floor === '' ? '' : $floor ),
      __( 'Price Floor', 'mns-navasan-plus' ),
      [ 'wrapper_class' => $wrapper_class . ' form-row-last show_if_mns_navasan_plus_currency_id show_if_mns_navasan_plus_dependence_type_simple show_if_mns_navasan_plus_active', 'class' => $field_class ]
  );
  ?>
</div>

<?php
// ... همان کد قبلی تا قبل از بخش پیش‌نمایش ...

// -------------------------------------------------------------------------
// پیش‌نمایش «اجزای فرمول» و جمع نهایی
// -------------------------------------------------------------------------
$dep_meta    = (string) $product->get_meta( '_mns_navasan_plus_dependence_type', 'simple' );
$use_formula = in_array( strtolower( $dep_meta ), [ 'advanced', 'formula' ], true );

if ( is_admin() && current_user_can('manage_woocommerce')
     && (bool)$product->get_meta('_mns_navasan_plus_active', true)
     && $use_formula
     && apply_filters('mnsnp/show_admin_formula_breakdown', true) ) {

    $wrapper = new \MNS\NavasanPlus\PublicNS\Product( $product );

    echo '<div class="mnsnp-admin-preview-wrap options_group">';
    echo '<hr class="mnsnp-sep" style="margin:12px 0" />';
    echo '<h4 class="mnsnp-preview-title" style="margin:6px 0;">' . esc_html__( 'Price Breakdown (preview)', 'mns-navasan-plus' ) . '</h4>';

    \MNS\NavasanPlus\Templates\Classes\Snippets::load_template(
        'product-formula-components-advanced',
        [ 'product' => $wrapper, 'value' => 1 ]
    );

    echo '<p class="mnsnp-preview-total" style="margin:8px 0;"><strong>' . esc_html__( 'Final price (preview):', 'mns-navasan-plus' ) . '</strong> ';
    $dec_filter = function(){ return 0; }; add_filter('wc_get_price_decimals',$dec_filter,1000);
    if ( class_exists('\MNS\NavasanPlus\Services\PriceCalculator') ) {
        try {
            $res = \MNS\NavasanPlus\Services\PriceCalculator::instance()->calculate( (int) $product->get_id() );
            $final = is_array($res) ? (float)($res['price'] ?? 0) : (float)$res;
            echo wp_kses_post( wc_price( max(0,(float)$final) ) );
        } catch ( \Throwable $e ) { echo esc_html__( '— error —', 'mns-navasan-plus' ); }
    } else {
        echo esc_html__( '— n/a —', 'mns-navasan-plus' );
    }
    remove_filter('wc_get_price_decimals',$dec_filter,1000);
    echo '</p>';

    echo '</div>';
}