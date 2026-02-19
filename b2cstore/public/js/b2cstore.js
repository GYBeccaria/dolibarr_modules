/* B2CStore — Frontend JavaScript
 * Copyright (C) 2025 Henaxis
 * Zero external dependencies.
 */
(function () {
  'use strict';

  /* ============================================================
     MOBILE NAVBAR TOGGLE
     ============================================================ */
  function initNavbar() {
    var toggle = document.querySelector('.b2cs-navbar__toggle');
    var menu   = document.querySelector('.b2cs-navbar__menu');
    if (!toggle || !menu) return;

    toggle.addEventListener('click', function () {
      var expanded = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', String(!expanded));
      menu.classList.toggle('is-open', !expanded);
    });

    // Close menu on outside click
    document.addEventListener('click', function (e) {
      if (!toggle.contains(e.target) && !menu.contains(e.target)) {
        toggle.setAttribute('aria-expanded', 'false');
        menu.classList.remove('is-open');
      }
    });
  }

  /* ============================================================
     CART BADGE COUNTER
     ============================================================ */
  function updateCartBadge(count) {
    var badge = document.querySelector('.b2cs-cart-badge__count');
    if (!badge) return;
    badge.textContent = count > 0 ? count : '';
    badge.classList.toggle('is-empty', count === 0);
  }

  /* ============================================================
     ADD TO CART AJAX (optional progressive enhancement)
     If the form has data-ajax="true" we submit via fetch and
     update the badge without a full page reload.
     ============================================================ */
  function initAjaxCart() {
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form.matches('form[data-ajax="cart"]')) return;
      e.preventDefault();

      var data = new FormData(form);
      fetch(form.action || window.location.href, {
        method: 'POST',
        body: data,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (json && typeof json.cart_count !== 'undefined') {
            updateCartBadge(parseInt(json.cart_count, 10));
          }
          if (json && json.message) {
            showFlash(json.message, json.error ? 'error' : 'success');
          }
        })
        .catch(function () {
          // Fallback: submit normally
          form.removeAttribute('data-ajax');
          form.submit();
        });
    });
  }

  /* ============================================================
     FLASH MESSAGE HELPER
     ============================================================ */
  function showFlash(message, type) {
    var existing = document.querySelector('.b2cs-flash-ajax');
    if (existing) existing.remove();

    var el = document.createElement('div');
    el.className = 'b2cs-alert b2cs-alert--' + (type || 'info') + ' b2cs-flash-ajax';
    el.style.cssText = 'position:fixed;top:72px;left:50%;transform:translateX(-50%);z-index:999;min-width:280px;max-width:90vw;box-shadow:0 4px 16px rgba(0,0,0,.15)';
    el.textContent = message;
    document.body.appendChild(el);

    setTimeout(function () {
      el.style.transition = 'opacity .4s';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 450);
    }, 3000);
  }

  /* ============================================================
     QTY INPUT AUTO-SUBMIT (cart page)
     ============================================================ */
  function initQtyAutoSubmit() {
    document.querySelectorAll('.b2cs-input--qty').forEach(function (input) {
      var debounceTimer;
      input.addEventListener('change', function () {
        clearTimeout(debounceTimer);
        var form = input.closest('form');
        if (form) {
          debounceTimer = setTimeout(function () { form.submit(); }, 300);
        }
      });
    });
  }

  /* ============================================================
     SMOOTH SCROLL for anchor links
     ============================================================ */
  function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        var id = a.getAttribute('href').slice(1);
        var target = id ? document.getElementById(id) : null;
        if (target) {
          e.preventDefault();
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });
  }

  /* ============================================================
     IMAGE LAZY LOAD polyfill (for older browsers)
     ============================================================ */
  function initLazyLoad() {
    if ('loading' in HTMLImageElement.prototype) return; // native support
    var imgs = document.querySelectorAll('img[loading="lazy"]');
    if (!imgs.length) return;
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          var img = entry.target;
          if (img.dataset.src) img.src = img.dataset.src;
          io.unobserve(img);
        }
      });
    });
    imgs.forEach(function (img) { io.observe(img); });
  }

  /* ============================================================
     INIT
     ============================================================ */
  document.addEventListener('DOMContentLoaded', function () {
    initNavbar();
    initAjaxCart();
    initQtyAutoSubmit();
    initSmoothScroll();
    initLazyLoad();
  });
}());
