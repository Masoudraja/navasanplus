<?php
/**
 * Formula Meta‐box template (Navasan Plus)
 *
 * Vars:
 * @var array $formula {
 *   @type string $formul                 (legacy)
 *   @type int    $variables_counter
 *   @type array  $variables  (assoc by variable code)
 * }
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\Templates\Classes\Fields;
use MNS\NavasanPlus\DB;

// Nonce مطابق با formula_save()
wp_nonce_field( 'mns_navasan_plus_formula', '_mns_navasan_plus_formula_nonce' );

// اطمینان از حضور پارسر (برای محاسبهٔ لحظه‌ای)
if ( wp_script_is( 'mns-navasan-plus-formula-parser', 'registered' ) ) {
    wp_enqueue_script( 'mns-navasan-plus-formula-parser' );
}

// ------- currencies (for "currency" type variables) -------
$currency_posts = get_posts([
    'post_type'        => 'mnswmc',
    'posts_per_page'   => -1,
    'post_status'      => 'publish',
    'orderby'          => 'title',
    'order'            => 'ASC',
    'fields'           => 'ids',
    'suppress_filters' => true,
]);

$currencies = [];
foreach ( $currency_posts as $cid ) {
    $cid   = (int) $cid;
    $title = get_the_title( $cid );
    $rate  = (float) DB::instance()->read_post_meta( $cid, 'currency_value', 0 );
    $sym = (string) DB::instance()->read_post_meta( $cid, 'currency_rate_symbol', '' );
    if ( $sym === '' ) {
        $sym = (string) DB::instance()->read_post_meta( $cid, 'currency_symbol', '' );
    }
    $currencies[] = [
        'id'     => $cid,
        'label'  => $title,
        'rate'   => $rate,
        'symbol' => $sym,
    ];
}

// ------- base data -------
$formula           = is_array( $formula ?? null ) ? $formula : [];
$codes             = array_keys( (array) ( $formula['variables'] ?? [] ) );
$existing_count    = count( $codes );
$stored_counter    = isset( $formula['variables_counter'] ) ? (int) $formula['variables_counter'] : 0;
$next_index        = max( $existing_count, $stored_counter ); // برای ساخت آیتم جدید
$render_count      = max( 1, $existing_count );               // حداقل یک ردیف برای شروع

// مقدار اولیهٔ عبارت: اگر متای جدید ست است، همان؛ وگرنه legacy $formula['formul']
$initial_expression = '';
// اگر از قبل توسط متاباکس/سیور جدید ذخیره شده باشد:
if ( isset( $formula['expression'] ) && $formula['expression'] !== '' ) {
    $initial_expression = (string) $formula['expression'];
} elseif ( isset( $formula['formul'] ) ) {
    // سازگاری با قدیمی
    $initial_expression = (string) $formula['formul'];
}
?>
<!-- Formula Expression -->
<p>
  <label for="mns_navasan_plus_formula_expression">
    <?php _e( 'Formula Expression', 'mns-navasan-plus' ); ?>
  </label>
  <textarea
    id="mns_navasan_plus_formula_expression"
    name="_mns_navasan_plus_formula_expression"
    rows="4"
    class="widefat"
  ><?php echo esc_textarea( $initial_expression ); ?></textarea>
  <small class="description">
    <?php _e( 'Use variable codes (each code equals unit×value).', 'mns-navasan-plus' ); ?>
  </small>
</p>

<!-- Live total (Expression) -->
<p class="mns-formula-live-row" style="margin-top:6px;">
  <strong><?php esc_html_e( 'Expression total', 'mns-navasan-plus' ); ?>:</strong>
  <span class="mns-formula-total" style="margin-inline-start:6px;">—</span>
  <em class="mns-formula-error" style="color:#d63638; display:none; margin-inline-start:10px;"></em>
</p>

<!-- Variables -->
<h4><?php _e( 'Formula Variables', 'mns-navasan-plus' ); ?></h4>

<input type="hidden"
       name="_mns_navasan_plus_formula_variables_counter"
       value="<?php echo (int) $next_index; ?>" />

<div id="mns-navasan-plus-formula-variables-container"
     data-currencies='<?php echo esc_attr( wp_json_encode( $currencies, JSON_UNESCAPED_UNICODE ) ); ?>'>
  <?php
  for ( $i = 0; $i < $render_count; $i++ ) :
      $code = isset( $codes[$i] ) ? (string) $codes[$i] : 'var_' . $i;

      $var  = $formula['variables'][ $code ] ?? [
          'name'         => '',
          'unit'         => null,   // null → تشخیص مقداردهی‌نشده
          'unit_symbol'  => null,
          'value'        => null,
          'value_symbol' => '',
          'type'         => '',
          'currency_id'  => 0,
      ];

      // نوع: اگر currency_id داشت → currency، در غیر اینصورت custom
      $type        = $var['type'] ?: ( ! empty( $var['currency_id'] ) ? 'currency' : 'custom' );
      $currency_id = (int) ( $var['currency_id'] ?? 0 );

      // پیدا کردن ارز انتخاب‌شده (اگر هست)
      $currSel = null;
      if ( $currency_id > 0 ) {
          foreach ( $currencies as $c ) {
              if ( (int) $c['id'] === $currency_id ) { $currSel = $c; break; }
          }
      }

      // مقدارهای پیش‌فرضِ هوشمند:
      $unit_val = array_key_exists( 'unit', $var )
          ? (string) $var['unit']
          : ( $type === 'currency' && $currSel ? (string) $currSel['rate'] : '' );

      $unit_sym = array_key_exists( 'unit_symbol', $var )
          ? (string) $var['unit_symbol']
          : ( $type === 'currency' && $currSel ? (string) $currSel['symbol'] : '' );

      // value: برای currency اگر ذخیره نشده → 1
      $value_val = array_key_exists( 'value', $var )
          ? (string) $var['value']
          : ( $type === 'currency' ? '1' : '' );

      // در حالت currency → readonly روی unit و unit_symbol
      $readonly_attrs = ( $type === 'currency' ) ? [ 'readonly' => 'readonly' ] : [];
  ?>
  <div class="mns-formula-variable" data-code="<?php echo esc_attr( $code ); ?>">

    <?php
    // نوع متغیر (id اول، name دوم)
    Fields::select(
        "mns_navasan_plus_formula_variables_{$code}_type",
        "_mns_navasan_plus_formula_variables[{$code}][type]",
        [
            'custom'   => __( 'Custom variable',   'mns-navasan-plus' ),
            'currency' => __( 'Currency variable', 'mns-navasan-plus' ),
        ],
        $type,
        __( 'Variable Type', 'mns-navasan-plus' ),
        [ 'class' => 'mns-var-type' ]
    );
    ?>

    <!-- currency-only controls -->
    <div class="mns-if-currency" style="<?php echo $type === 'currency' ? '' : 'display:none;'; ?>">
      <p class="form-field">
        <label for="<?php echo esc_attr( "mns_navasan_plus_formula_variables_{$code}_currency_id" ); ?>">
          <?php _e( 'Select Currency', 'mns-navasan-plus' ); ?>
        </label>
        <select
          id="<?php echo esc_attr( "mns_navasan_plus_formula_variables_{$code}_currency_id" ); ?>"
          name="<?php echo esc_attr( "_mns_navasan_plus_formula_variables[{$code}][currency_id]" ); ?>"
          class="mns-currency-select"
        >
          <option value="0"><?php _e( '— select —', 'mns-navasan-plus' ); ?></option>
          <?php foreach ( $currencies as $c ): ?>
            <option
              value="<?php echo esc_attr( $c['id'] ); ?>"
              data-rate="<?php echo esc_attr( $c['rate'] ); ?>"
              data-symbol="<?php echo esc_attr( $c['symbol'] ); ?>"
              <?php selected( $currency_id, $c['id'] ); ?>
            >
              <?php echo esc_html( $c['label'] ); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="description" style="margin-inline-start:8px;">
          <?php _e( 'Current rate:', 'mns-navasan-plus' ); ?>
          <code class="mns-curr-rate">
            <?php
            if ( $currSel ) {
                $t = number_format_i18n( (float) $currSel['rate'], 4 );
                if ( ! empty( $currSel['symbol'] ) ) { $t .= ' ' . $currSel['symbol']; }
                echo esc_html( $t );
            } else {
                echo '—';
            }
            ?>
          </code>
        </span>
      </p>
    </div>

    <?php
    // نام متغیر
    Fields::text(
        "mns_navasan_plus_formula_variables_{$code}_name",
        "_mns_navasan_plus_formula_variables[{$code}][name]",
        (string) ( $var['name'] ?? '' ),
        __( 'Variable Name', 'mns-navasan-plus' )
    );

    // مقدار واحد (برای currency readonly و توسط JS نیز به‌روزرسانی می‌شود)
    Fields::number(
        "mns_navasan_plus_formula_variables_{$code}_unit",
        "_mns_navasan_plus_formula_variables[{$code}][unit]",
        $unit_val,
        __( 'Unit Value', 'mns-navasan-plus' ),
        array_merge( [ 'step' => '0.0001', 'class' => 'mns-unit' ], $readonly_attrs )
    );

    // نماد واحد (برای currency readonly)
    Fields::text(
        "mns_navasan_plus_formula_variables_{$code}_unit_symbol",
        "_mns_navasan_plus_formula_variables[{$code}][unit_symbol]",
        $unit_sym,
        __( 'Unit Symbol', 'mns-navasan-plus' ),
        array_merge( [ 'class' => 'mns-unit-symbol' ], $readonly_attrs )
    );

    // مقدار (برای currency آزاد؛ پیش‌فرض 1 اگر ذخیره نشده بود)
    Fields::number(
        "mns_navasan_plus_formula_variables_{$code}_value",
        "_mns_navasan_plus_formula_variables[{$code}][value]",
        $value_val,
        __( 'Value', 'mns-navasan-plus' ),
        [ 'step' => '0.0001', 'class' => 'mns-value' ]
    );

    // نماد مقدار
    Fields::text(
        "mns_navasan_plus_formula_variables_{$code}_value_symbol",
        "_mns_navasan_plus_formula_variables[{$code}][value_symbol]",
        (string) ( $var['value_symbol'] ?? '' ),
        __( 'Value Symbol', 'mns-navasan-plus' ),
        [ 'class' => 'mns-value-symbol' ]
    );
    ?>

    <p>
      <button type="button" class="button remove-formula-variable">
        <?php _e( 'Remove Variable', 'mns-navasan-plus' ); ?>
      </button>

      <span class="description" style="margin-inline-start:8px;">
        <?php
        printf(
          /* translators: %s: variable code */
          esc_html__( 'Code: %s (use this in expression)', 'mns-navasan-plus' ),
          '<code class="mns-var-code">' . esc_html( $code ) . '</code>'
        );
        ?>
      </span>

      <!-- Copy & Insert buttons -->
      <button type="button"
              class="button button-small mns-copy-code"
              data-code="<?php echo esc_attr( $code ); ?>"
              style="margin-inline-start:8px;">
        <?php esc_html_e( 'Copy', 'mns-navasan-plus' ); ?>
      </button>

      <button type="button"
              class="button button-small mns-insert-code"
              data-code="<?php echo esc_attr( $code ); ?>"
              data-target="#mns_navasan_plus_formula_expression">
        <?php esc_html_e( 'Insert into expression', 'mns-navasan-plus' ); ?>
      </button>
    </p>

    <hr/>
  </div>
  <?php endfor; ?>
</div>

<p>
  <button type="button" class="button add-formula-variable" data-kind="custom">
    <?php _e( 'Add Custom Variable', 'mns-navasan-plus' ); ?>
  </button>
  <button type="button" class="button add-formula-variable" data-kind="currency">
    <?php _e( 'Add Currency Variable', 'mns-navasan-plus' ); ?>
  </button>
</p>