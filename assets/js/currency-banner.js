/**
 * MNS Navasan Plus - Currency Banner JavaScript
 * Handles real-time updates, animations, and user interactions
 */

(function ($) {
  "use strict";

  class CurrencyBanner {
    constructor(element) {
      this.banner = $(element);
      this.bannerId = this.banner.attr("id");
      this.currencyIds = this.getCurrencyIds();
      this.autoRefreshInterval =
        parseInt(this.banner.data("auto-refresh")) || 0;
      this.showChange = this.banner.data("show-change") === "yes";
      this.showTime = this.banner.data("show-time") === "yes";
      this.showSymbol = this.banner.data("show-symbol") === "yes";
      this.refreshTimer = null;
      this.lastRates = {};
      this.isLoading = false;

      this.init();
    }

    init() {
      this.setupEventListeners();
      this.startAutoRefresh();
      this.animateOnLoad();

      // Initial rate storage for change detection
      this.storeCurrentRates();
    }

    getCurrencyIds() {
      const ids = [];
      this.banner.find(".mns-currency-item").each(function () {
        const id = $(this).data("currency-id");
        if (id) ids.push(id);
      });
      return ids;
    }

    setupEventListeners() {
      // Hover effects
      this.banner.on(
        "mouseenter",
        ".mns-currency-item",
        this.handleItemHover.bind(this)
      );
      this.banner.on(
        "mouseleave",
        ".mns-currency-item",
        this.handleItemLeave.bind(this)
      );

      // Click events for potential future features
      this.banner.on(
        "click",
        ".mns-currency-item",
        this.handleItemClick.bind(this)
      );

      // Visibility API for pausing updates when tab is hidden
      if (typeof document.hidden !== "undefined") {
        document.addEventListener(
          "visibilitychange",
          this.handleVisibilityChange.bind(this)
        );
      }

      // Window focus/blur events
      $(window).on("focus", this.handleWindowFocus.bind(this));
      $(window).on("blur", this.handleWindowBlur.bind(this));
    }

    handleItemHover(event) {
      const $item = $(event.currentTarget);
      $item.addClass("mns-item-hover");
    }

    handleItemLeave(event) {
      const $item = $(event.currentTarget);
      $item.removeClass("mns-item-hover");
    }

    handleItemClick(event) {
      const $item = $(event.currentTarget);
      const currencyId = $item.data("currency-id");

      // Trigger custom event for potential integrations
      this.banner.trigger("currencyClick", {
        currencyId: currencyId,
        item: $item,
      });
    }

    handleVisibilityChange() {
      if (document.hidden) {
        this.pauseRefresh();
      } else {
        this.resumeRefresh();
      }
    }

    handleWindowFocus() {
      this.resumeRefresh();
    }

    handleWindowBlur() {
      this.pauseRefresh();
    }

    startAutoRefresh() {
      if (this.autoRefreshInterval > 0) {
        this.refreshTimer = setInterval(() => {
          this.refreshRates();
        }, this.autoRefreshInterval * 1000);
      }
    }

    pauseRefresh() {
      if (this.refreshTimer) {
        clearInterval(this.refreshTimer);
        this.refreshTimer = null;
      }
    }

    resumeRefresh() {
      if (this.autoRefreshInterval > 0 && !this.refreshTimer) {
        this.startAutoRefresh();
      }
    }

    refreshRates() {
      if (this.isLoading || this.currencyIds.length === 0) {
        return;
      }

      this.isLoading = true;
      this.banner.addClass("loading");

      $.ajax({
        url: mnsCurrencyBanner.ajaxUrl,
        type: "POST",
        dataType: "json",
        data: {
          action: "mns_get_currency_rates",
          nonce: mnsCurrencyBanner.nonce,
          currency_ids: this.currencyIds.join(","),
        },
        success: (response) => {
          if (response.success && response.data) {
            this.updateRates(response.data.rates);
            if (this.showTime && response.data.timestamp) {
              this.updateTimestamp(response.data.timestamp);
            }
          }
        },
        error: (xhr, status, error) => {
          console.warn("Currency banner update failed:", error);
          this.showError();
        },
        complete: () => {
          this.isLoading = false;
          this.banner.removeClass("loading");
        },
      });
    }

    updateRates(rates) {
      for (const [currencyId, data] of Object.entries(rates)) {
        const $item = this.banner.find(`[data-currency-id="${currencyId}"]`);
        if ($item.length) {
          this.updateCurrencyItem($item, data);
        }
      }
    }

    updateCurrencyItem($item, data) {
      const $priceValue = $item.find(".mns-price-value");
      const $changeValue = $item.find(".mns-change-value");
      const $changeContainer = $item.find(".mns-price-change");

      const oldRate = parseFloat($priceValue.data("rate")) || 0;
      const newRate = parseFloat(data.rate) || 0;

      // Update rate data attribute
      $priceValue.data("rate", newRate);

      // Choose display value based on symbol setting
      const displayValue = this.showSymbol
        ? data.display_rate
        : parseFloat(data.rate).toFixed(0);

      // Animate price change
      this.animatePriceUpdate($priceValue, displayValue, oldRate !== newRate);

      // Update change percentage
      if (this.showChange && $changeValue.length) {
        const changeClass =
          data.change_pct > 0
            ? "positive"
            : data.change_pct < 0
            ? "negative"
            : "neutral";

        $changeContainer
          .removeClass(
            "mns-change-positive mns-change-negative mns-change-neutral"
          )
          .addClass(`mns-change-${changeClass}`);

        const changeSymbol = data.change_pct > 0 ? "+" : "";
        $changeValue.text(`${changeSymbol}${data.change_pct.toFixed(2)}%`);
      }

      // Flash effect for rate changes
      if (oldRate !== newRate && oldRate > 0) {
        this.flashPriceChange($item, newRate > oldRate);
      }
    }

    animatePriceUpdate($element, newValue, hasChanged) {
      if (!hasChanged) {
        $element.text(newValue);
        return;
      }

      // Counter animation for smooth number transitions
      const $counter = $element;
      const currentText = $counter.text();

      // Add transition class
      $counter.addClass("mns-price-updating");

      setTimeout(() => {
        $counter.text(newValue);
        setTimeout(() => {
          $counter.removeClass("mns-price-updating");
        }, 300);
      }, 150);
    }

    flashPriceChange($item, isIncrease) {
      const flashClass = isIncrease ? "mns-flash-up" : "mns-flash-down";

      $item.addClass(flashClass);
      setTimeout(() => {
        $item.removeClass(flashClass);
      }, 1000);
    }

    updateTimestamp(timestamp) {
      const $timeValue = this.banner.find(".mns-time-value");
      if ($timeValue.length) {
        $timeValue.fadeOut(200, function () {
          $(this).text(timestamp).fadeIn(200);
        });
      }
    }

    showError() {
      const $container = this.banner.find(".mns-banner-container");
      const errorHtml = `<div class="mns-banner-error-overlay">
                <span class="mns-error-icon">⚠</span>
                <span class="mns-error-text">${mnsCurrencyBanner.i18n.error}</span>
            </div>`;

      $container.append(errorHtml);

      setTimeout(() => {
        this.banner.find(".mns-banner-error-overlay").fadeOut(300, function () {
          $(this).remove();
        });
      }, 3000);
    }

    animateOnLoad() {
      const animationType = this.getAnimationType();

      if (animationType === "none") return;

      this.banner.find(".mns-currency-item").each(function (index) {
        const $item = $(this);
        setTimeout(() => {
          $item.addClass("mns-animate-in");
        }, index * 100);
      });
    }

    getAnimationType() {
      if (this.banner.hasClass("mns-banner-anim-slide")) return "slide";
      if (this.banner.hasClass("mns-banner-anim-fade")) return "fade";
      return "none";
    }

    storeCurrentRates() {
      this.banner.find(".mns-currency-item").each((index, element) => {
        const $item = $(element);
        const currencyId = $item.data("currency-id");
        const rate =
          parseFloat($item.find(".mns-price-value").data("rate")) || 0;
        this.lastRates[currencyId] = rate;
      });
    }

    destroy() {
      this.pauseRefresh();
      this.banner.off();
      $(window).off("focus blur", this.handleWindowFocus);
      if (typeof document.hidden !== "undefined") {
        document.removeEventListener(
          "visibilitychange",
          this.handleVisibilityChange
        );
      }
    }
  }

  // Enhanced CSS animations via JavaScript
  const style = document.createElement("style");
  style.textContent = `
        .mns-price-updating {
            transform: scale(1.05);
            transition: all 0.3s ease;
        }
        
        .mns-flash-up {
            background-color: rgba(34, 197, 94, 0.2) !important;
            transition: background-color 1s ease;
        }
        
        .mns-flash-down {
            background-color: rgba(239, 68, 68, 0.2) !important;
            transition: background-color 1s ease;
        }
        
        .mns-item-hover {
            transform: translateY(-3px) !important;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
        }
        
        .mns-animate-in {
            animation-play-state: running !important;
        }
        
        .mns-banner-error-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(239, 68, 68, 0.95);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 500;
            z-index: 100;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .mns-error-icon {
            font-size: 16px;
        }
    `;
  document.head.appendChild(style);

  // Utility functions
  window.MNSCurrencyBanner = {
    instances: new Map(),

    init: function () {
      $(".mns-currency-banner").each(function () {
        const bannerId = $(this).attr("id");
        if (bannerId && !MNSCurrencyBanner.instances.has(bannerId)) {
          const instance = new CurrencyBanner(this);
          MNSCurrencyBanner.instances.set(bannerId, instance);
        }
      });
    },

    refresh: function (bannerId = null) {
      if (bannerId) {
        const instance = MNSCurrencyBanner.instances.get(bannerId);
        if (instance) {
          instance.refreshRates();
        }
      } else {
        MNSCurrencyBanner.instances.forEach((instance) => {
          instance.refreshRates();
        });
      }
    },

    destroy: function (bannerId = null) {
      if (bannerId) {
        const instance = MNSCurrencyBanner.instances.get(bannerId);
        if (instance) {
          instance.destroy();
          MNSCurrencyBanner.instances.delete(bannerId);
        }
      } else {
        MNSCurrencyBanner.instances.forEach((instance, id) => {
          instance.destroy();
        });
        MNSCurrencyBanner.instances.clear();
      }
    },
  };

  // Initialize on document ready
  $(document).ready(function () {
    MNSCurrencyBanner.init();

    // Re-initialize when new content is added (e.g., AJAX)
    $(document).on("DOMNodeInserted", function (e) {
      if (
        $(e.target).hasClass("mns-currency-banner") ||
        $(e.target).find(".mns-currency-banner").length
      ) {
        setTimeout(() => {
          MNSCurrencyBanner.init();
        }, 100);
      }
    });

    // Handle Woodmart theme header builder
    if (
      typeof woodmart !== "undefined" ||
      $("body").hasClass("woodmart-theme")
    ) {
      // Initialize after a short delay to ensure header is loaded
      setTimeout(() => {
        MNSCurrencyBanner.init();
      }, 500);

      // Re-initialize on window load
      $(window).on("load", function () {
        setTimeout(() => {
          MNSCurrencyBanner.init();
        }, 100);
      });
    }

    // Handle Elementor preview mode
    if (typeof elementorFrontend !== "undefined") {
      elementorFrontend.hooks.addAction(
        "frontend/element_ready/shortcode.default",
        function () {
          MNSCurrencyBanner.init();
        }
      );
    }

    // Handle Gutenberg blocks
    if (typeof wp !== "undefined" && wp.domReady) {
      wp.domReady(() => {
        MNSCurrencyBanner.init();
      });
    }
  });

  // Clean up on page unload
  $(window).on("beforeunload", function () {
    MNSCurrencyBanner.destroy();
  });
})(jQuery);
