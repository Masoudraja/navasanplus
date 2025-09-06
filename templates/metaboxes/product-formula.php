<?php
/**
 * Product → Formula picker + per-product variable overrides
 *
 * Expects:
 * @var int    $post_id
 * @var string $name_prefix
 * @var string $name_suffix
 * @var array  $formula_data [fid => ['label'=>..., 'variables'=> [code => ['code','name','unit_symbol']]]]
 * @var array  $product_data [fid => [code => ['regular' => '...']]]
 */
if ( ! defined('ABSPATH') ) exit;

use MNS\NavasanPlus\DB;

$post_id      = isset($post_id) ? (int) $post_id : 0;
$formula_data = is_array($formula_data ?? null) ? $formula_data : [];
$product_data = is_array($product_data ?? null) ? $product_data : [];

// فرمول انتخابی فعلی
$current_fid = (int) get_post_meta( $post_id, DB::instance()->full_meta_key('formula_id'), true );

// سلکت فرمول
?>
<p class="form-field">
  <label for="mns_navasan_plus_formula_id"><?php esc_html_e('Formula', 'mns-navasan-plus'); ?></label>
  <select id="mns_navasan_plus_formula_id"
          name="<?php echo esc_attr($name_prefix . '_mns_navasan_plus_formula_id' . $name_suffix); ?>"
          class="mnsnp-w100">
    <option value="0">— <?php esc_html_e('select', 'mns-navasan-plus'); ?> —</option>
    <?php foreach ($formula_data as $fid => $row): ?>
      <option value="<?php echo (int) $fid; ?>" <?php selected($current_fid, $fid); ?>>
        <?php echo esc_html($row['label'] ?? ('#'.$fid)); ?>
      </option>
    <?php endforeach; ?>
  </select>
  <span class="description"><?php esc_html_e('Pick the formula to use for this product.', 'mns-navasan-plus'); ?></span>
</p>

<?php
// پنل متغیرها برای هر فرمول (فقط یکی نمایش داده می‌شود)
foreach ($formula_data as $fid => $row):
  $vars = (array) ($row['variables'] ?? []);
  $vals = (array) ($product_data[$fid] ?? []);
  $open = ($fid == $current_fid);
?>
<div class="mnsnp-formula-vars-panel" data-fid="<?php echo (int) $fid; ?>" style="<?php echo $open ? '' : 'display:none;'; ?>">
  <h4 style="margin:10px 0;"><?php echo esc_html($row['label'] ?? ('#'.$fid)); ?> — <?php esc_html_e('Variables (per product)', 'mns-navasan-plus'); ?></h4>

  <?php if (empty($vars)) : ?>
    <p class="description"><?php esc_html_e('This formula has no variables.', 'mns-navasan-plus'); ?></p>
  <?php else: ?>
    <table class="widefat striped" style="max-width:680px;">
      <thead>
        <tr>
          <th style="width:30%;"><?php esc_html_e('Variable', 'mns-navasan-plus'); ?></th>
          <th style="width:20%;"><?php esc_html_e('Code', 'mns-navasan-plus'); ?></th>
          <th style="width:30%;"><?php esc_html_e('Value for this product', 'mns-navasan-plus'); ?></th>
          <th style="width:20%;"><?php esc_html_e('Unit', 'mns-navasan-plus'); ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($vars as $code => $meta):
          $code   = (string) $code;
          $name   = (string) ($meta['name'] ?? $code);
          $symbol = (string) ($meta['unit_symbol'] ?? '');

          $reg    = $vals[$code]['regular'] ?? ''; // override ذخیره‌شده برای این محصول
      ?>
        <tr>
          <td>
            <strong><?php echo esc_html($name); ?></strong>
          </td>
          <td>
            <code><?php echo esc_html($code); ?></code>
          </td>
          <td>
            <input type="number"
                   step="0.01"
                   class="short mnsnp-w100"
                   name="<?php echo esc_attr($name_prefix . '_mns_navasan_plus_formula_variables' . $name_suffix . '[' . (int)$fid . '][' . $code . '][regular]'); ?>"
                   value="<?php echo esc_attr($reg); ?>"
                   placeholder="e.g. 1" />
          </td>
          <td>
            <?php echo $symbol !== '' ? esc_html($symbol) : '—'; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="description" style="margin-top:6px;">
      <?php esc_html_e('Each code in the expression equals: Unit × Value (the value you set here per product).', 'mns-navasan-plus'); ?>
    </p>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<script>
(function($){
  $(function(){
    var $sel  = $('#mns_navasan_plus_formula_id');
    var $pans = $('.mnsnp-formula-vars-panel');
    $sel.on('change', function(){
      var fid = parseInt($(this).val(), 10) || 0;
      $pans.hide();
      $pans.filter('[data-fid="'+fid+'"]').show();
    });
  });
})(jQuery);
</script>

<style>
.mnsnp-w100{ width:100%; max-width:480px; }
.mnsnp-formula-vars-panel table input.short{ width:100%; }
</style>