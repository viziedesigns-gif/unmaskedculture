/**
 * Unmasked Culture shared navbar behavior
 * Mainstream mobile: hamburger → full-height panel, accordion dropdowns, backdrop close
 */
(function () {
  var BP = 992;

  function isMobile() {
    return window.matchMedia('(max-width: ' + BP + 'px)').matches;
  }

  function closeDropdowns(nav, except) {
    nav.querySelectorAll('[data-dropdown].is-open').forEach(function (item) {
      if (item === except) return;
      item.classList.remove('is-open');
      var btn = item.querySelector(':scope > .uc-site-nav__link');
      if (btn) btn.setAttribute('aria-expanded', 'false');
    });
  }

  function setOpen(nav, toggle, open) {
    nav.classList.toggle('is-open', open);
    document.body.classList.toggle('uc-nav-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    var backdrop = nav.querySelector('[data-uc-nav-backdrop]');
    if (backdrop) {
      if (open) backdrop.removeAttribute('hidden');
      else backdrop.setAttribute('hidden', '');
    }
    if (!open) closeDropdowns(nav);
  }

  function markActiveLinks(nav) {
    var path = (window.location.pathname || '/').replace(/\/+$/, '') || '/';
    var file = path.split('/').pop() || '';
    nav.querySelectorAll('a[href]').forEach(function (link) {
      var href = link.getAttribute('href') || '';
      if (!href || href === '#') return;
      var normalized = href.replace(/\/+$/, '') || '/';
      if (normalized === path || normalized === '/' + file || (path === '/' && normalized === '/')) {
        link.classList.add('is-active');
      }
    });
  }

  function initNav(nav) {
    if (!nav || nav.dataset.ucNavReady === 'true') return;
    nav.dataset.ucNavReady = 'true';

    var toggle = nav.querySelector('#uc-nav-toggle');
    var menu = nav.querySelector('#uc-nav-menu');
    var backdrop = nav.querySelector('[data-uc-nav-backdrop]');
    if (!toggle || !menu) return;

    markActiveLinks(nav);

    toggle.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      setOpen(nav, toggle, !nav.classList.contains('is-open'));
    });

    if (backdrop) {
      backdrop.addEventListener('click', function () {
        setOpen(nav, toggle, false);
      });
    }

    nav.querySelectorAll('[data-dropdown] > .uc-site-nav__link').forEach(function (btn) {
      btn.addEventListener('click', function (event) {
        if (!isMobile()) return;
        event.preventDefault();
        event.stopPropagation();
        var item = btn.closest('[data-dropdown]');
        var willOpen = item && !item.classList.contains('is-open');
        closeDropdowns(nav, item);
        if (item) {
          item.classList.toggle('is-open', !!willOpen);
          btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        }
      });
    });

    menu.addEventListener('click', function (event) {
      var link = event.target.closest('a[href]');
      if (!link || !isMobile()) return;
      // Close after choosing a real destination
      setOpen(nav, toggle, false);
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && nav.classList.contains('is-open')) {
        setOpen(nav, toggle, false);
        toggle.focus();
      }
    });

    window.addEventListener('resize', function () {
      if (!isMobile() && nav.classList.contains('is-open')) {
        setOpen(nav, toggle, false);
      }
      if (!isMobile()) closeDropdowns(nav);
    });
  }

  window.UCSiteNav = {
    init: initNav,
    initAll: function () {
      document.querySelectorAll('.uc-site-nav').forEach(initNav);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.UCSiteNav.initAll);
  } else {
    window.UCSiteNav.initAll();
  }
})();
