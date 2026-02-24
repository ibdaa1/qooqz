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
   * 9. Init all on DOMContentLoaded
   * ----------------------------------------------------- */
  document.addEventListener('DOMContentLoaded', function () {
    applyTheme();
    initMobileNav();
    markActiveNav();
    lazyLoadImages();
    initSearch();
    animateCounters();
    initFilterSelects();
    initBackToTop();
  });

})();
