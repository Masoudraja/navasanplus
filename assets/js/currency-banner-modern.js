/**
 * MNS Navasan Plus - Modern Currency Banner JavaScript
 * Uses modern libraries like Swiper and GSAP for enhanced animations
 */

(function ($) {
  "use strict";

  const Swiper = window.Swiper || null;
  const gsap = window.gsap || null;

  if (typeof window.mnsBannerDebug === "undefined") {
    window.mnsBannerDebug = false;
  }

  const mnsCurrencyBanner = window.mnsCurrencyBanner || {};

  window.MNSCurrencyBannerModern = {
    instances: new Map(),

    init: function () {
      const self = window.MNSCurrencyBannerModern;
      const $banners = $(".mns-currency-banner-modern");

      if (window.mnsBannerDebug) {
        console.log("[MNS Modern Banner] Global init() - Found", $banners.length, "banners");
      }

      $banners.each(function (index) {
        const bannerId = $(this).attr("id");
        if (window.mnsBannerDebug) {
          console.log("[MNS Modern Banner] Processing banner", index + 1, "ID:", bannerId);
        }

        if (bannerId && !self.instances.has(bannerId)) {
          if (window.mnsBannerDebug) {
            console.log("[MNS Modern Banner] Creating new instance for:", bannerId);
          }
          const instance = new ModernCurrencyBanner(this);
          self.instances.set(bannerId, instance);
        } else if (bannerId && self.instances.has(bannerId)) {
          if (window.mnsBannerDebug) {
            console.log("[MNS Modern Banner] Instance already exists for:", bannerId);
          }
        } else {
          if (window.mnsBannerDebug) {
            console.log("[MNS Modern Banner] Warning: Banner has no ID, skipping");
          }
        }
      });

      if (window.mnsBannerDebug) {
        console.log("[MNS Modern Banner] Total active instances:", self.instances.size);
      }
    },

    refresh: function (bannerId = null) {
      const self = window.MNSCurrencyBannerModern;
      if (bannerId) {
        const instance = self.instances.get(bannerId);
        if (instance) {
          instance.refreshRates();
        }
      } else {
        self.instances.forEach((instance) => {
          instance.refreshRates();
        });
      }
    },

    destroy: function (bannerId = null) {
      const self = window.MNSCurrencyBannerModern;
      if (bannerId) {
        const instance = self.instances.get(bannerId);
        if (instance) {
          instance.destroy();
          self.instances.delete(bannerId);
        }
      } else {
        self.instances.forEach((instance) => {
          instance.destroy();
        });
        self.instances.clear();
      }
    },
  };

  class ModernCurrencyBanner {
    constructor(element) {
      this.banner = $(element);
      this.bannerId = this.banner.attr("id");
      this.currencyIds = this.getCurrencyIds();
      this.autoRefreshInterval = parseInt(this.banner.data("auto-refresh")) || 0;
      this.showChange = this.banner.data("show-change") === "yes";
      this.showTime = this.banner.data("show-time") === "yes";
      this.showSymbol = this.banner.data("show-symbol") === "yes";
      this.animationType = this.banner.data("animation") || "slide";
      this.refreshTimer = null;
      this.lastRates = {};
      this.isLoading = false;
      this.resizeTimer = null;
      this.swiperInstance = null;

      this.isInWoodmartHeader = this.banner.closest(".woodmart-header-banner").length > 0;

      this.boundHandleVisibilityChange = this.handleVisibilityChange.bind(this);
      this.boundHandleWindowFocus = this.handleWindowFocus.bind(this);
      this.boundHandleWindowBlur = this.handleWindowBlur.bind(this);

      this.init();
    }

    init() {
      if (window.mnsBannerDebug) {
        console.log("[MNS Modern Banner] Instance init() called for:", this.bannerId);
      }

      this.setupEventListeners();
      this.startAutoRefresh();
      this.storeCurrentRates();

      setTimeout(() => {
        this.initAnimations();
      }, 100);
    }

    initAnimations() {
      const width = $(window).width();
      const animationType = this.getAnimationType();

      if (window.mnsBannerDebug) {
        console.log("[MNS Modern Banner] Init animations for:", animationType, "width:", width);
      }

      let effectiveAnimationType = animationType;
      if (width <= 576) {
        const mobileAnimation = this.banner.data("mobile-animation");
        effectiveAnimationType = mobileAnimation || "ticker";
      } else if (width <= 768) {
        effectiveAnimationType = this.banner.data("tablet-animation") || "grid";
      }

      if (this.isInWoodmartHeader) {
        if (effectiveAnimationType === "ticker" || effectiveAnimationType === "carousel") {
          effectiveAnimationType = "ticker";
        }
      }

      if (effectiveAnimationType === "ticker") {
        this.initTickerSwiper();
      } else if (effectiveAnimationType === "carousel" && Swiper) {
        this.initCarouselSwiper();
      } else if (effectiveAnimationType === "slide" && gsap) {
        this.animateWithGSAP("slide");
      } else if (effectiveAnimationType === "fade" && gsap) {
        this.animateWithGSAP("fade");
      } else {
        this.animateWithCSS(effectiveAnimationType);
      }
    }

    initTickerSwiper() {
        if (!Swiper) {
            if (window.mnsBannerDebug) {
                console.warn("[MNS Modern Banner] Swiper not available for ticker, falling back to CSS carousel");
            }
            this.animateWithCSS("carousel");
            return;
        }

        const $container = this.banner.find(".mns-banner-container-modern");
        const $items = $container.find(".mns-currency-item-modern");
        const itemCount = $items.length;

        if (itemCount === 0) return;

        // Get the HTML of all items
        const itemsHtml = $items.map(function() {
            return '<div class="swiper-slide">' + this.outerHTML + '</div>';
        }).get().join('');

        // Wrap container in swiper structure
        $container.html(`<div class="swiper-wrapper">${itemsHtml}</div>`);
        $container.addClass('swiper-container mns-ticker-swiper');

        // Wait for DOM to settle before measuring
        setTimeout(() => {
            // Check if content overflows to determine if ticker is needed
            const containerWidth = $container.width();
            let totalWidth = 0;
            $container.find('.swiper-slide').each(function() {
                totalWidth += $(this).outerWidth(true);
            });

            if (totalWidth <= containerWidth && itemCount <= 3) {
                if (window.mnsBannerDebug) {
                    console.log("[MNS Modern Banner] Ticker not needed, content fits within the container.");
                }
                $container.find('.swiper-wrapper').css({
                    'justify-content': 'center',
                    'display': 'flex'
                });
                return;
            }

            // Initialize Swiper with proper ticker configuration
            this.swiperInstance = new Swiper($container[0], {
                loop: true,
                slidesPerView: 'auto',
                spaceBetween: 16,
                centeredSlides: false,
                speed: 5000, // Fixed speed for smooth ticker
                freeMode: true, // MUST be true for ticker effect
                autoplay: {
                    delay: 0,
                    disableOnInteraction: false,
                    pauseOnMouseEnter: false
                },
                allowTouchMove: true,
                grabCursor: true,
                loopAdditionalSlides: 2,
                loopedSlides: itemCount
            });

            if (window.mnsBannerDebug) {
                console.log("[MNS Modern Banner] Ticker Swiper configured:", {
                    itemCount: itemCount,
                    totalWidth: totalWidth,
                    speed: this.swiperInstance.params.speed
                });
            }
        }, 50);
    }

    initCarouselSwiper() {
      if (!Swiper) {
        if (window.mnsBannerDebug) {
          console.warn("[MNS Modern Banner] Swiper not available, falling back to CSS carousel");
        }
        this.animateWithCSS("carousel");
        return;
      }

      const $container = this.banner.find(".mns-banner-container-modern");
      const $items = $container.find(".mns-currency-item-modern");
      const itemCount = $items.length;

      if (itemCount === 0) return;

      const itemsHtml = $items.map(function() { return '<div class="swiper-slide">' + this.outerHTML + '</div>'; }).get().join('');

      const swiperId = this.bannerId + "-swiper";
      $container.html(
        `<div class="swiper mns-swiper-container" id="${swiperId}">
          <div class="swiper-wrapper">${itemsHtml}</div>
          <div class="swiper-button-next"></div>
          <div class="swiper-button-prev"></div>
          <div class="swiper-pagination"></div>
        </div>`
      );

      const containerWidth = $container.width() || 500;
      const itemWidth = 150;
      const maxSlidesPerView = Math.max(1, Math.floor(containerWidth / itemWidth));
      const slidesPerView = Math.min(maxSlidesPerView, itemCount || 1);

      this.swiperInstance = new Swiper($container.find(".swiper")[0], {
        slidesPerView: 1,
        spaceBetween: 10,
        loop: itemCount > slidesPerView,
        autoplay: {
          delay: 3000,
          disableOnInteraction: false,
          pauseOnMouseEnter: true,
        },
        speed: 600,
        navigation: {
          nextEl: ".swiper-button-next",
          prevEl: ".swiper-button-prev",
        },
        pagination: {
          el: ".swiper-pagination",
          clickable: true,
          type: "bullets",
        },
        breakpoints: {
          320: { slidesPerView: Math.min(1.5, slidesPerView), spaceBetween: 8 },
          480: { slidesPerView: Math.min(2.5, slidesPerView), spaceBetween: 10 },
          768: { slidesPerView: Math.min(3.5, slidesPerView), spaceBetween: 12 },
          1024: { slidesPerView: Math.min(4.5, slidesPerView), spaceBetween: 15 },
        },
      });

      if (window.mnsBannerDebug) {
        console.log("[MNS Modern Banner] Carousel Swiper initialized. Items:", itemCount);
      }
    }

    animateWithGSAP(type) {
      const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
      if (motionQuery.matches) {
        this.banner.find(".mns-currency-item-modern").css('opacity', 1);
        return;
      }

      if (!gsap) {
        if (window.mnsBannerDebug) {
          console.warn("[MNS Modern Banner] GSAP not available, falling back to CSS animations");
        }
        this.animateWithCSS(type);
        return;
      }

      const $items = this.banner.find(".mns-currency-item-modern");

      if (type === "slide") {
        gsap.fromTo(
          $items.toArray(),
          { x: 20, opacity: 0 },
          { x: 0, opacity: 1, duration: 0.5, stagger: 0.1, ease: "power2.out" }
        );
      } else if (type === "fade") {
        gsap.fromTo(
          $items.toArray(),
          { opacity: 0 },
          { opacity: 1, duration: 0.8, stagger: 0.1, ease: "power2.out" }
        );
      }
    }

    animateWithCSS(type) {
      const $items = this.banner.find(".mns-currency-item-modern");
      const $container = this.banner.find(".mns-banner-container-modern");

      if (type === "slide" || type === "fade") {
        $items.each(function (index) {
          setTimeout(() => {
            $(this).addClass("mns-animate-in-modern");
          }, index * 100);
        });
      } else if (type === "carousel") {
        $container.css({
          "overflow-x": "auto",
          "flex-wrap": "nowrap",
          "scroll-behavior": "smooth",
        });
        this.enhanceScrolling($container);
      } else if (type === "grid") {
        $container.css({
          "overflow-x": "hidden",
          "flex-wrap": "wrap",
        });
      }
    }

    enhanceScrolling($container) {
      let isDown = false;
      let startX;
      let scrollLeft;

      // Check if content overflows and add class
      if ($container[0].scrollWidth > $container[0].clientWidth) {
        this.banner.addClass("has-overflow");
      }

      // Hide scroll hint on first scroll (mobile)
      let hasScrolled = false;
      $container.on("scroll", () => {
        if (!hasScrolled) {
          hasScrolled = true;
          this.banner.addClass("scrolled");
        }

        // Check if scrolled to end to hide fade indicator
        const scrollLeft = $container.scrollLeft();
        const scrollWidth = $container[0].scrollWidth;
        const clientWidth = $container[0].clientWidth;

        if (scrollLeft + clientWidth >= scrollWidth - 10) {
          this.banner.addClass("scroll-end");
        } else {
          this.banner.removeClass("scroll-end");
        }
      });

      // Touch scroll detection for mobile
      $container.on("touchmove", () => {
        if (!hasScrolled) {
          hasScrolled = true;
          this.banner.addClass("scrolled");
        }
      });

      $container.on("mousedown", (e) => {
        isDown = true;
        startX = e.pageX - $container.offset().left;
        scrollLeft = $container.scrollLeft();
      });

      $container.on("mouseleave", () => { isDown = false; });
      $container.on("mouseup", () => { isDown = false; });

      $container.on("mousemove", (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - $container.offset().left;
        const walk = (x - startX) * 2;
        $container.scrollLeft(scrollLeft - walk);
      });

      this.addScrollIndicators($container);
    }

    addScrollIndicators($container) {
      this.banner.find(".mns-scroll-indicator").remove();

      if ($container[0].scrollWidth > $container[0].clientWidth) {
        const indicatorHtml = `
          <div class="mns-scroll-indicator mns-scroll-left">‹</div>
          <div class="mns-scroll-indicator mns-scroll-right">›</div>
        `;
        this.banner.append(indicatorHtml);

        const $leftIndicator = this.banner.find(".mns-scroll-left");
        const $rightIndicator = this.banner.find(".mns-scroll-right");

        const updateIndicators = () => {
          const scrollLeft = $container.scrollLeft();
          const scrollWidth = $container[0].scrollWidth;
          const clientWidth = $container[0].clientWidth;
          $leftIndicator.toggleClass("visible", scrollLeft > 10);
          $rightIndicator.toggleClass("visible", scrollLeft < scrollWidth - clientWidth - 10);
        };

        updateIndicators();
        $container.on("scroll", updateIndicators);

        $leftIndicator.on("click", () => {
          $container.animate({ scrollLeft: "-=150" }, 300);
        });

        $rightIndicator.on("click", () => {
          $container.animate({ scrollLeft: "+=150" }, 300);
        });
      }
    }

    getCurrencyIds() {
      const ids = [];
      this.banner
        .find(".mns-currency-item-modern:not(.cloned)")
        .each(function () {
          const id = $(this).data("currency-id");
          if (id && String(id).trim()) {
            ids.push(id);
          }
        });
      return ids;
    }

    setupEventListeners() {
      this.banner.on("mouseenter", ".mns-currency-item-modern", this.handleItemHover.bind(this));
      this.banner.on("mouseleave", ".mns-currency-item-modern", this.handleItemLeave.bind(this));

      if (typeof document.hidden !== "undefined") {
        document.addEventListener("visibilitychange", this.boundHandleVisibilityChange);
      }

      $(window).on("focus", this.boundHandleWindowFocus);
      $(window).on("blur", this.boundHandleWindowBlur);

      $(window).on("resize", () => {
        clearTimeout(this.resizeTimer);
        this.resizeTimer = setTimeout(() => {
          this.handleResize();
        }, 250);
      });
    }

    handleResize() {
      if (this.swiperInstance) {
        this.swiperInstance.destroy(true, true);
        this.swiperInstance = null;
      }

      this.banner.find(".mns-currency-item-modern.cloned").remove();

      setTimeout(() => {
        this.initAnimations();
      }, 100);
    }

    handleItemHover(event) {
      $(event.currentTarget).addClass("mns-item-hover-modern");
    }

    handleItemLeave(event) {
      $(event.currentTarget).removeClass("mns-item-hover-modern");
    }

    handleVisibilityChange() {
      if (document.hidden) {
        this.pauseAutoRefresh();
      } else {
        this.resumeAutoRefresh();
      }
    }

    handleWindowFocus() {
      this.resumeAutoRefresh();
    }

    handleWindowBlur() {
      this.pauseAutoRefresh();
    }

    getAnimationType() {
      return this.banner.data("animation") || "slide";
    }

    startAutoRefresh() {
      if (this.autoRefreshInterval > 0) {
        this.refreshTimer = setInterval(() => {
          this.refreshRates();
        }, this.autoRefreshInterval * 1000);
      }
    }

    pauseAutoRefresh() {
      if (this.refreshTimer) {
        clearInterval(this.refreshTimer);
        this.refreshTimer = null;
      }
    }

    resumeAutoRefresh() {
      if (this.autoRefreshInterval > 0 && !this.refreshTimer) {
        this.startAutoRefresh();
      }
    }

    storeCurrentRates() {
      this.banner
        .find(".mns-currency-item-modern:not(.cloned)")
        .each((index, item) => {
          const $item = $(item);
          const currencyId = $item.data("currency-id");
          const rate = $item.find(".mns-price-value-modern").data("rate");
          if (currencyId && rate !== undefined) {
            this.lastRates[currencyId] = rate;
          }
        });
    }

    refreshRates() {
      if (this.isLoading || this.currencyIds.length === 0) {
        return;
      }

      this.isLoading = true;
      this.banner.addClass("loading");

      let ajaxUrl =
        typeof mnsCurrencyBanner !== "undefined" && mnsCurrencyBanner.ajaxUrl
          ? mnsCurrencyBanner.ajaxUrl
          : typeof window.ajaxurl !== "undefined"
          ? window.ajaxurl
          : "/wp-admin/admin-ajax.php";

      if (ajaxUrl.indexOf("http") !== 0) {
        const cleanPath = ajaxUrl.replace(/^\/+/, "");
        ajaxUrl = window.location.origin + "/" + cleanPath;
      }

      const nonce =
        typeof mnsCurrencyBanner !== "undefined" && mnsCurrencyBanner.nonce
          ? mnsCurrencyBanner.nonce
          : typeof window.mnsCurrencyBanner !== "undefined" &&
            window.mnsCurrencyBanner.nonce
          ? window.mnsCurrencyBanner.nonce
          : "";

      const data = {
        action: "mns_get_currency_rates",
        currency_ids: this.currencyIds.join(","),
        nonce: nonce,
        security: nonce,
      };

      if (window.mnsBannerDebug) {
        console.log("[MNS Modern Banner] Refreshing rates for currencies:", this.currencyIds);
        console.log("[MNS Modern Banner] AJAX URL:", ajaxUrl);
        console.log("[MNS Modern Banner] Data:", data);
      }

      const self = this;

      const makeRequest = (method) => {
        return $.ajax({
          url: ajaxUrl,
          type: method,
          data: data,
          dataType: "json",
          timeout: 15000,
        });
      };

      makeRequest("POST")
        .done((response) => {
          self.handleAjaxSuccess(response);
        })
        .fail(() => {
          makeRequest("GET")
            .done((response) => {
              self.handleAjaxSuccess(response);
            })
            .fail((xhr, status, error) => {
              self.handleAjaxError(xhr, status, error, ajaxUrl, data);
            });
        });
    }

    handleAjaxSuccess(response) {
      if (window.mnsBannerDebug) {
        console.log("[MNS Modern Banner] AJAX response:", response);
      }

      if (response.success && response.data && response.data.rates) {
        this.updateRates(response.data.rates);
        if (this.showTime && response.data.timestamp) {
          this.updateTimestamp(response.data.timestamp);
        }
      } else {
        console.warn("[MNS Modern Banner] Failed to refresh rates:", response);
      }

      this.isLoading = false;
      this.banner.removeClass("loading");
    }

    handleAjaxError(xhr, status, error, ajaxUrl, data) {
      if (window.mnsBannerDebug) {
        console.error("[MNS Modern Banner] AJAX request failed:", status, error, "URL:", ajaxUrl, "Data:", data);
        console.error("[MNS Modern Banner] Response:", xhr.responseText);
      }

      this.banner
        .find(".mns-banner-timestamp-modern")
        .html(
          '<span class="mns-error-message-modern">' +
            (typeof mnsCurrencyBanner !== "undefined" &&
            mnsCurrencyBanner.i18n &&
            mnsCurrencyBanner.i18n.error
              ? mnsCurrencyBanner.i18n.error
              : "Error loading rates") +
            "</span>"
        );

      this.isLoading = false;
      this.banner.removeClass("loading");

      if (this.refreshTimer) {
        clearInterval(this.refreshTimer);
        this.refreshTimer = null;
      }
    }

    updateRates(rates) {
      const self = this;
      this.banner
        .find(".mns-currency-item-modern:not(.cloned)")
        .each(function () {
          const $item = $(this);
          const currencyId = $item.data("currency-id");
          const rateData = rates[currencyId];

          if (rateData) {
            const $price = $item.find(".mns-price-value-modern");
            const newRate = parseFloat(rateData.rate) || 0;
            const formattedRate = parseFloat(newRate).toLocaleString("en-US", { maximumFractionDigits: 0 });

            $price.data("rate", newRate).text(formattedRate);

            if (rateData.change_pct !== undefined) {
              const $change = $item.find(".mns-price-change-modern");
              const changeValue = parseFloat(rateData.change_pct);
              const changeText = (changeValue > 0 ? "+" : "") + changeValue.toFixed(2) + "%";

              $change.find(".mns-change-value-modern").text(changeText);

              $change.removeClass("mns-change-positive-modern mns-change-negative-modern mns-change-neutral-modern");
              if (changeValue > 0) {
                $change.addClass("mns-change-positive-modern");
              } else if (changeValue < 0) {
                $change.addClass("mns-change-negative-modern");
              } else {
                $change.addClass("mns-change-neutral-modern");
              }
            }

            $item.addClass("mns-price-updated-modern");
            setTimeout(() => {
              $item.removeClass("mns-price-updated-modern");
            }, 1000);
          }
        });

      Object.keys(rates).forEach((currencyId) => {
        if (rates[currencyId].rate !== undefined) {
          this.lastRates[currencyId] = rates[currencyId].rate;
        }
      });
    }

    updateTimestamp(timestamp) {
      this.banner.find(".mns-time-value-modern").text(timestamp);
    }

    destroy() {
      if (this.refreshTimer) {
        clearInterval(this.refreshTimer);
      }

      if (this.swiperInstance) {
        this.swiperInstance.destroy(true, true);
      }

      document.removeEventListener("visibilitychange", this.boundHandleVisibilityChange);
      $(window).off("focus", this.boundHandleWindowFocus);
      $(window).off("blur", this.boundHandleWindowBlur);
      $(window).off("resize", this.handleResize);
      this.banner.off("mouseenter", ".mns-currency-item-modern");
      this.banner.off("mouseleave", ".mns-currency-item-modern");

      if (this.bannerId) {
        window.MNSCurrencyBannerModern.instances.delete(this.bannerId);
      }

      if (window.mnsBannerDebug) {
        console.log("[MNS Modern Banner] Instance destroyed:", this.bannerId);
      }
    }
  }

  $(document).ready(function () {
    window.MNSCurrencyBannerModern.init();
  });

  $(document).on("mns_banner_init", function () {
    window.MNSCurrencyBannerModern.init();
  });
})(jQuery);
