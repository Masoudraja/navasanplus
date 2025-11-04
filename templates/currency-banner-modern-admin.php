<?php
/**
 * Modern Currency Banner Template
 * 
 * Template for displaying modern currency selection interface in admin
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

<div class="mns-currency-banner-admin-modern">
    <div class="mns-banner-settings-modern">
        <div class="mns-currency-selection-modern">
            <div class="mns-available-currencies-modern">
                <h3><?php _e('ارزهای موجود', 'mns-navasan-plus'); ?></h3>
                <div class="mns-currency-list-modern" id="mns-available-currencies-modern">
                    <?php foreach ($currencies as $currency): ?>
                        <?php if (!in_array($currency['id'], $selected)): ?>
                            <div class="mns-currency-card-modern" data-currency-id="<?php echo esc_attr($currency['id']); ?>">
                                <div class="mns-currency-info-modern">
                                    <strong><?php echo esc_html($currency['name']); ?></strong>
                                    <?php if ($currency['code']): ?>
                                        <span class="currency-code-modern"><?php echo esc_html($currency['code']); ?></span>
                                    <?php endif; ?>
                                    <div class="currency-rate-modern"><?php echo esc_html($currency['display_rate']); ?></div>
                                </div>
                                <div class="mns-currency-actions-modern">
                                    <button type="button" class="button mns-add-currency-modern">
                                        <?php _e('افزودن', 'mns-navasan-plus'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mns-selected-currencies-modern">
                <h3><?php _e('ارزهای انتخابی (برای مرتب سازی بکشید)', 'mns-navasan-plus'); ?></h3>
                <div class="mns-currency-list-modern mns-sortable-modern" id="mns-selected-currencies-modern">
                    <?php foreach ($selected as $currency_id): ?>
                        <?php 
                        $currency = array_filter($currencies, function($c) use ($currency_id) {
                            return $c['id'] == $currency_id;
                        });
                        $currency = reset($currency);
                        if ($currency):
                        ?>
                            <div class="mns-currency-card-modern selected-modern" data-currency-id="<?php echo esc_attr($currency['id']); ?>">
                                <div class="mns-drag-handle-modern">⋮⋮</div>
                                <div class="mns-currency-info-modern">
                                    <strong><?php echo esc_html($currency['name']); ?></strong>
                                    <?php if ($currency['code']): ?>
                                        <span class="currency-code-modern"><?php echo esc_html($currency['code']); ?></span>
                                    <?php endif; ?>
                                    <div class="currency-rate-modern"><?php echo esc_html($currency['display_rate']); ?></div>
                                </div>
                                <div class="mns-currency-actions-modern">
                                    <button type="button" class="button mns-remove-currency-modern">
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

        <div class="mns-banner-options-modern">
            <h3><?php _e('گزینه‌های بنر', 'mns-navasan-plus'); ?></h3>
            
            <div class="mns-options-tabs">
                <div class="mns-tabs-nav">
                    <button type="button" class="mns-tab-button active" data-tab="appearance"><?php _e('ظاهر', 'mns-navasan-plus'); ?></button>
                    <button type="button" class="mns-tab-button" data-tab="animations"><?php _e('انیمیشن‌ها', 'mns-navasan-plus'); ?></button>
                    <button type="button" class="mns-tab-button" data-tab="display"><?php _e('نمایش', 'mns-navasan-plus'); ?></button>
                    <button type="button" class="mns-tab-button" data-tab="customization"><?php _e('سفارشی‌سازی', 'mns-navasan-plus'); ?></button>
                    <button type="button" class="mns-tab-button" data-tab="behavior"><?php _e('رفتار', 'mns-navasan-plus'); ?></button>
                </div>
                
                <div class="mns-tabs-content">
                    <!-- Appearance Tab -->
                    <div class="mns-tab-content active" data-tab="appearance">
                        <div class="mns-option-group">
                            <h4><?php _e('پیکربندی ظاهری', 'mns-navasan-plus'); ?></h4>
                            
                            <div class="mns-form-field">
                                <label for="banner_name"><?php _e('نام بنر', 'mns-navasan-plus'); ?></label>
                                <input type="text" name="banner_name" id="banner_name" class="regular-text" 
                                       value="<?php echo esc_attr(get_option('mns_currency_banner_settings')['name'] ?? 'بنر پیش‌فرض'); ?>" 
                                       placeholder="<?php _e('نام بنر را وارد کنید', 'mns-navasan-plus'); ?>">
                                <p class="description"><?php _e('نام توصیفی برای این بنر بدهید', 'mns-navasan-plus'); ?></p>
                            </div>
                            
                            <div class="mns-form-field">
                                <label for="mns_banner_style"><?php _e('سبک', 'mns-navasan-plus'); ?></label>
                                <select name="mns_banner_style" id="mns_banner_style" class="regular-text">
                                    <option value="modern" <?php $settings = get_option('mns_currency_banner_settings', []); selected($settings['style'] ?? 'modern', 'modern'); ?>><?php _e('مدرن', 'mns-navasan-plus'); ?></option>
                                    <option value="glass" <?php selected($settings['style'] ?? 'modern', 'glass'); ?>><?php _e('شیشه‌ای', 'mns-navasan-plus'); ?></option>
                                    <option value="minimal" <?php selected($settings['style'] ?? 'modern', 'minimal'); ?>><?php _e('مینیمال', 'mns-navasan-plus'); ?></option>
                                </select>
                            </div>
                            
                            <div class="mns-form-field">
                                <label for="mns_banner_background"><?php _e('پس‌زمینه', 'mns-navasan-plus'); ?></label>
                                <select name="mns_banner_background" id="mns_banner_background" class="regular-text">
                                    <option value="gradient" <?php selected($settings['background'] ?? 'gradient', 'gradient'); ?>><?php _e('گرادیان', 'mns-navasan-plus'); ?></option>
                                    <option value="solid" <?php selected($settings['background'] ?? 'gradient', 'solid'); ?>><?php _e('یکدست', 'mns-navasan-plus'); ?></option>
                                    <option value="custom" <?php selected($settings['background'] ?? 'gradient', 'custom'); ?>><?php _e('رنگ سفارشی', 'mns-navasan-plus'); ?></option>
                                    <option value="transparent" <?php selected($settings['background'] ?? 'gradient', 'transparent'); ?>><?php _e('شفاف', 'mns-navasan-plus'); ?></option>
                                </select>
                            </div>
                            
                            <div class="mns-form-field" id="custom_color_row_modern" style="<?php echo ($settings['background'] ?? 'gradient') === 'custom' ? 'display: block;' : 'display: none;'; ?>">
                                <label for="mns_banner_background_color"><?php _e('رنگ پس‌زمینه', 'mns-navasan-plus'); ?></label>
                                <input type="text" name="mns_banner_background_color" id="mns_banner_background_color" 
                                       class="color-picker" value="<?php echo esc_attr($settings['background_color'] ?? '#667eea'); ?>" data-default-color="#667eea">
                                <p class="description"><?php _e('رنگ پس‌زمینه سفارشی را انتخاب کنید', 'mns-navasan-plus'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Animations Tab -->
                    <div class="mns-tab-content" data-tab="animations">
                        <div class="mns-option-group">
                            <h4><?php _e('انیمیشن‌ها', 'mns-navasan-plus'); ?></h4>
                            
                            <div class="mns-form-field">
                                <label for="mns_banner_animation"><?php _e('انیمیشن دسکتاپ', 'mns-navasan-plus'); ?></label>
                                <select name="mns_banner_animation" id="mns_banner_animation" class="regular-text">
                                    <option value="slide" <?php selected($settings['animation'] ?? 'slide', 'slide'); ?>><?php _e('لغزش', 'mns-navasan-plus'); ?></option>
                                    <option value="fade" <?php selected($settings['animation'] ?? 'slide', 'fade'); ?>><?php _e('محو شدن', 'mns-navasan-plus'); ?></option>
                                    <option value="ticker" <?php selected($settings['animation'] ?? 'slide', 'ticker'); ?>><?php _e('تیکر', 'mns-navasan-plus'); ?></option>
                                    <option value="swiper" <?php selected($settings['animation'] ?? 'slide', 'swiper'); ?>><?php _e('Swiper', 'mns-navasan-plus'); ?></option>
                                    <option value="none" <?php selected($settings['animation'] ?? 'slide', 'none'); ?>><?php _e('هیچ', 'mns-navasan-plus'); ?></option>
                                </select>
                            </div>
                            
                            <div class="mns-form-field">
                                <label for="mns_banner_mobile_animation"><?php _e('انیمیشن موبایل (تا ۵۷۶ پیکسل)', 'mns-navasan-plus'); ?></label>
                                <select name="mns_banner_mobile_animation" id="mns_banner_mobile_animation" class="regular-text">
                                    <option value="carousel" <?php selected($settings['mobile_animation'] ?? 'carousel', 'carousel'); ?>><?php _e('چرخ فلک (اسکرول افقی)', 'mns-navasan-plus'); ?></option>
                                    <option value="ticker" <?php selected($settings['mobile_animation'] ?? 'carousel', 'ticker'); ?>><?php _e('تیکر', 'mns-navasan-plus'); ?></option>
                                    <option value="stacked" <?php selected($settings['mobile_animation'] ?? 'carousel', 'stacked'); ?>><?php _e('پشته‌ای (عمودی)', 'mns-navasan-plus'); ?></option>
                                    <option value="grid" <?php selected($settings['mobile_animation'] ?? 'carousel', 'grid'); ?>><?php _e('شبکه', 'mns-navasan-plus'); ?></option>
                                    <option value="swiper" <?php selected($settings['mobile_animation'] ?? 'carousel', 'swiper'); ?>><?php _e('Swiper', 'mns-navasan-plus'); ?></option>
                                    <option value="none" <?php selected($settings['mobile_animation'] ?? 'carousel', 'none'); ?>><?php _e('هیچ', 'mns-navasan-plus'); ?></option>
                                </select>
                            </div>
                            
                            <div class="mns-form-field">
                                <label for="mns_banner_tablet_view"><?php _e('نمای تبلت (۵۷۷ تا ۷۶۸ پیکسل)', 'mns-navasan-plus'); ?></label>
                                <select name="mns_banner_tablet_view" id="mns_banner_tablet_view" class="regular-text">
                                    <option value="grid" <?php selected($settings['tablet_view'] ?? 'grid', 'grid'); ?>><?php _e('شبکه', 'mns-navasan-plus'); ?></option>
                                    <option value="carousel" <?php selected($settings['tablet_view'] ?? 'grid', 'carousel'); ?>><?php _e('چرخ فلک (اسکرول افقی)', 'mns-navasan-plus'); ?></option>
                                    <option value="ticker" <?php selected($settings['tablet_view'] ?? 'grid', 'ticker'); ?>><?php _e('تیکر', 'mns-navasan-plus'); ?></option>
                                    <option value="stacked" <?php selected($settings['tablet_view'] ?? 'grid', 'stacked'); ?>><?php _e('پشته‌ای (عمودی)', 'mns-navasan-plus'); ?></option>
                                    <option value="swiper" <?php selected($settings['tablet_view'] ?? 'grid', 'swiper'); ?>><?php _e('Swiper', 'mns-navasan-plus'); ?></option>
                                    <option value="none" <?php selected($settings['tablet_view'] ?? 'grid', 'none'); ?>><?php _e('هیچ', 'mns-navasan-plus'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Display Tab -->
                    <div class="mns-tab-content" data-tab="display">
                        <div class="mns-option-group">
                            <h4><?php _e('نمایش', 'mns-navasan-plus'); ?></h4>
                            
                            <div class="mns-form-field">
                                <label for="mns_banner_show_change">
                                    <input type="checkbox" name="mns_banner_show_change" id="mns_banner_show_change" 
                                           value="yes" <?php checked($settings['show_change'] ?? 'yes', 'yes'); ?>>
                                    <?php _e('نمایش تغییر قیمت', 'mns-navasan-plus'); ?>
                                </label>
                                <p class="description"><?php _e('نمایش درصد تغییر قیمت', 'mns-navasan-plus'); ?></p>
                            </div>
                            
                            <div class="mns-form-field">
                                <label for="mns_banner_show_symbol">
                                    <input type="checkbox" name="mns_banner_show_symbol" id="mns_banner_show_symbol" 
                                           value="yes" <?php checked($settings['show_symbol'] ?? 'no', 'yes'); ?>>
                                    <?php _e('نمایش نماد ارز', 'mns-navasan-plus'); ?>
                                </label>
                                <p class="description"><?php _e('نمایش نمادها/واحدهای ارز', 'mns-navasan-plus'); ?></p>
                            </div>
                            
                            <div class="mns-form-field">
                                <label for="mns_banner_show_time">
                                    <input type="checkbox" name="mns_banner_show_time" id="mns_banner_show_time" 
                                           value="yes" <?php checked($settings['show_time'] ?? 'yes', 'yes'); ?>>
                                    <?php _e('نمایش زمان بروزرسانی', 'mns-navasan-plus'); ?>
                                </label>
                                <p class="description"><?php _e('نمایش آخرین زمان بروزرسانی', 'mns-navasan-plus'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Customization Tab -->
                    <div class="mns-tab-content" data-tab="customization">
                        <div class="mns-option-group">
                            <h4><?php _e('سفارشی‌سازی', 'mns-navasan-plus'); ?></h4>
                            
                            <div class="mns-form-field">
                                <label for="mns_banner_font_size"><?php _e('اندازه فونت (پیکسل)', 'mns-navasan-plus'); ?></label>
                                <input type="number" name="mns_banner_font_size" id="mns_banner_font_size" 
                                       value="<?php echo esc_attr($settings['font_size'] ?? 16); ?>" min="10" max="32" class="small-text">
                            </div>
                            
                            <div class="mns-form-field">
                                <label for="mns_banner_font_color"><?php _e('رنگ فونت', 'mns-navasan-plus'); ?></label>
                                <input type="text" name="mns_banner_font_color" id="mns_banner_font_color" 
                                       class="color-picker" value="<?php echo esc_attr($settings['font_color'] ?? '#ffffff'); ?>" data-default-color="#ffffff">
                            </div>
                            
                            <div class="mns-form-field">
                                <label for="mns_banner_border_radius"><?php _e('شعاع حاشیه (پیکسل)', 'mns-navasan-plus'); ?></label>
                                <input type="number" name="mns_banner_border_radius" id="mns_banner_border_radius" 
                                       value="<?php echo esc_attr($settings['border_radius'] ?? 8); ?>" min="0" max="50" class="small-text">
                            </div>
                            
                            <div class="mns-form-field">
                                <label for="mns_banner_padding"><?php _e('فاصله داخلی (پیکسل)', 'mns-navasan-plus'); ?></label>
                                <input type="number" name="mns_banner_padding" id="mns_banner_padding" 
                                       value="<?php echo esc_attr($settings['padding'] ?? 20); ?>" min="5" max="50" class="small-text">
                            </div>
                            
                            <div class="mns-form-field">
                                <label for="mns_banner_item_spacing"><?php _e('فاصله آیتم‌ها (پیکسل)', 'mns-navasan-plus'); ?></label>
                                <input type="number" name="mns_banner_item_spacing" id="mns_banner_item_spacing" 
                                       value="<?php echo esc_attr($settings['item_spacing'] ?? 15); ?>" min="5" max="30" class="small-text">
                            </div>
                            
                            <div class="mns-form-field">
                                <label for="mns_banner_shadow"><?php _e('سایه کادر', 'mns-navasan-plus'); ?></label>
                                <select name="mns_banner_shadow" id="mns_banner_shadow" class="regular-text">
                                    <option value="none" <?php selected($settings['shadow'] ?? 'light', 'none'); ?>><?php _e('هیچ', 'mns-navasan-plus'); ?></option>
                                    <option value="light" <?php selected($settings['shadow'] ?? 'light', 'light'); ?>><?php _e('ملایم', 'mns-navasan-plus'); ?></option>
                                    <option value="medium" <?php selected($settings['shadow'] ?? 'light', 'medium'); ?>><?php _e('متوسط', 'mns-navasan-plus'); ?></option>
                                    <option value="heavy" <?php selected($settings['shadow'] ?? 'light', 'heavy'); ?>><?php _e('قوی', 'mns-navasan-plus'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Behavior Tab -->
                    <div class="mns-tab-content" data-tab="behavior">
                        <div class="mns-option-group">
                            <h4><?php _e('رفتار', 'mns-navasan-plus'); ?></h4>
                            
                            <div class="mns-form-field">
                                <label for="mns_banner_auto_refresh">
                                    <input type="checkbox" name="mns_banner_auto_refresh" id="mns_banner_auto_refresh" 
                                           value="30" <?php checked($settings['auto_refresh'] ?? '30', '30'); ?>>
                                    <?php _e('بروزرسانی خودکار', 'mns-navasan-plus'); ?>
                                </label>
                                <p class="description"><?php _e('بروزرسانی خودکار نرخ‌ها هر ۳۰ ثانیه', 'mns-navasan-plus'); ?></p>
                            </div>
                            
                            <div class="mns-form-field">
                                <label for="mns_banner_refresh_interval"><?php _e('فاصله بروزرسانی (ثانیه)', 'mns-navasan-plus'); ?></label>
                                <input type="number" name="mns_banner_refresh_interval" id="mns_banner_refresh_interval" 
                                       value="<?php echo esc_attr($settings['refresh_interval'] ?? 30); ?>" min="10" max="300" class="small-text">
                                <p class="description"><?php _e('فاصله زمانی بین بروزرسانی‌های خودکار (حداقل ۱۰ ثانیه)', 'mns-navasan-plus'); ?></p>
                            </div>
                            
                            <div class="mns-form-field">
                                <label for="mns_banner_max_currencies"><?php _e('حداکثر تعداد ارزها', 'mns-navasan-plus'); ?></label>
                                <input type="number" name="mns_banner_max_currencies" id="mns_banner_max_currencies" 
                                       value="<?php echo esc_attr($settings['max_currencies'] ?? 6); ?>" min="1" max="20" class="small-text">
                                <p class="description"><?php _e('حداکثر تعداد ارزهای قابل نمایش در بنر', 'mns-navasan-plus'); ?></p>
                            </div>
                            
                            <div class="mns-form-field">
                                <label for="mns_banner_enable_hover_effect">
                                    <input type="checkbox" name="mns_banner_enable_hover_effect" id="mns_banner_enable_hover_effect" 
                                           value="yes" <?php checked($settings['enable_hover_effect'] ?? 'yes', 'yes'); ?>>
                                    <?php _e('فعال‌سازی جلوه‌های هاور', 'mns-navasan-plus'); ?>
                                </label>
                                <p class="description"><?php _e('نمایش جلوه‌های بصری هنگام عبور موس از روی آیتم‌ها', 'mns-navasan-plus'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mns-shortcode-generator-modern">
            <h3><?php _e('شورت‌کد تولید شده', 'mns-navasan-plus'); ?></h3>
            <div class="mns-shortcode-output-modern">
                <input type="text" id="mns-generated-shortcode-modern" class="large-text code" readonly 
                       value="[mns_currency_banner]">
                <button type="button" class="button button-secondary mns-copy-shortcode-modern">
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
.mns-currency-banner-admin-modern {
    max-width: 100%;
}

.mns-currency-selection-modern {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.mns-available-currencies-modern,
.mns-selected-currencies-modern {
    background: #f9f9f9;
    border-radius: 8px;
    padding: 20px;
}

.mns-available-currencies-modern h3,
.mns-selected-currencies-modern h3 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #ddd;
}

.mns-currency-list-modern {
    border: 1px solid #ddd;
    border-radius: 6px;
    min-height: 200px;
    padding: 10px;
    background: #fafafa;
}

.mns-currency-card-modern {
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

.mns-currency-card-modern:hover {
    border-color: #0073aa;
    box-shadow: 0 2px 8px rgba(0,115,170,0.1);
}

.mns-currency-card-modern.selected-modern {
    border-left: 4px solid #0073aa;
}

.mns-drag-handle-modern {
    cursor: move;
    color: #666;
    font-weight: bold;
    padding: 5px;
}

.mns-currency-info-modern {
    flex: 1;
}

.mns-currency-info-modern strong {
    display: block;
    font-size: 14px;
    margin-bottom: 2px;
}

.currency-code-modern {
    font-size: 11px;
    color: #666;
    text-transform: uppercase;
    margin-left: 8px;
}

.currency-rate-modern {
    font-size: 12px;
    color: #0073aa;
    font-weight: 500;
}

.mns-currency-actions-modern .button {
    padding: 4px 12px;
    font-size: 11px;
}

.mns-sortable-modern {
    cursor: default;
}

.mns-sortable-modern .mns-currency-card-modern {
    cursor: move;
}

.ui-sortable-helper {
    transform: rotate(2deg);
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.mns-banner-options-modern {
    background: #f0f6fc;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.mns-banner-options-modern h3 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #ddd;
}

.mns-options-tabs {
    background: white;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.mns-tabs-nav {
    display: flex;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
    border-radius: 6px 6px 0 0;
}

.mns-tab-button {
    padding: 12px 20px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-weight: 500;
    color: #666;
    border-bottom: 3px solid transparent;
    transition: all 0.2s ease;
}

.mns-tab-button:hover {
    color: #0073aa;
    background: #e9ecef;
}

.mns-tab-button.active {
    color: #0073aa;
    border-bottom-color: #0073aa;
    background: white;
}

.mns-tabs-content {
    padding: 20px;
}

.mns-tab-content {
    display: none;
}

.mns-tab-content.active {
    display: block;
}

.mns-option-group {
    margin-bottom: 20px;
}

.mns-option-group h4 {
    margin-top: 0;
    color: #0073aa;
    border-bottom: 1px solid #eee;
    padding-bottom: 8px;
    margin-bottom: 15px;
}

.mns-form-field {
    margin-bottom: 15px;
}

.mns-form-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.mns-form-field input[type="text"],
.mns-form-field input[type="number"],
.mns-form-field select {
    width: 100%;
}

.mns-form-field .description {
    font-size: 12px;
    color: #666;
    margin-top: 3px;
}

.mns-shortcode-generator-modern {
    background: #f0f8ff;
    padding: 20px;
    border-radius: 6px;
    border-left: 4px solid #0073aa;
}

.mns-shortcode-generator-modern h3 {
    margin-top: 0;
}

.mns-shortcode-output-modern {
    display: flex;
    gap: 10px;
    align-items: center;
    margin: 10px 0;
}

.mns-copy-shortcode-modern {
    white-space: nowrap;
}

@media (max-width: 768px) {
    .mns-currency-selection-modern {
        grid-template-columns: 1fr;
    }
    
    .mns-tabs-nav {
        flex-wrap: wrap;
    }
    
    .mns-tab-button {
        flex: 1 0 auto;
        text-align: center;
        padding: 10px;
        font-size: 14px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize color picker
    if ($.fn.wpColorPicker) {
        $('.color-picker').wpColorPicker({
            change: function() {
                setTimeout(function() {
                    updateShortcodeModern();
                }, 100);
            }
        });
    }
    
    // Show/hide custom color option
    function toggleCustomColorModern() {
        if ($('#mns_banner_background').val() === 'custom') {
            $('#custom_color_row_modern').show();
        } else {
            $('#custom_color_row_modern').hide();
        }
    }
    
    $('#mns_banner_background').on('change', toggleCustomColorModern);
    toggleCustomColorModern();
    
    // Tab navigation
    $('.mns-tab-button').on('click', function() {
        const tab = $(this).data('tab');
        
        // Update active tab button
        $('.mns-tab-button').removeClass('active');
        $(this).addClass('active');
        
        // Show active tab content
        $('.mns-tab-content').removeClass('active');
        $('.mns-tab-content[data-tab="' + tab + '"]').addClass('active');
    });
    
    // Make selected currencies sortable
    $('#mns-selected-currencies-modern').sortable({
        handle: '.mns-drag-handle-modern',
        placeholder: 'ui-sortable-placeholder',
        update: function() {
            updateShortcodeModern();
        }
    });

    // Add currency
    $(document).on('click', '.mns-add-currency-modern', function() {
        const $card = $(this).closest('.mns-currency-card-modern');
        const currencyId = $card.data('currency-id');
        
        $card.append('<input type="hidden" name="mns_selected_currencies[]" value="' + currencyId + '">');
        $card.prepend('<div class="mns-drag-handle-modern">⋮⋮</div>');
        $(this).text('<?php _e("حذف", "mns-navasan-plus"); ?>').removeClass('mns-add-currency-modern').addClass('mns-remove-currency-modern');
        $card.addClass('selected-modern').appendTo('#mns-selected-currencies-modern');
        updateShortcodeModern();
    });

    // Remove currency
    $(document).on('click', '.mns-remove-currency-modern', function() {
        const $card = $(this).closest('.mns-currency-card-modern');
        $card.find('input[type="hidden"]').remove();
        $card.find('.mns-drag-handle-modern').remove();
        $(this).text('<?php _e("افزودن", "mns-navasan-plus"); ?>').removeClass('mns-remove-currency-modern').addClass('mns-add-currency-modern');
        $card.removeClass('selected-modern').appendTo('#mns-available-currencies-modern');
        updateShortcodeModern();
    });

    // Update shortcode when options change
    $('.mns-banner-options-modern input, .mns-banner-options-modern select').on('change input', function() {
        setTimeout(updateShortcodeModern, 50);
    });

    // Copy shortcode
    $('.mns-copy-shortcode-modern').on('click', function() {
        const $input = $('#mns-generated-shortcode-modern');
        $input.select();
        document.execCommand('copy');
        const $button = $(this);
        const originalText = $button.text();
        $button.text('<?php _e("کپی شد!", "mns-navasan-plus"); ?>');
        setTimeout(() => { $button.text(originalText); }, 2000);
    });

    function updateShortcodeModern() {
        const currencies = [];
        $('#mns-selected-currencies-modern .mns-currency-card-modern').each(function() {
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
        
        const autoRefresh = $('#mns_banner_auto_refresh').is(':checked');
        if (!autoRefresh) {
            shortcode += ' auto_refresh="0"';
        } else {
            // Add refresh interval if auto refresh is enabled
            const refreshInterval = $('#mns_banner_refresh_interval').val();
            if (refreshInterval && refreshInterval !== '30') {
                shortcode += ' refresh_interval="' + refreshInterval + '"';
            }
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
        
        const mobileAnimation = $('#mns_banner_mobile_animation').val();
        if (mobileAnimation && mobileAnimation !== 'carousel') {
            shortcode += ' mobile_animation="' + mobileAnimation + '"';
        }
        
        // Add tablet_view parameter
        const tabletView = $('#mns_banner_tablet_view').val();
        if (tabletView && tabletView !== 'grid') {
            shortcode += ' tablet_view="' + tabletView + '"';
        }
        
        // UI Customization
        const fontSize = $('#mns_banner_font_size').val();
        if (fontSize && fontSize !== '16') {
            shortcode += ' font_size="' + fontSize + '"';
        }
        
        const fontColor = $('#mns_banner_font_color').val();
        if (fontColor && fontColor !== '#ffffff') {
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
        
        const itemSpacing = $('#mns_banner_item_spacing').val();
        if (itemSpacing && itemSpacing !== '15') {
            shortcode += ' item_spacing="' + itemSpacing + '"';
        }
        
        const shadow = $('#mns_banner_shadow').val();
        if (shadow && shadow !== 'light') {
            shortcode += ' shadow="' + shadow + '"';
        }
        
        // Behavior settings
        const maxCurrencies = $('#mns_banner_max_currencies').val();
        if (maxCurrencies && maxCurrencies !== '6') {
            shortcode += ' max_currencies="' + maxCurrencies + '"';
        }
        
        const enableHoverEffect = $('#mns_banner_enable_hover_effect').is(':checked');
        if (!enableHoverEffect) {
            shortcode += ' enable_hover_effect="no"';
        }
        
        shortcode += ']';
        $('#mns-generated-shortcode-modern').val(shortcode);
    }

    // Initial setup
    updateShortcodeModern();
});
</script>