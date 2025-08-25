/**
 * MNS Navasan Plus — persist.js
 *
 * وظیفه‌ها:
 * 1) «اتو‌سیو» مقادیر فیلدهای متاباکس‌های افزونه در localStorage تا قبل از ذخیره‌ی پست/محصول.
 * 2) همگام‌کردن شمارنده‌های تکرارشونده (variables/components) با تعداد آیتم‌های UI.
 *
 * نکته: فقط در صفحه‌های ویرایش پست/محصول (form#post) فعال شود.
 */
(function ($) {
  'use strict';

  // ---- helpers -------------------------------------------------------------

  function debounce(fn, wait) {
    var t;
    return function () {
      var ctx = this, args = arguments;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(ctx, args); }, wait || 200);
    };
  }

  function storageKey($form) {
    var postId   = $form.find('#post_ID').val() || '0';
    var postType = $form.find('input#post_type, input[name="post_type"]').val() || (window.pagenow || 'post');
    return 'mnsnp:autosave:' + postType + ':' + postId;
  }

  function readStore(key) {
    try {
      var raw = window.localStorage.getItem(key);
      return raw ? JSON.parse(raw) : {};
    } catch (e) { return {}; }
  }
  function writeStore(key, obj) {
    try { window.localStorage.setItem(key, JSON.stringify(obj || {})); } catch (e) {}
  }
  function clearStore(key) {
    try { window.localStorage.removeItem(key); } catch (e) {}
  }

  // فقط فیلدهای افزونه داخل فرم ویرایش
  function $pluginFields($root) {
    return $root.find(
      'input[name^="mns_navasan_plus"], input[name^="_mns_navasan_plus"],' +
      'select[name^="mns_navasan_plus"], select[name^="_mns_navasan_plus"],' +
      'textarea[name^="mns_navasan_plus"], textarea[name^="_mns_navasan_plus"]'
    );
  }

  function getFieldValue($el) {
    var type = ($el.attr('type') || '').toLowerCase();
    if (type === 'checkbox')  return $el.prop('checked') ? '1' : '';
    if (type === 'radio')     return $el.prop('checked') ? $el.val() : null;
    return $el.val();
  }
  function setFieldValue($el, val) {
    var type = ($el.attr('type') || '').toLowerCase();
    if (type === 'checkbox') {
      $el.prop('checked', val === '1' || val === true).trigger('change');
      return;
    }
    if (type === 'radio') {
      $('input[type="radio"][name="' + $el.attr('name') + '"]')
        .each(function () { $(this).prop('checked', $(this).val() === val); })
        .trigger('change');
      return;
    }
    $el.val(val).trigger('change');
  }

  // ---- init only on post edit ------------------------------------------------
  $(function () {
    var $form = $('form#post');
    if (!$form.length || !$('#post_ID').length) {
      // بیرون از صفحهٔ ویرایش پست/محصول: کاملاً غیرفعال شو (برای جلوگیری از ذخیرهٔ ناخواسته روی صفحات تنظیمات)
      return;
    }

    var key = storageKey($form);

    // ---- restore ------------------------------------------------------------
    (function restoreInputs() {
      var data = readStore(key);
      if (!data || !Object.keys(data).length) return;

      $pluginFields($form).each(function () {
        var $el = $(this), name = $el.attr('name');
        if (!name || !(name in data)) return;
        setFieldValue($el, data[name]);
      });
    })();

    // ---- persist on change ---------------------------------------------------
    var persistInputs = debounce(function () {
      var bag = readStore(key);
      $pluginFields($form).each(function () {
        var $el = $(this), name = $el.attr('name');
        if (!name) return;
        var val = getFieldValue($el);
        if (val === null) return; // رادیوی انتخاب‌نشده
        bag[name] = val;
      });
      writeStore(key, bag);
    }, 250);

    $form.on('change input', 'input, select, textarea', function () {
      var name = $(this).attr('name') || '';
      if (name.indexOf('mns_navasan_plus') !== -1 || name.indexOf('_mns_navasan_plus') !== -1) {
        persistInputs();
      }
    });

    // پاک‌سازی بعد از submit (فقط وقتی پست ذخیره می‌شود)
    $form.on('submit', function () { clearStore(key); });

    // ---- counters sync (variables/components) --------------------------------
    (function ensureCounters() {
      // ورودی‌های hidden باید داخل فرم باشند تا POST شوند
      var $varsHidden  = $form.find('input[name="_mns_navasan_plus_formula_variables_counter"]');
      var $compsHidden = $form.find('input[name="_mns_navasan_plus_formula_components_counter"]');

      if (!$varsHidden.length) {
        $varsHidden = $('<input type="hidden" name="_mns_navasan_plus_formula_variables_counter" />').appendTo($form);
      }
      if (!$compsHidden.length) {
        $compsHidden = $('<input type="hidden" name="_mns_navasan_plus_formula_components_counter" />').appendTo($form);
      }

      function sync() {
        var varsCount  = $('#mns-navasan-plus-formula-variables-container .mns-formula-variable').length || 0;
        var compsCount = $('#mns-navasan-plus-formula-components-container .mns-formula-component').length || 0;
        $varsHidden.val(varsCount || 1);
        $compsHidden.val(compsCount || 1);
      }

      sync();

      // رویدادهای افزودن/حذف در admin.js
      $(document).on(
        'click',
        '.add-formula-variable, .remove-formula-variable, .add-formula-component, .remove-formula-component',
        debounce(sync, 50)
      );
    })();
  });

})(jQuery);