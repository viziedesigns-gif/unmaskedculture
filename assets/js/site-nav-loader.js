/**
 * Injects the shared Unmasked Culture navbar into #site-nav-root on every marketing page.
 */
(function () {
  function ensureAssets() {
    if (!document.querySelector('link[data-uc-site-nav-css]')) {
      var link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = '/assets/css/site-nav.css';
      link.setAttribute('data-uc-site-nav-css', '1');
      document.head.appendChild(link);
    }
  }

  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      if (document.querySelector('script[src="' + src + '"]')) {
        resolve();
        return;
      }
      var script = document.createElement('script');
      script.src = src;
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });
  }

  function mount(html) {
    var root = document.getElementById('site-nav-root');
    if (!root) {
      root = document.createElement('div');
      root.id = 'site-nav-root';
      document.body.insertBefore(root, document.body.firstChild);
    }
    root.innerHTML = html;
    document.documentElement.classList.add('has-uc-site-nav');

    // Hide legacy navs if a page still has them
    document.querySelectorAll('nav.navbar, nav.uc-navbar').forEach(function (legacy) {
      if (!legacy.closest('#site-nav-root')) {
        legacy.style.display = 'none';
        legacy.setAttribute('aria-hidden', 'true');
      }
    });
  }

  function boot() {
    ensureAssets();
    fetch('/assets/partials/site-nav.html', { credentials: 'same-origin' })
      .then(function (res) {
        if (!res.ok) throw new Error('Nav partial failed');
        return res.text();
      })
      .then(function (html) {
        mount(html);
        return loadScript('/assets/js/site-nav.js');
      })
      .then(function () {
        if (window.UCSiteNav) window.UCSiteNav.initAll();
      })
      .catch(function (err) {
        console.error('Shared navbar failed to load:', err);
      });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
