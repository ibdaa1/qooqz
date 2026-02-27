/**
 * assets/js/public.js
 * QOOQZ — Global Public Interface JS
 * - Mobile hamburger menu
 * - Dynamic theme injection from API
 * - Lazy loading helpers
 * - Lightweight, no dependencies
 */

(function () {
  'use strict';

  /* -------------------------------------------------------
   * 1. Mobile nav toggle
   * ----------------------------------------------------- */
  function initMobileNav() {
    var toggle = document.getElementById('pubHamburger');
    var drawer = document.getElementById('pubMobileNav');
    var overlay = document.getElementById('pubMobileNav');

    if (!toggle || !drawer) return;

    toggle.addEventListener('click', function () {
      var isOpen = drawer.classList.toggle('open');
      toggle.setAttribute('aria-expanded', String(isOpen));
      document.body.style.overflow = isOpen ? 'hidden' : '';
    });

    // Close on overlay click
    drawer.addEventListener('click', function (e) {
      if (e.target === drawer) {
        drawer.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
      }
    });

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && drawer.classList.contains('open')) {
        drawer.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
      }
    });
  }

  /* -------------------------------------------------------
   * 2. Apply dynamic theme colors from data attribute
   * ----------------------------------------------------- */
  function applyTheme() {
    var root = document.documentElement;
    var themeEl = document.getElementById('pubThemeData');
    if (!themeEl) return;

    var raw = themeEl.textContent || themeEl.innerText || '';
    if (!raw.trim()) return;

    var theme;
    try { theme = JSON.parse(raw); } catch (e) { return; }

    var map = {
      primary:    '--pub-primary',
      secondary:  '--pub-secondary',
      accent:     '--pub-accent',
      background: '--pub-bg',
      surface:    '--pub-surface',
      text:       '--pub-text',
    };

    Object.keys(map).forEach(function (key) {
      if (theme[key]) {
        root.style.setProperty(map[key], theme[key]);
      }
    });
  }

  /* -------------------------------------------------------
   * 3. Mark active nav link based on current path
   * ----------------------------------------------------- */
  function markActiveNav() {
    var path = window.location.pathname;
    var links = document.querySelectorAll('.pub-nav a, .pub-mobile-nav-inner a');
    links.forEach(function (a) {
      if (a.getAttribute('href') && path.indexOf(a.getAttribute('href')) !== -1) {
        a.classList.add('active');
      }
    });
  }

  /* -------------------------------------------------------
   * 4. Lazy-load images with data-src attribute
   * ----------------------------------------------------- */
  function lazyLoadImages() {
    if (!('IntersectionObserver' in window)) {
      // Fallback: load all immediately
      document.querySelectorAll('img[data-src]').forEach(function (img) {
        img.src = img.dataset.src;
      });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          var img = entry.target;
          img.src = img.dataset.src;
          img.removeAttribute('data-src');
          observer.unobserve(img);
        }
      });
    }, { rootMargin: '200px' });

    document.querySelectorAll('img[data-src]').forEach(function (img) {
      observer.observe(img);
    });
  }

  /* -------------------------------------------------------
   * 5. Simple search form progressive enhancement
   * ----------------------------------------------------- */
  function initSearch() {
    var form = document.getElementById('pubSearchForm');
    if (!form) return;

    var input = form.querySelector('.pub-search-input');
    if (!input) return;

    // Auto-focus on desktop
    if (window.innerWidth >= 768) {
      input.focus();
    }
  }

  /* -------------------------------------------------------
   * 6a. Banner/Slider carousel auto-advance
   * Finds .pub-banner-slider elements rendered by PHP,
   * activates the first slide, and auto-advances.
   * ----------------------------------------------------- */
  function initSliders() {
    var sliders = document.querySelectorAll('.pub-banner-slider');
    sliders.forEach(function (slider) {
      var slides = slider.querySelectorAll('.pub-banner-slide');
      if (slides.length <= 1) return; // nothing to cycle

      var current = 0;
      var isRtl   = document.documentElement.dir === 'rtl';

      /* Activate first slide */
      slides.forEach(function (s) { s.classList.remove('active'); });
      slides[0].classList.add('active');

      /* Inject prev / next buttons */
      var prevBtn = document.createElement('button');
      prevBtn.className = 'pub-slider-btn pub-slider-btn--prev';
      prevBtn.setAttribute('aria-label', 'Previous');
      prevBtn.innerHTML = isRtl ? '&#8250;' : '&#8249;'; // › or ‹
      slider.appendChild(prevBtn);

      var nextBtn = document.createElement('button');
      nextBtn.className = 'pub-slider-btn pub-slider-btn--next';
      nextBtn.setAttribute('aria-label', 'Next');
      nextBtn.innerHTML = isRtl ? '&#8249;' : '&#8250;'; // ‹ or ›
      slider.appendChild(nextBtn);

      /* Inject dot indicators */
      var dotsWrap = document.createElement('div');
      dotsWrap.className = 'pub-slider-dots';
      slides.forEach(function (_, i) {
        var dot = document.createElement('button');
        dot.className = 'pub-slider-dot' + (i === 0 ? ' active' : '');
        dot.setAttribute('aria-label', 'Go to slide ' + (i + 1));
        dot.addEventListener('click', function () { goTo(i); });
        dotsWrap.appendChild(dot);
      });
      slider.appendChild(dotsWrap);

      function goTo(idx) {
        slides[current].classList.remove('active');
        dotsWrap.children[current].classList.remove('active');
        current = (idx + slides.length) % slides.length;
        slides[current].classList.add('active');
        dotsWrap.children[current].classList.add('active');
      }

      prevBtn.addEventListener('click', function () { goTo(current - 1); clearInterval(timer); timer = setInterval(advance, 5000); });
      nextBtn.addEventListener('click', function () { goTo(current + 1); clearInterval(timer); timer = setInterval(advance, 5000); });

      function advance() { goTo(current + 1); }
      var timer = setInterval(advance, 5000);

      /* Pause on hover */
      slider.addEventListener('mouseenter', function () { clearInterval(timer); });
      slider.addEventListener('mouseleave', function () { timer = setInterval(advance, 5000); });
    });
  }

  /* -------------------------------------------------------
   * 6. Animate counters (stats row)
   * ----------------------------------------------------- */
  function animateCounters() {
    var counters = document.querySelectorAll('.pub-stat-value[data-target]');
    if (!counters.length) return;

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        var target = parseInt(el.dataset.target, 10);
        var duration = 800;
        var start = performance.now();
        observer.unobserve(el);

        function step(now) {
          var elapsed = now - start;
          var progress = Math.min(elapsed / duration, 1);
          // easeOut
          var value = Math.floor(progress * target);
          el.textContent = value.toLocaleString();
          if (progress < 1) requestAnimationFrame(step);
          else el.textContent = target.toLocaleString() + '+';
        }
        requestAnimationFrame(step);
      });
    });

    counters.forEach(function (c) { observer.observe(c); });
  }

  /* -------------------------------------------------------
   * 7. Filter select: submit form on change
   * ----------------------------------------------------- */
  function initFilterSelects() {
    document.querySelectorAll('.pub-filter-select[data-auto-submit]').forEach(function (sel) {
      sel.addEventListener('change', function () {
        var form = sel.closest('form');
        if (form) form.submit();
      });
    });
  }

  /* -------------------------------------------------------
   * 8. Back-to-top button
   * ----------------------------------------------------- */
  function initBackToTop() {
    var btn = document.getElementById('pubBackToTop');
    if (!btn) return;

    window.addEventListener('scroll', function () {
      btn.style.display = window.scrollY > 400 ? 'flex' : 'none';
    }, { passive: true });

    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* -------------------------------------------------------
   * 9. Cart badge — read localStorage and update #pubCartCount
   * ----------------------------------------------------- */
  function initCartBadge() {
    var badges = [
      document.getElementById('pubCartCount'),
      document.getElementById('pubCartCountMobile'),
    ];
    var cart = [];
    try { cart = JSON.parse(localStorage.getItem('pub_cart') || '[]'); } catch (e) {}
    if (!Array.isArray(cart)) cart = [];
    var total = cart.reduce(function (s, i) { return s + (Math.max(1, parseInt(i.qty, 10) || 1)); }, 0);
    badges.forEach(function (badge) {
      if (!badge) return;
      badge.textContent = total;
      badge.style.display = total ? 'inline-flex' : 'none';
    });
  }

  /* -------------------------------------------------------
   * 9b. User display: read localStorage.pubUser set by login.js
   *     Falls back to window.pubSessionUser injected by PHP (header.php)
   *     so users who logged in via the admin panel are also recognised.
   * ----------------------------------------------------- */
  function updateUserDisplay() {
    try {
      var u = null;
      var raw = localStorage.getItem('pubUser');
      if (raw) { try { u = JSON.parse(raw); } catch (e) {} }

      // Fallback: PHP session user injected in <head> by header.php
      if (!u || !u.id) {
        u = (typeof window.pubSessionUser !== 'undefined' && window.pubSessionUser) ? window.pubSessionUser : null;
        // Sync to localStorage so future checks (pubAddToCart, job.php) also see it
        if (u && u.id) {
          try { localStorage.setItem('pubUser', JSON.stringify(u)); } catch (e) {}
        }
      }

      if (!u || !u.id) return;

      var displayName = u.name || u.username || 'User';

      // Update header login link(s) that still point to login.php
      var loginLinks = document.querySelectorAll('a.pub-login-btn');
      loginLinks.forEach(function (el) {
        if (el.href && el.href.indexOf('login.php') !== -1) {
          el.textContent = displayName;
          el.href = '/frontend/profile.php';
        }
      });
    } catch (e) {}
  }

  /* -------------------------------------------------------
   * 10. Init all on DOMContentLoaded
   * ----------------------------------------------------- */
  document.addEventListener('DOMContentLoaded', function () {
    applyTheme();
    initMobileNav();
    markActiveNav();
    lazyLoadImages();
    initSearch();
    initSliders();
    animateCounters();
    initFilterSelects();
    initBackToTop();
    initCartBadge();
    updateUserDisplay();
  });

})();

