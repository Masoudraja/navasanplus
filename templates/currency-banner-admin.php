<?php
/**
 * Currency Banner Template
 * 
 * Template for displaying currency selection interface in admin
 * Used in metaboxes and admin settings pages
 *
 * Variables available:
 * @var array $currencies List of available currencies
 * @var array $selected Selected currency IDs
 * @var string $context Context where template is used (metabox, settings, etc.)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use MNS\NavasanPlus\Templates\Classes\Fields;
use MNS\NavasanPlus\DB;

// Default variables
$currencies = $currencies ?? [];
$selected = $selected ?? [];
$context = $context ?? 'metabox';

// Get available currencies if not provided
if (empty($currencies)) {
    $currency_posts = get_posts([
        'post_type'      => 'mnsnp_currency',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC'
    ]);
    
    $currencies = [];
    foreach ($currency_posts as $post) {
        $currency = new \MNS\NavasanPlus\PublicNS\Currency($post);
        $currencies[] = [
            'id' => $currency->get_id(),
            'name' => $currency->get_name(),
            'code' => $currency->get_code(),
            'rate' => $currency->get_rate(),
            'symbol' => $currency->get_symbol(),
            'display_rate' => $currency->display_rate()
        ];
    }
}

// Enqueue necessary scripts
wp_enqueue_script('jquery-ui-sortable');
?>

<div class="mns-currency-banner-admin">
    <div class="mns-banner-settings">
        <h4><?php _e('انتخاب ارز', 'mns-navasan-plus'); ?></h4>
        
        <div class="mns-currency-selection">
            <div class="mns-available-currencies">
                <label><?php _e('ارزهای موجود', 'mns-navasan-plus'); ?></label>
                <div class="mns-currency-list" id="mns-available-currencies">
                    <?php foreach ($currencies as $currency): ?>
                        <?php if (!in_array($currency['id'], $selected)): ?>
                            <div class="mns-currency-card" data-currency-id="<?php echo esc_attr($currency['id']); ?>">
                                <div class="mns-currency-info">
                                    <strong><?php echo esc_html($currency['name']); ?></strong>
                                    <?php if ($currency['code']): ?>
                                        <span class="currency-code"><?php echo esc_html($currency['code']); ?></span>
                                    <?php endif; ?>
                                    <div class="currency-rate"><?php echo esc_html($currency['display_rate']); ?></div>
                                </div>
                                <div class="mns-currency-actions">
                                    <button type="button" class="button mns-add-currency">
                                        <?php _e('افزودن', 'mns-navasan-plus'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mns-selected-currencies">
                <label><?php _e('ارزهای انتخابی (برای مرتب سازی بکشید)', 'mns-navasan-plus'); ?></label>
                <div class="mns-currency-list mns-sortable" id="mns-selected-currencies">
                    <?php foreach ($selected as $currency_id): ?>
                        <?php 
                        $currency = array_filter($currencies, function($c) use ($currency_id) {
                            return $c['id'] == $currency_id;
                        });
                        $currency = reset($currency);
                        if ($currency):
                        ?>
                            <div class="mns-currency-card selected" data-currency-id="<?php echo esc_attr($currency['id']); ?>">
                                <div class="mns-drag-handle">⋮⋮</div>
                                <div class="mns-currency-info">
                                    <strong><?php echo esc_html($currency['name']); ?></strong>
                                    <?php if ($currency['code']): ?>
                                        <span class="currency-code"><?php echo esc_html($currency['code']); ?></span>
                                    <?php endif; ?>
                                    <div class="currency-rate"><?php echo esc_html($currency['display_rate']); ?></div>
                                </div>
                                <div class="mns-currency-actions">
                                    <button type="button" class="button mns-remove-currency">
                                        <?php _e('حذف', 'mns-navasan-plus'); ?>
                                    </button>
                                </div>
                                <input type="hidden" name="mns_selected_currencies[]" value="<?php echo esc_attr($currency['id']); ?>">
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="mns-banner-options">
            <h4><?php _e('گزینه‌های بنر', 'mns-navasan-plus'); ?></h4>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="banner_name"><?php _e('نام بنر', 'mns-navasan-plus'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="banner_name" id="banner_name" class="regular-text" 
                               value="<?php echo esc_attr(get_option('mns_currency_banner_settings')['name'] ?? 'بنر پیش‌فرض'); ?>" 
                               placeholder="<?php _e('نام بنر را وارد کنید', 'mns-navasan-plus'); ?>">
                        <p class="description"><?php _e('نام توصیفی برای این بنر بدهید', 'mns-navasan-plus'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mns_banner_style"><?php _e('سبک', 'mns-navasan-plus'); ?></label>
                    </th>
                    <td>
                        <select name="mns_banner_style" id="mns_banner_style" class="regular-text">
                            <option value="modern" <?php $settings = get_option('mns_currency_banner_settings', []); selected($settings['style'] ?? 'modern', 'modern'); ?>><?php _e('مدرن', 'mns-navasan-plus'); ?></option>
                            <option value="minimal" <?php selected($settings['style'] ?? 'modern', 'minimal'); ?>><?php _e('مینیمال', 'mns-navasan-plus'); ?></option>
                            <option value="classic" <?php selected($settings['style'] ?? 'modern', 'classic'); ?>><?php _e('کلاسیک', 'mns-navasan-plus'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mns_banner_height"><?php _e('ارتفاع', 'mns-navasan-plus'); ?></label>
                    </th>
                    <td>
                        <select name="mns_banner_height" id="mns_banner_height" class="regular-text">
                            <option value="auto" <?php selected($settings['height'] ?? 'auto', 'auto'); ?>><?php _e('خودکار', 'mns-navasan-plus'); ?></option>
                            <option value="compact" <?php selected($settings['height'] ?? 'auto', 'compact'); ?>><?php _e('فشرده', 'mns-navasan-plus'); ?></option>
                            <option value="tall" <?php selected($settings['height'] ?? 'auto', 'tall'); ?>><?php _e('بلند', 'mns-navasan-plus'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mns_banner_background"><?php _e('پس‌زمینه', 'mns-navasan-plus'); ?></label>
                    </th>
                    <td>
                        <select name="mns_banner_background" id="mns_banner_background" class="regular-text">
                            <option value="gradient" <?php selected($settings['background'] ?? 'gradient', 'gradient'); ?>><?php _e('گرادیان', 'mns-navasan-plus'); ?></option>
                            <option value="solid" <?php selected($settings['background'] ?? 'gradient', 'solid'); ?>><?php _e('یکدست', 'mns-navasan-plus'); ?></option>
                            <option value="custom" <?php selected($settings['background'] ?? 'gradient', 'custom'); ?>><?php _e('رنگ سفارشی', 'mns-navasan-plus'); ?></option>
                            <option value="transparent" <?php selected($settings['background'] ?? 'gradient', 'transparent'); ?>><?php _e('شفاف', 'mns-navasan-plus'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr id="custom_color_row" style="<?php echo ($settings['background'] ?? 'gradient') === 'custom' ? 'display: table-row;' : 'display: none;'; ?>">
                    <th scope="row">
                        <label for="mns_banner_background_color"><?php _e('رنگ پس‌زمینه', 'mns-navasan-plus'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="mns_banner_background_color" id="mns_banner_background_color" 
                               class="color-picker" value="<?php echo esc_attr($settings['background_color'] ?? '#667eea'); ?>" data-default-color="#667eea">
                        <p class="description"><?php _e('رنگ پس‌زمینه سفارشی را انتخاب کنید', 'mns-navasan-plus'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mns_banner_columns"><?php _e('ستون‌ها', 'mns-navasan-plus'); ?></label>
                    </th>
                    <td>
                        <select name="mns_banner_columns" id="mns_banner_columns" class="regular-text">
                            <option value="auto" <?php selected($settings['columns'] ?? 'auto', 'auto'); ?>><?php _e('خودکار', 'mns-navasan-plus'); ?></option>
                            <option value="2" <?php selected($settings['columns'] ?? 'auto', '2'); ?>><?php _e('۲ ستون', 'mns-navasan-plus'); ?></option>
                            <option value="3" <?php selected($settings['columns'] ?? 'auto', '3'); ?>><?php _e('۳ ستون', 'mns-navasan-plus'); ?></option>
                            <option value="4" <?php selected($settings['columns'] ?? 'auto', '4'); ?>><?php _e('۴ ستون', 'mns-navasan-plus'); ?></option>
                            <option value="5" <?php selected($settings['columns'] ?? 'auto', '5'); ?>><?php _e('۵ ستون', 'mns-navasan-plus'); ?></option>
                            <option value="6" <?php selected($settings['columns'] ?? 'auto', '6'); ?>><?php _e('۶ ستون', 'mns-navasan-plus'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mns_banner_auto_refresh"><?php _e('بروزرسانی خودکار (ثانیه)', 'mns-navasan-plus'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="mns_banner_auto_refresh" id="mns_banner_auto_refresh" 
                               value="<?php echo esc_attr($settings['auto_refresh'] ?? 30); ?>" min="0" max="300" class="small-text">
                        <p class="description"><?php _e('برای غیرفعال کردن بروزرسانی خودکار، مقدار ۰ را وارد کنید', 'mns-navasan-plus'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mns_banner_show_change"><?php _e('نمایش تغییر قیمت', 'mns-navasan-plus'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" name="mns_banner_show_change" id="mns_banner_show_change" 
                               value="yes" <?php checked($settings['show_change'] ?? 'yes', 'yes'); ?>>
                        <label for="mns_banner_show_change"><?php _e('نمایش درصد تغییر', 'mns-navasan-plus'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mns_banner_show_symbol"><?php _e('نمایش نماد ارز', 'mns-navasan-plus'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" name="mns_banner_show_symbol" id="mns_banner_show_symbol" 
                               value="yes" <?php checked($settings['show_symbol'] ?? 'no', 'yes'); ?>>
                        <label for="mns_banner_show_symbol"><?php _e('نمایش نمادها/واحدهای ارز', 'mns-navasan-plus'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mns_banner_show_time"><?php _e('نمایش زمان بروزرسانی', 'mns-navasan-plus'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" name="mns_banner_show_time" id="mns_banner_show_time" 
                               value="yes" <?php checked($settings['show_time'] ?? 'yes', 'yes'); ?>>
                        <label for="mns_banner_show_time"><?php _e('نمایش آخرین زمان بروزرسانی', 'mns-navasan-plus'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mns_banner_animation"><?php _e('انیمیشن', 'mns-navasan-plus'); ?></label>
                    </th>
                    <td>
                        <select name="mns_banner_animation" id="mns_banner_animation" class="regular-text">
                            <option value="slide" <?php selected($settings['animation'] ?? 'slide', 'slide'); ?>><?php _e('اسلاید', 'mns-navasan-plus'); ?></option>
                            <option value="fade" <?php selected($settings['animation'] ?? 'slide', 'fade'); ?>><?php _e('محو شدن', 'mns-navasan-plus'); ?></option>
                            <option value="none" <?php selected($settings['animation'] ?? 'slide', 'none'); ?>><?php _e('هیچ', 'mns-navasan-plus'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mns_banner_font_size"><?php _e('اندازه فونت (پیکسل)', 'mns-navasan-plus'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="mns_banner_font_size" id="mns_banner_font_size" 
                               value="<?php echo esc_attr($settings['font_size'] ?? 16); ?>" min="10" max="32" class="small-text">
                        <p class="description"><?php _e('اندازه فونت برای نام‌ها و مقادیر ارز', 'mns-navasan-plus'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mns_banner_width"><?php _e('عرض بنر', 'mns-navasan-plus'); ?></label>
                    </th>
                    <td>
                        <select name="mns_banner_width" id="mns_banner_width" class="regular-text">
                            <option value="100%" <?php selected($settings['width'] ?? '100%', '100%'); ?>><?php _e('عرض کامل (۱۰۰٪)', 'mns-navasan-plus'); ?></option>
                            <option value="auto" <?php selected($settings['width'] ?? '100%', 'auto'); ?>><?php _e('عرض خودکار', 'mns-navasan-plus'); ?></option>
                            <option value="50%" <?php selected($settings['width'] ?? '100%', '50%'); ?>><?php _e('نصف عرض (۵۰٪)', 'mns-navasan-plus'); ?></option>
                            <option value="75%" <?php selected($settings['width'] ?? '100%', '75%'); ?>><?php _e('سه‌چهارم عرض (۷۵٪)', 'mns-navasan-plus'); ?></option>
                            <option value="300px" <?php selected($settings['width'] ?? '100%', '300px'); ?>><?php _e('ثابت ۳۰۰ پیکسل', 'mns-navasan-plus'); ?></option>
                            <option value="500px" <?php selected($settings['width'] ?? '100%', '500px'); ?>><?php _e('ثابت ۵۰۰ پیکسل', 'mns-navasan-plus'); ?></option>
                            <option value="800px" <?php selected($settings['width'] ?? '100%', '800px'); ?>><?php _e('ثابت ۸۰۰ پیکسل', 'mns-navasan-plus'); ?></option>
                        </select>
                        <p class="description"><?php _e('عرض کانتینر بنر', 'mns-navasan-plus'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mns_banner_font_color"><?php _e('رنگ فونت', 'mns-navasan-plus'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="mns_banner_font_color" id="mns_banner_font_color" 
                               class="color-picker" value="<?php echo esc_attr($settings['font_color'] ?? '#333333'); ?>" data-default-color="#333333">
                        <p class="description"><?php _e('رنگ متن برای اطلاعات ارز', 'mns-navasan-plus'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mns_banner_border_radius"><?php _e('شعاع حاشیه (پیکسل)', 'mns-navasan-plus'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="mns_banner_border_radius" id="mns_banner_border_radius" 
                               value="<?php echo esc_attr($settings['border_radius'] ?? 8); ?>" min="0" max="50" class="small-text">
                        <p class="description"><?php _e('گوشه‌های گرد برای بنر و آیتم‌های ارز', 'mns-navasan-plus'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mns_banner_padding"><?php _e('فاصله داخلی (پیکسل)', 'mns-navasan-plus'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="mns_banner_padding" id="mns_banner_padding" 
                               value="<?php echo esc_attr($settings['padding'] ?? 20); ?>" min="5" max="50" class="small-text">
                        <p class="description"><?php _e('فاصله داخلی درون بنر', 'mns-navasan-plus'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mns_banner_margin"><?php _e('حاشیه (پیکسل)', 'mns-navasan-plus'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="mns_banner_margin" id="mns_banner_margin" 
                               value="<?php echo esc_attr($settings['margin'] ?? 10); ?>" min="0" max="50" class="small-text">
                        <p class="description"><?php _e('فاصله خارجی اطراف بنر', 'mns-navasan-plus'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mns_banner_item_spacing"><?php _e('فاصله آیتم‌ها (پیکسل)', 'mns-navasan-plus'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="mns_banner_item_spacing" id="mns_banner_item_spacing" 
                               value="<?php echo esc_attr($settings['item_spacing'] ?? 15); ?>" min="5" max="30" class="small-text">
                        <p class="description"><?php _e('فاصله بین آیتم‌های ارز', 'mns-navasan-plus'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mns_banner_shadow"><?php _e('سایه کادر', 'mns-navasan-plus'); ?></label>
                    </th>
                    <td>
                        <select name="mns_banner_shadow" id="mns_banner_shadow" class="regular-text">
                            <option value="none" <?php selected($settings['shadow'] ?? 'light', 'none'); ?>><?php _e('هیچ', 'mns-navasan-plus'); ?></option>
                            <option value="light" <?php selected($settings['shadow'] ?? 'light', 'light'); ?>><?php _e('ملایم', 'mns-navasan-plus'); ?></option>
                            <option value="medium" <?php selected($settings['shadow'] ?? 'light', 'medium'); ?>><?php _e('متوسط', 'mns-navasan-plus'); ?></option>
                            <option value="heavy" <?php selected($settings['shadow'] ?? 'light', 'heavy'); ?>><?php _e('قوی', 'mns-navasan-plus'); ?></option>
                        </select>
                        <p class="description"><?php _e('افکت سایه افتان برای بنر', 'mns-navasan-plus'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="mns-shortcode-generator">
            <h4><?php _e('شورت‌کد تولید شده', 'mns-navasan-plus'); ?></h4>
            <div class="mns-shortcode-output">
                <input type="text" id="mns-generated-shortcode" class="large-text code" readonly 
                       value="[mns_currency_banner]">
                <button type="button" class="button button-secondary mns-copy-shortcode">
                    <?php _e('کپی', 'mns-navasan-plus'); ?>
                </button>
            </div>
            <p class="description">
                <?php _e('این شورت‌کد را کپی کنید و در هر نقطه از سایت که می‌خواهید بنر ارز را نمایش دهید، قرار دهید.', 'mns-navasan-plus'); ?>
            </p>
        </div>
    </div>
</div>

<style>
.mns-currency-banner-admin {
    max-width: 100%;
}

.mns-currency-selection {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
}

.mns-available-currencies,
.mns-selected-currencies {
    flex: 1;
}

.mns-currency-list {
    border: 1px solid #ddd;
    border-radius: 6px;
    min-height: 200px;
    padding: 10px;
    background: #fafafa;
}

.mns-currency-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 8px;
    transition: all 0.2s ease;
}

.mns-currency-card:hover {
    border-color: #0073aa;
    box-shadow: 0 2px 8px rgba(0,115,170,0.1);
}

.mns-currency-card.selected {
    border-left: 4px solid #0073aa;
}

.mns-drag-handle {
    cursor: move;
    color: #666;
    font-weight: bold;
    padding: 5px;
}

.mns-currency-info {
    flex: 1;
}

.mns-currency-info strong {
    display: block;
    font-size: 14px;
    margin-bottom: 2px;
}

.currency-code {
    font-size: 11px;
    color: #666;
    text-transform: uppercase;
    margin-left: 8px;
}

.currency-rate {
    font-size: 12px;
    color: #0073aa;
    font-weight: 500;
}

.mns-currency-actions .button {
    padding: 4px 12px;
    font-size: 11px;
}

.mns-sortable {
    cursor: default;
}

.mns-sortable .mns-currency-card {
    cursor: move;
}

.ui-sortable-helper {
    transform: rotate(2deg);
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.mns-banner-options {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.mns-shortcode-generator {
    background: #f0f8ff;
    padding: 20px;
    border-radius: 6px;
    border-left: 4px solid #0073aa;
}

.mns-shortcode-output {
    display: flex;
    gap: 10px;
    align-items: center;
    margin: 10px 0;
}

.mns-copy-shortcode {
    white-space: nowrap;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize color picker
    if ($.fn.wpColorPicker) {
        $('.color-picker').wpColorPicker({
            change: function() {
                setTimeout(function() {
                    updateShortcode();
                }, 100);
            }
        });
    }
    
    // Show/hide custom color option
    function toggleCustomColor() {
        if ($('#mns_banner_background').val() === 'custom') {
            $('#custom_color_row').show();
        } else {
            $('#custom_color_row').hide();
        }
    }
    
    $('#mns_banner_background').on('change', toggleCustomColor);
    toggleCustomColor();
    
    // Make selected currencies sortable
    $('#mns-selected-currencies').sortable({
        handle: '.mns-drag-handle',
        placeholder: 'ui-sortable-placeholder',
        update: function() {
            updateShortcode();
        }
    });

    // Add currency
    $(document).on('click', '.mns-add-currency', function() {
        const $card = $(this).closest('.mns-currency-card');
        const currencyId = $card.data('currency-id');
        
        $card.append('<input type="hidden" name="mns_selected_currencies[]" value="' + currencyId + '">');
        $card.prepend('<div class="mns-drag-handle">⋮⋮</div>');
        $(this).text('<?php _e("حذف", "mns-navasan-plus"); ?>').removeClass('mns-add-currency').addClass('mns-remove-currency');
        $card.addClass('selected').appendTo('#mns-selected-currencies');
        updateShortcode();
    });

    // Remove currency
    $(document).on('click', '.mns-remove-currency', function() {
        const $card = $(this).closest('.mns-currency-card');
        $card.find('input[type="hidden"]').remove();
        $card.find('.mns-drag-handle').remove();
        $(this).text('<?php _e("افزودن", "mns-navasan-plus"); ?>').removeClass('mns-remove-currency').addClass('mns-add-currency');
        $card.removeClass('selected').appendTo('#mns-available-currencies');
        updateShortcode();
    });

    // Update shortcode when options change
    $('.mns-banner-options input, .mns-banner-options select').on('change input', function() {
        setTimeout(updateShortcode, 50);
    });

    // Copy shortcode
    $('.mns-copy-shortcode').on('click', function() {
        const $input = $('#mns-generated-shortcode');
        $input.select();
        document.execCommand('copy');
        const $button = $(this);
        const originalText = $button.text();
        $button.text('<?php _e("کپی شد!", "mns-navasan-plus"); ?>');
        setTimeout(() => { $button.text(originalText); }, 2000);
    });

    function updateShortcode() {
        const currencies = [];
        $('#mns-selected-currencies .mns-currency-card').each(function() {
            currencies.push($(this).data('currency-id'));
        });

        let shortcode = '[mns_currency_banner';
        
        if (currencies.length) {
            shortcode += ' currencies="' + currencies.join(',') + '"';
        }
        
        const style = $('#mns_banner_style').val();
        if (style && style !== 'modern') {
            shortcode += ' style="' + style + '"';
        }
        
        const height = $('#mns_banner_height').val();
        if (height && height !== 'auto') {
            shortcode += ' height="' + height + '"';
        }
        
        const background = $('#mns_banner_background').val();
        if (background && background !== 'gradient') {
            shortcode += ' background="' + background + '"';
            if (background === 'custom') {
                const color = $('#mns_banner_background_color').val();
                if (color) {
                    shortcode += ' background_color="' + color + '"';
                }
            }
        }
        
        const columns = $('#mns_banner_columns').val();
        if (columns && columns !== 'auto') {
            shortcode += ' columns="' + columns + '"';
        }
        
        const autoRefresh = $('#mns_banner_auto_refresh').val();
        if (autoRefresh && autoRefresh !== '30') {
            shortcode += ' auto_refresh="' + autoRefresh + '"';
        }
        
        const showChange = $('#mns_banner_show_change').is(':checked');
        if (!showChange) {
            shortcode += ' show_change="no"';
        }
        
        const showSymbol = $('#mns_banner_show_symbol').is(':checked');
        if (showSymbol) {
            shortcode += ' show_symbol="yes"';
        }
        
        const showTime = $('#mns_banner_show_time').is(':checked');
        if (!showTime) {
            shortcode += ' show_time="no"';
        }
        
        const animation = $('#mns_banner_animation').val();
        if (animation && animation !== 'slide') {
            shortcode += ' animation="' + animation + '"';
        }
        
        // UI Customization
        const fontSize = $('#mns_banner_font_size').val();
        if (fontSize && fontSize !== '16') {
            shortcode += ' font_size="' + fontSize + '"';
        }
        
        const width = $('#mns_banner_width').val();
        if (width && width !== '100%') {
            shortcode += ' width="' + width + '"';
        }
        
        const fontColor = $('#mns_banner_font_color').val();
        if (fontColor && fontColor !== '#333333') {
            shortcode += ' font_color="' + fontColor + '"';
        }
        
        const borderRadius = $('#mns_banner_border_radius').val();
        if (borderRadius && borderRadius !== '8') {
            shortcode += ' border_radius="' + borderRadius + '"';
        }
        
        const padding = $('#mns_banner_padding').val();
        if (padding && padding !== '20') {
            shortcode += ' padding="' + padding + '"';
        }
        
        const margin = $('#mns_banner_margin').val();
        if (margin && margin !== '10') {
            shortcode += ' margin="' + margin + '"';
        }
        
        const itemSpacing = $('#mns_banner_item_spacing').val();
        if (itemSpacing && itemSpacing !== '15') {
            shortcode += ' item_spacing="' + itemSpacing + '"';
        }
        
        const shadow = $('#mns_banner_shadow').val();
        if (shadow && shadow !== 'light') {
            shortcode += ' shadow="' + shadow + '"';
        }
        
        shortcode += ']';
        $('#mns-generated-shortcode').val(shortcode);
    }

    // Initial setup
    updateShortcode();
});
</script>