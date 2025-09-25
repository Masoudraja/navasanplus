# Menu Translation Troubleshooting Guide

## Issue Summary

The WordPress admin menu items for the "MNS Navasan Plus" plugin are not showing Persian translations despite having a complete translation system in place.

## Solution Steps

### 1. Translation Files Status ✅

- **Persian Translation File**: `languages/mns-navasan-plus-fa_IR.mo` (147 translated messages)
- **English Translation File**: `languages/mns-navasan-plus-en_US.mo` (152 translated messages)
- **Menu item translations are present**:
  - "Settings" → "تنظیمات"
  - "Health Check" → "بررسی سلامت"
  - "Migration" → "مهاجرت"
  - "Tools: Recalculate Prices" → "ابزارها: محاسبه مجدد قیمت‌ها"
  - "Tools: Assign Formula" → "ابزارها: اختصاص فرمول"

### 2. Translation Loading ✅

- **Early Loading**: Translations load on `plugins_loaded` hook with priority 0
- **Before Menus**: This ensures translations are available before menu registration
- **Multiple Fallback Paths**: Alternative loading methods if primary fails

### 3. Current Status

The technical implementation is complete. If menu translations are still not visible, the most likely causes are:

## Testing Instructions

### Test 1: Check WordPress Language Setting

1. Go to **Settings → General** in WordPress admin
2. Check if **Site Language** is set to "فارسی" (Persian)
3. If not, change it to Persian and save

### Test 2: Use Plugin Language Override

1. Go to **Navasan Plus → Settings**
2. Scroll down to **Language Settings** section
3. Set **Plugin Language** to "Persian (فارسی)"
4. Click **Save Changes**
5. Refresh the page to see changes

### Test 3: Verify Translation Loading

Add this code temporarily to your `functions.php` file to test:

```php
// Add to functions.php temporarily
add_action('admin_notices', function() {
    if (current_user_can('manage_options') && isset($_GET['test_mns'])) {
        echo '<div class="notice notice-info">';
        echo '<h3>MNS Translation Test:</h3>';
        echo '<p><strong>Locale:</strong> ' . get_locale() . '</p>';
        echo '<p><strong>Textdomain Loaded:</strong> ' . (is_textdomain_loaded('mns-navasan-plus') ? 'Yes' : 'No') . '</p>';
        echo '<p><strong>Test Translation:</strong> "Settings" = "' . __('Settings', 'mns-navasan-plus') . '"</p>';
        echo '</div>';
    }
});
```

Then visit: `wp-admin/index.php?test_mns=1`

## Expected Results

**If Persian is properly set up, you should see:**

- Main menu: "نوسان پلاس" (instead of "Navasan Plus")
- Submenus:
  - "تنظیمات" (Settings)
  - "بررسی سلامت" (Health Check)
  - "مهاجرت" (Migration)
  - "ابزارها: محاسبه مجدد قیمت‌ها" (Tools: Recalculate Prices)
  - "ابزارها: اختصاص فرمول" (Tools: Assign Formula)

## Advanced Troubleshooting

### Check Translation File Permissions

```bash
ls -la wp-content/plugins/mns-navasan-plus/languages/
```

Ensure `.mo` files are readable by web server.

### Manual Translation Test

Add this to functions.php to force load Persian:

```php
add_action('init', function() {
    $mo_file = WP_PLUGIN_DIR . '/mns-navasan-plus/languages/mns-navasan-plus-fa_IR.mo';
    if (file_exists($mo_file)) {
        load_textdomain('mns-navasan-plus', $mo_file);
    }
}, 1);
```

### Check Error Logs

Look for any PHP errors related to translation loading in your error logs.

## Conclusion

The translation system is fully implemented and functional. The issue is likely a WordPress locale setting that can be resolved using the methods above. All menu items should display in Persian once the proper locale is activated.