/* -------------------------------------------------------
 * Global Add-to-Cart helpers (available on all pages)
 * Used by product detail page and products listing cards.
 * ----------------------------------------------------- */

/**
 * Change quantity in #pubQtyInput by delta (+1 / -1).
 */
function pubQtyChange(delta) {
  var inp = document.getElementById('pubQtyInput');
  if (!inp) return;
  var v = parseInt(inp.value, 10) || 1;
  v = Math.max(1, Math.min(parseInt(inp.max, 10) || 999, v + delta));
  inp.value = v;
}

/**
 * Add a product to the cart (localStorage 'pub_cart').
 * Accepts a button/span element with data-product-* attributes.
 * Quantity is taken from #pubQtyInput (defaults to 1).
 */
function pubAddToCart(btn) {
  // Require login — check localStorage first, then window.pubSessionUser (PHP session)
  try {
    var pubU = JSON.parse(localStorage.getItem('pubUser') || 'null');
    if (!pubU || !pubU.id) {
      // Try server-injected session user (for users who logged in via admin panel)
      if (typeof window.pubSessionUser !== 'undefined' && window.pubSessionUser && window.pubSessionUser.id) {
        pubU = window.pubSessionUser;
        try { localStorage.setItem('pubUser', JSON.stringify(pubU)); } catch (e) {}
      }
    }
    if (!pubU || !pubU.id) {
      window.location.href = '/frontend/login.php?redirect=' + encodeURIComponent(window.location.href);
      return;
    }
  } catch (e) {
    window.location.href = '/frontend/login.php';
    return;
  }

  var qtyInput = document.getElementById('pubQtyInput');
  var qty      = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;
  var id    = parseInt(btn.dataset.productId, 10);
  var name  = btn.dataset.productName  || '';
  var price = parseFloat(btn.dataset.productPrice) || 0;
  var img   = btn.dataset.productImage || '';
  var cur   = btn.dataset.currency     || '';
  var sku   = btn.dataset.productSku   || '';

  if (!id) return;

  var cart = [];
  try { cart = JSON.parse(localStorage.getItem('pub_cart') || '[]'); } catch (e) {}
  if (!Array.isArray(cart)) cart = [];

  var found = false;
  cart.forEach(function (item) {
    if (item.id === id) { item.qty = (item.qty || 1) + qty; found = true; }
  });
  if (!found) {
    cart.push({ id: id, name: name, price: price, qty: qty, image: img, currency: cur, sku: sku });
  }
  localStorage.setItem('pub_cart', JSON.stringify(cart));

  /* Update cart count badges if present */
  var badges = [
    document.getElementById('pubCartCount'),
    document.getElementById('pubCartCountMobile'),
  ];
  var total = cart.reduce(function (s, i) { return s + (Math.max(1, parseInt(i.qty, 10) || 1)); }, 0);
  badges.forEach(function (badge) {
    if (!badge) return;
    badge.textContent = total;
    badge.style.display = total ? 'inline-flex' : 'none';
  });

  /* Visual feedback */
  var orig = btn.textContent;
  btn.textContent = btn.dataset.addedText || '✅';
  btn.disabled = true;
  setTimeout(function () { btn.textContent = orig; btn.disabled = false; }, 1800);
}
