jQuery(function ($) {
  "use strict";

  // ---------------------------------------
  // Helpers
  // ---------------------------------------
  function getVarsWrap() {
    return $("#mns-navasan-plus-formula-variables-container");
  }
  function getCompsWrap() {
    return $("#mns-navasan-plus-formula-components-container");
  }
  function getCurrencies() {
    const $wrap = getVarsWrap();
    const data = $wrap.data("currencies");
    if (Array.isArray(data)) return data;
    try {
      return JSON.parse(String(data || "[]"));
    } catch (e) {
      return [];
    }
  }

  function getRowCode($row) {
    var code = $row.attr("data-code");
    if (code) return code;
    var $any = $row
      .find('[name*="_mns_navasan_plus_formula_variables["]')
      .first();
    if ($any.length) {
      var m = ($any.attr("name") || "").match(
        /_mns_navasan_plus_formula_variables\[([^\]]+)\]/
      );
      if (m) return m[1];
    }
    return "v_0";
  }

  function rxEscape(s) {
    return String(s).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  }

  function replaceCodeInRow($row, oldCode, newCode) {
    if (!newCode || newCode === oldCode) return;
    $row.find("[name],[id]").each(function () {
      var $el = $(this);
      var nm = $el.attr("name");
      if (nm) {
        // [oldCode] → [newCode]
        nm = nm.replace(
          new RegExp("\\[" + rxEscape(oldCode) + "\\]", "g"),
          "[" + newCode + "]"
        );
        $el.attr("name", nm);
      }
      var id = $el.attr("id");
      if (id) {
        // _oldCode_ → _newCode_
        id = id.replace(
          new RegExp("_" + rxEscape(oldCode) + "_", "g"),
          "_" + newCode + "_"
        );
        // [oldCode] → [newCode] (برای idهایی که مثل name براکت دارند)
        id = id.replace(
          new RegExp("\\[" + rxEscape(oldCode) + "\\]", "g"),
          "[" + newCode + "]"
        );
        $el.attr("id", id);
      }
    });

    $row.find(".mns-var-code").text(newCode);
    $row
      .find(".mns-copy-code, .mns-insert-code")
      .attr("data-code", newCode)
      .data("code", newCode);

    $row.attr("data-code", newCode);
  }

  // تولید کُد یکتا
  function makeCode() {
    return (
      "v_" +
      (Date.now().toString(36) + Math.floor(Math.random() * 999).toString(36))
    );
  }

  // ست‌کردن/یکتا کردن data-code برای همه ردیف‌های موجود
  function initRowCodes() {
    var seen = new Set();
    getVarsWrap()
      .children(".mns-formula-variable")
      .each(function () {
        var $row = $(this);
        var old = getRowCode($row);

        // اگر خالی/پیش‌فرض/تکراری بود → کُد جدید بده
        if (!old || old === "v_0" || seen.has(old)) {
          var fresh = makeCode();
          replaceCodeInRow($row, old || "v_0", fresh);
          seen.add(fresh);
        } else {
          $row.attr("data-code", old);
          $row.find(".mns-var-code").text(old);
          $row
            .find(".mns-copy-code, .mns-insert-code")
            .attr("data-code", old)
            .data("code", old);
          seen.add(old);
        }
      });
  }

  // next index برای کامپوننت‌ها (عددیه)
  function nextIndex($wrap, itemSel) {
    var max = -1;
    $wrap.find(itemSel).each(function () {
      $(this)
        .find("input[name], select[name], textarea[name]")
        .each(function () {
          var name = $(this).attr("name") || "";
          var m = name.match(/\[(\d+)\](?=\[[^\]]+\]$)/);
          if (m) {
            var n = parseInt(m[1], 10);
            if (!isNaN(n) && n > max) max = n;
          }
        });
    });
    return max >= 0 ? max + 1 : $wrap.find(itemSel).length;
  }

  // قفل/بازکردن و پرکردن خودکار برای currency
  function refreshVarRowUI($row) {
    var type = $row.find('select[id$="_type"]').val() || "custom";
    var isCurrency = type === "currency";

    var $unit = $row.find('input[id$="_unit"]');
    var $unitSym = $row.find('input[id$="_unit_symbol"]');
    var $val = $row.find('input[id$="_value"]');
    var $valSym = $row.find('input[id$="_value_symbol"]');
    var $currSel = $row.find('select[id$="_currency_id"]');
    var $rateText = $row.find(".mns-curr-rate");

    $row.find(".mns-if-currency")[isCurrency ? "show" : "hide"]();

    if (isCurrency) {
      $unit.attr("readonly", "readonly");
      // $unitSym.attr('readonly', 'readonly'); // Remove readonly for unit symbol
      $val.removeAttr("readonly");
      $valSym.removeAttr("readonly");

      var $opt = $currSel.find("option:selected");
      var cid = ($currSel.val() || "").toString();
      var rate = parseFloat($opt.data("rate"));
      var sym = $opt.data("symbol");

      if (isNaN(rate) || !isFinite(rate) || sym === undefined || sym === null) {
        var list = getCurrencies();
        for (var i = 0; i < list.length; i++) {
          if (String(list[i].id) === cid) {
            if (isNaN(rate) || !isFinite(rate)) rate = parseFloat(list[i].rate);
            if (sym === undefined || sym === null) sym = list[i].symbol || "";
            break;
          }
        }
      }

      if (!isNaN(rate) && isFinite(rate)) $unit.val(rate);
      if (typeof sym === "string") $unitSym.val(sym);

      if (!$val.val()) $val.val("1");
      if (!$valSym.val() && typeof sym === "string") $valSym.val(sym);

      if ($rateText.length) {
        var txt =
          !isNaN(rate) && isFinite(rate)
            ? rate.toLocaleString(undefined, { maximumFractionDigits: 4 })
            : "—";
        if (sym) txt += " " + sym;
        $rateText.text(txt);
      }
    } else {
      $unit.removeAttr("readonly");
      $unitSym.removeAttr("readonly");
      $val.removeAttr("readonly");
      $valSym.removeAttr("readonly");
    }
  }

  // ساخت ردیف جدید با preset
  function addVarRow(kind, preset) {
    var $wrap = getVarsWrap();
    var $items = $wrap.children(".mns-formula-variable");
    if (!$items.length) return;

    var $clone = $items.last().clone(true, true);
    var oldCode = getRowCode($items.last());
    var newCode = makeCode();

    replaceCodeInRow($clone, oldCode, newCode);

    // پاکسازی
    $clone.find("input, select, textarea").each(function () {
      var $f = $(this);
      if ($f.is(":checkbox,:radio")) $f.prop("checked", false);
      else if ($f.is("select")) $f.prop("selectedIndex", 0);
      else $f.val("");
    });

    var $type = $clone.find('select[id$="_type"]');
    if ($type.length) $type.val(kind === "currency" ? "currency" : "custom");

    if (kind === "currency" && preset) {
      var $cid = $clone.find('select[id$="_currency_id"]');
      if ($cid.length && preset.currency_id)
        $cid.val(String(preset.currency_id));
      if (preset.name) $clone.find('input[id$="_name"]').val(preset.name);

      if (typeof preset.unit !== "undefined") {
        $clone
          .find('input[id$="_unit"]')
          .val(preset.unit)
          .attr("readonly", "readonly");
      }
      if (preset.unit_symbol) {
        $clone.find('input[id$="_unit_symbol"]').val(preset.unit_symbol);
        // Remove readonly: .attr("readonly", "readonly");
      }

      var $v = $clone.find('input[id$="_value"]');
      if (!$v.val()) $v.val("1");

      var $vs = $clone.find('input[id$="_value_symbol"]');
      if (!$vs.val() && preset.unit_symbol) $vs.val(preset.unit_symbol);
    }

    var $ctr = $('input[name="_mns_navasan_plus_formula_variables_counter"]');
    if ($ctr.length) {
      var n = parseInt($ctr.val() || "0", 10);
      if (!isNaN(n)) $ctr.val(n + 1);
    }

    $wrap.append($clone);
    refreshVarRowUI($clone);
    initRowCodes(); // ← تضمین یکتا بودن
    triggerRecalcAll();
    return $clone;
  }

  // پاپ‌آپ انتخاب ارز
  function openCurrencyPicker(onPick) {
    var currencies = getCurrencies();
    if (!currencies.length) {
      alert("No currencies found.");
      return;
    }
    var html = `
  <div class="mnsnp-modal-backdrop" style="position:fixed;inset:0;background:rgba(0,0,0,.25);z-index:9999;"></div>
  <div class="mnsnp-modal" style="position:fixed;left:50%;top:30%;transform:translateX(-50%);background:#fff;border:1px solid #ccd0d4;border-radius:6px;box-shadow:0 10px 30px rgba(0,0,0,.2);width:420px;z-index:10000;">
    <div style="padding:14px 16px;border-bottom:1px solid #eee;font-weight:600;">Add Currency Variable</div>
    <div style="padding:16px;">
      <label style="display:block;margin-bottom:6px;">Select Currency</label>
      <select class="mnsnp-curr-select" style="width:100%;">
        <option value="0">— select —</option>
        ${currencies
          .map(
            (c) =>
              `<option value="${c.id}" data-rate="${c.rate}" data-symbol="${
                c.symbol || ""
              }">${c.label || "#" + c.id}</option>`
          )
          .join("")}
      </select>
    </div>
    <div style="padding:12px 16px;border-top:1px solid #eee;text-align:left;">
      <button class="button button-primary mnsnp-curr-choose">Add</button>
      <button class="button mnsnp-curr-cancel" style="margin-inline-start:6px;">Cancel</button>
    </div>
  </div>`;
    var $wrap = $('<div class="mnsnp-curr-picker-wrap"></div>')
      .appendTo(document.body)
      .html(html);
    $wrap.on(
      "click",
      ".mnsnp-curr-cancel, .mnsnp-modal-backdrop",
      function (e) {
        e.preventDefault();
        $wrap.remove();
      }
    );
    $wrap.on("click", ".mnsnp-curr-choose", function (e) {
      e.preventDefault();
      var $opt = $wrap.find(".mnsnp-curr-select option:selected");
      var cid = $opt.val();
      if (cid && cid !== "0") {
        var rate = parseFloat($opt.data("rate")) || 0;
        var sym = $opt.data("symbol") || "";
        var lbl = $opt.text();
        onPick({ id: cid, name: lbl, rate: rate, symbol: sym });
        $wrap.remove();
      }
    });
  }

  // ---------------------------------------
  // Variables: Add / Remove
  // ---------------------------------------
  $(document).on("click", ".add-formula-variable", function (e) {
    e.preventDefault();
    var kind = $(this).data("kind") || "custom";
    if (kind === "currency") {
      openCurrencyPicker(function (cur) {
        addVarRow("currency", {
          currency_id: cur.id,
          name: cur.name,
          unit: cur.rate,
          unit_symbol: cur.symbol,
        });
      });
    } else {
      addVarRow("custom", {});
    }
  });

  $(document).on("click", ".remove-formula-variable", function (e) {
    e.preventDefault();
    var $wrap = getVarsWrap();
    var $items = $wrap.children(".mns-formula-variable");
    if ($items.length > 1) {
      $(this).closest(".mns-formula-variable").remove();
      triggerRecalcAll();
    } else {
      var $row = $items.eq(0);
      $row.find("input, select, textarea").each(function () {
        var $f = $(this);
        if ($f.is(":checkbox,:radio")) $f.prop("checked", false);
        else if ($f.is("select")) $f.prop("selectedIndex", 0);
        else $f.val("");
      });
      refreshVarRowUI($row);
      initRowCodes();
      triggerRecalcAll();
    }
  });

  // تغییر نوع و ارز
  $(document).on(
    "change",
    '#mns-navasan-plus-formula-variables-container select[id$="_type"]',
    function () {
      refreshVarRowUI($(this).closest(".mns-formula-variable"));
      triggerRecalcAll();
    }
  );
  $(document).on(
    "change",
    '#mns-navasan-plus-formula-variables-container select[id$="_currency_id"]',
    function () {
      var $row = $(this).closest(".mns-formula-variable");
      refreshVarRowUI($row);
      triggerRecalcAll();
    }
  );

  // ---------------------------------------
  // Copy & Insert code
  // ---------------------------------------
  function insertAtCaret($ta, text) {
    var el = $ta.get(0);
    if (!el) return;
    el.focus();
    if (typeof el.selectionStart === "number") {
      var start = el.selectionStart,
        end = el.selectionEnd;
      var val = el.value;
      el.value = val.slice(0, start) + text + val.slice(end);
      el.selectionStart = el.selectionEnd = start + text.length;
    } else if (document.selection && document.selection.createRange) {
      el.focus();
      var range = document.selection.createRange();
      range.text = text;
    } else {
      el.value += text;
    }
    $ta.trigger("input").trigger("change");
  }

  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText)
      return navigator.clipboard.writeText(text);
    var $t = $(
      '<textarea style="position:fixed;left:-9999px;top:-9999px;"></textarea>'
    )
      .val(text)
      .appendTo("body");
    $t[0].select();
    try {
      document.execCommand("copy");
    } catch (e) {}
    $t.remove();
    return Promise.resolve();
  }

  $(document).on("click", ".mns-copy-code", function (e) {
    e.preventDefault();
    var code = $(this).attr("data-code") || $(this).data("code") || "";
    if (!code) return;
    copyText(code).then(() => $(e.currentTarget).blur());
  });

  $(document).on("click", ".mns-insert-code", function (e) {
    e.preventDefault();
    var $btn = $(this);
    var code = $btn.attr("data-code") || $btn.data("code") || "";
    var target = $btn.data("target") || $btn.attr("data-target") || "";
    if (!code || !target) return;
    var $ta = $(target);
    if (!$ta.length) return;
    insertAtCaret($ta, code);
  });

  // ---------------------------------------
  // Realtime Totals (Components + Global Expression + Live Row)
  // ---------------------------------------
  var parser =
    window.FormulaParser && FormulaParser.Parser
      ? new FormulaParser.Parser()
      : null;

  function buildVarsMap() {
    var vars = {};
    getVarsWrap()
      .children(".mns-formula-variable")
      .each(function () {
        var $row = $(this);
        var code = getRowCode($row);
        // unit * value  (برای currency اگر value خالی باشد، در refreshVarRowUI → 1 می‌گذاریم)
        var unit = parseFloat($row.find('input[id$="_unit"]').val() || "0");
        var val = parseFloat($row.find('input[id$="_value"]').val() || "0");
        var num = (isFinite(unit) ? unit : 0) * (isFinite(val) ? val : 0);
        if (!isFinite(num)) num = 0;
        vars[code] = num;
      });
    return vars;
  }

  function evalExpr(expr, vars) {
    if (!parser || !expr) return null;
    try {
      var out = parser.evaluate(expr, vars || {});
      return isFinite(out) ? out : null;
    } catch (e) {
      return null;
    }
  }

  // محاسبهٔ ردیف‌های کامپوننت
  function recalcComponents() {
    var vars = buildVarsMap();
    getCompsWrap()
      .children(".mns-formula-component")
      .each(function () {
        var $row = $(this);
        var $exp = $row
          .find(
            '.mns-component-expression, [id$="_expression"], [name$="[expression]"], [name$="[text]"]'
          )
          .first();
        var $out = $row.find(".mns-component-total").first();
        if (!$exp.length || !$out.length) return;
        var v = evalExpr($exp.val(), vars);
        $out.text(
          v === null
            ? "—"
            : v.toLocaleString(undefined, { maximumFractionDigits: 4 })
        );
        // اگر span نماد داشته باشیم، همگام‌سازی‌اش
        var $symInput = $row.find('input[name$="[symbol]"]').first();
        var $symSpan = $row.find(".mns-component-total-symbol").first();
        if ($symSpan.length)
          $symSpan.text(
            $symInput.length && $symInput.val() ? " " + $symInput.val() : ""
          );
      });
  }

  // محاسبهٔ فرمول کل (سازگار با UI فعلی: .mns-formula-live-row)
  function recalcFormulaLiveRow() {
    var $ta = $("#mns_navasan_plus_formula_expression");
    var $out = $(".mns-formula-live-row .mns-formula-total");
    var $err = $(".mns-formula-live-row .mns-formula-error");

    if (!$ta.length || !$out.length) return;

    var expr = String($ta.val() || "").trim();
    if (!expr) {
      $out.text("—");
      if ($err.length) $err.hide().text("");
      return;
    }
    var vars = buildVarsMap();
    try {
      var result = parser ? parser.evaluate(expr, vars) : null;
      if (typeof result === "number" && isFinite(result)) {
        $out.text(
          result.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          })
        );
        if ($err.length) $err.hide().text("");
      } else {
        $out.text("—");
      }
    } catch (e) {
      $out.text("—");
      if ($err.length)
        $err.text(e && e.message ? e.message : "Parse error").show();
    }
  }

  // (سازگار با انتخاب‌گرهای عمومی قبلی اگر جایی وجود داشت)
  function recalcGlobalFallback() {
    var vars = buildVarsMap();
    var $exp = $(
      '.mns-formula-expression textarea, [id$="_formula_expression"], [name$="[formula_expression]"]'
    ).first();
    var $out = $(".mns-formula-expression-total").first();
    if (!$exp.length || !$out.length) return;
    var v = evalExpr($exp.val(), vars);
    $out.text(
      v === null
        ? "—"
        : v.toLocaleString(undefined, { maximumFractionDigits: 4 })
    );
  }

  var recalcTimer = null;
  function triggerRecalcAll() {
    if (!parser) return;
    clearTimeout(recalcTimer);
    recalcTimer = setTimeout(function () {
      recalcComponents();
      recalcFormulaLiveRow();
      recalcGlobalFallback();
    }, 150);
  }

  // تغییر روی ورودی‌های متغیرها
  $(document).on(
    "input change",
    "#mns-navasan-plus-formula-variables-container input, #mns-navasan-plus-formula-variables-container select, #mns-navasan-plus-formula-variables-container textarea",
    triggerRecalcAll
  );

  // تغییر روی اکسپرشن هر کامپوننت
  $(document).on(
    "input change",
    '#mns-navasan-plus-formula-components-container .mns-component-expression, #mns-navasan-plus-formula-components-container [id$="_expression"], #mns-navasan-plus-formula-components-container [name$="[expression]"]',
    triggerRecalcAll
  );

  // تغییر روی اکسپرشن کل
  $(document).on(
    "input change",
    "#mns_navasan_plus_formula_expression",
    triggerRecalcAll
  );
  $(document).on(
    "input change",
    '.mns-formula-expression textarea, [id$="_formula_expression"], [name$="[formula_expression]"]',
    triggerRecalcAll
  );

  // ---------------------------------------
  // Components: Add / Remove
  // ---------------------------------------
  $(document).on("click", ".add-formula-component", function (e) {
    e.preventDefault();
    var $wrap = getCompsWrap();
    var $items = $wrap.children(".mns-formula-component");
    if (!$items.length) return;

    var $clone = $items.last().clone(true, true);
    var newIndex = nextIndex($wrap, ".mns-formula-component");

    $clone.find("input, select, textarea").each(function () {
      var $f = $(this);
      var name = $f.attr("name");
      if (name) {
        name = name.replace(/\[(\d+)\](?=\[[^\]]+\]$)/, "[" + newIndex + "]");
        $f.attr("name", name);
      }
      if ($f.is("select")) $f.prop("selectedIndex", 0);
      else $f.val("");
    });
    $clone.find("[id]").removeAttr("id");
    $clone.attr("data-index", newIndex);
    $wrap.append($clone);
    triggerRecalcAll();
  });

  $(document).on("click", ".remove-formula-component", function (e) {
    e.preventDefault();
    var $wrap = getCompsWrap();
    var $items = $wrap.children(".mns-formula-component");
    if ($items.length > 1) {
      $(this).closest(".mns-formula-component").remove();
      triggerRecalcAll();
    }
  });

  // ---------------------------------------
  // Quick Edit (ستون‌های تخفیف)
  // ---------------------------------------
  if (
    typeof inlineEditPost !== "undefined" &&
    inlineEditPost &&
    inlineEditPost.edit
  ) {
    var wpInlineEdit = inlineEditPost.edit;
    inlineEditPost.edit = function (postId) {
      wpInlineEdit.apply(this, arguments);

      var id = 0;
      if (typeof postId === "object") id = parseInt(this.getId(postId), 10);
      else id = parseInt(postId, 10);
      if (!(id > 0)) return;

      var $postRow = $("#post-" + id);
      var $editRow = $("#edit-" + id);

      var getNum = function (sel) {
        return ($postRow.find(sel).text() || "")
          .trim()
          .replace(/[^\d.\-]/g, "");
      };

      var profitPerc = getNum(".column-discount-profit-percentage");
      var profitFixed = getNum(".column-discount-profit-fixed");
      var chargePerc = getNum(".column-discount-charge-percentage");
      var chargeFixed = getNum(".column-discount-charge-fixed");

      $editRow
        .find("input.inline-edit-mns-discount-profit-percentage")
        .val(profitPerc);
      $editRow
        .find("input.inline-edit-mns-discount-profit-fixed")
        .val(profitFixed);
      $editRow
        .find("input.inline-edit-mns-discount-charge-percentage")
        .val(chargePerc);
      $editRow
        .find("input.inline-edit-mns-discount-charge-fixed")
        .val(chargeFixed);
    };
  }

  // ---------- Init: یکتاسازی کُدها + ست‌کردن UI + محاسبه‌ی اولیه ----------
  initRowCodes();
  getVarsWrap()
    .children(".mns-formula-variable")
    .each(function () {
      refreshVarRowUI($(this));
    });
  triggerRecalcAll();
});
