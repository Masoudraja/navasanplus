jQuery(function ($) {
  'use strict';

  // ---------- Helpers ----------
  // پیدا کردن بزرگ‌ترین ایندکس عددی موجود (مثل ...[3][field]) و برگرداندن next
  function nextIndex($wrap, itemSel) {
    var max = -1;
    $wrap.find(itemSel).each(function () {
      $(this).find('input[name], select[name], textarea[name]').each(function () {
        var name = $(this).attr('name') || '';
        // ...[<index>][field]  → آخرین index عددی را بردار
        var m = name.match(/\[([^\]]+)\](\[[^\]]+\])$/);
        if (m) {
          var n = parseInt(m[1], 10);
          if (!isNaN(n) && n > max) max = n;
        }
      });
    });
    // اگر هیچ index عددی نبود، از طول آیتم‌ها استفاده کن (0,1,2,...)
    return (max >= 0) ? (max + 1) : $wrap.find(itemSel).length;
  }

  // جایگزین‌کردن index در انتهای nameها با newIndex + ریست مقدارها
  function retargetNames($row, newIndex) {
    $row.find('input, select, textarea').each(function () {
      var $f = $(this);
      var name = $f.attr('name');
      if (name) {
        // ...[هرچی][field] => ...[newIndex][field]
        name = name.replace(/\[([^\]]+)\](\[[^\]]+\])$/, '[' + newIndex + ']$2');
        $f.attr('name', name);
      }
      if ($f.is(':checkbox,:radio')) {
        $f.prop('checked', false);
      } else if ($f.is('select')) {
        $f.prop('selectedIndex', 0);
      } else {
        $f.val('');
      }
    });
    // جلوگیری از تکرار id در DOM
    $row.find('[id]').removeAttr('id');
    // data-attributes اختیاری برای دیباگ
    $row.attr('data-index', newIndex);
    // اگر قبلاً data-code داشته، یک کُد پیش‌فرض هم‌راستا تولید کن (اختیاری)
    if ($row.attr('data-code') != null) {
      $row.attr('data-code', 'var_' + newIndex);
    }
  }

  // ---------- 1) متغیرهای فرمول: افزودن/حذف ----------
  $(document).on('click', '.add-formula-variable', function (e) {
    e.preventDefault();
    var $wrap  = $(this).closest('#mns-navasan-plus-formula-variables-container');
    var $items = $wrap.children('.mns-formula-variable');
    if (!$items.length) return; // باید حداقل یک الگو برای کلون داشته باشیم
    var $clone    = $items.last().clone(true, true);
    var newIndex  = nextIndex($wrap, '.mns-formula-variable');
    retargetNames($clone, newIndex);
    $wrap.append($clone);
  });

  $(document).on('click', '.remove-formula-variable', function (e) {
    e.preventDefault();
    var $wrap  = $(this).closest('#mns-navasan-plus-formula-variables-container');
    var $items = $wrap.children('.mns-formula-variable');
    if ($items.length > 1) {
      $(this).closest('.mns-formula-variable').remove();
    }
  });

  // ---------- 2) کامپوننت‌های فرمول: افزودن/حذف ----------
  $(document).on('click', '.add-formula-component', function (e) {
    e.preventDefault();
    var $wrap  = $(this).closest('#mns-navasan-plus-formula-components-container');
    var $items = $wrap.children('.mns-formula-component');
    if (!$items.length) return;
    var $clone   = $items.last().clone(true, true);
    var newIndex = nextIndex($wrap, '.mns-formula-component');

    $clone.find('input, select, textarea').each(function () {
      var $f = $(this);
      var name = $f.attr('name');
      if (name) {
        name = name.replace(/\[([^\]]+)\](\[[^\]]+\])$/, '[' + newIndex + ']$2');
        $f.attr('name', name);
      }
      if ($f.is('select')) {
        $f.prop('selectedIndex', 0);
      } else {
        $f.val('');
      }
    });
    $clone.find('[id]').removeAttr('id');
    $clone.attr('data-index', newIndex);
    $wrap.append($clone);
  });

  $(document).on('click', '.remove-formula-component', function (e) {
    e.preventDefault();
    var $wrap  = $(this).closest('#mns-navasan-plus-formula-components-container');
    var $items = $wrap.children('.mns-formula-component');
    if ($items.length > 1) {
      $(this).closest('.mns-formula-component').remove();
    }
  });

  // ---------- 3) Quick Edit: تخفیف سود/اجرت ----------
  if (typeof inlineEditPost !== 'undefined' && inlineEditPost && inlineEditPost.edit) {
    var wpInlineEdit = inlineEditPost.edit;
    inlineEditPost.edit = function (postId) {
      wpInlineEdit.apply(this, arguments);

      var id = 0;
      if (typeof postId === 'object') id = parseInt(this.getId(postId), 10);
      else id = parseInt(postId, 10);
      if (!(id > 0)) return;

      var $postRow = $('#post-' + id);
      var $editRow = $('#edit-' + id);

      var getNum = function (sel) {
        return ($postRow.find(sel).text() || '').trim().replace(/[^\d.\-]/g, '');
      };

      var profitPerc  = getNum('.column-discount-profit-percentage');
      var profitFixed = getNum('.column-discount-profit-fixed');
      var chargePerc  = getNum('.column-discount-charge-percentage');
      var chargeFixed = getNum('.column-discount-charge-fixed');

      $editRow.find('input.inline-edit-mns-discount-profit-percentage').val(profitPerc);
      $editRow.find('input.inline-edit-mns-discount-profit-fixed').val(profitFixed);
      $editRow.find('input.inline-edit-mns-discount-charge-percentage').val(chargePerc);
      $editRow.find('input.inline-edit-mns-discount-charge-fixed').val(chargeFixed);
    };
  }
});