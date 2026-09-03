(function() {
    function setMenuState(menuToggle, navMenu, isOpen) {
        navMenu.classList.toggle('active', isOpen);
        menuToggle.classList.toggle('active', isOpen);
        menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        document.body.classList.toggle('site-menu-open', isOpen);

        if (!isOpen) {
            navMenu.querySelectorAll('.dropdown.active').forEach(function(dropdown) {
                dropdown.classList.remove('active');
            });
        }
    }

    function initSiteShellNav() {
        const menuToggle = document.getElementById('mobile-menu');
        const navMenu = document.getElementById('nav-menu');

        if (!menuToggle || !navMenu || menuToggle.dataset.siteShellReady === 'true') {
            return;
        }

        menuToggle.dataset.siteShellReady = 'true';
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.setAttribute('aria-controls', navMenu.id || 'nav-menu');

        menuToggle.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            setMenuState(menuToggle, navMenu, !navMenu.classList.contains('active'));
        });

        menuToggle.addEventListener('keydown', function(event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                setMenuState(menuToggle, navMenu, !navMenu.classList.contains('active'));
            }
        });

        navMenu.querySelectorAll('.dropdown > .nav-link').forEach(function(toggle) {
            toggle.classList.add('dropdown-toggle');

            toggle.addEventListener('click', function(event) {
                if (window.innerWidth > 1040) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                const dropdown = toggle.closest('.dropdown');
                const shouldOpen = dropdown && !dropdown.classList.contains('active');

                navMenu.querySelectorAll('.dropdown.active').forEach(function(openDropdown) {
                    if (openDropdown !== dropdown) {
                        openDropdown.classList.remove('active');
                    }
                });

                if (dropdown) {
                    dropdown.classList.toggle('active', shouldOpen);
                }
            });
        });

        navMenu.addEventListener('click', function(event) {
            const link = event.target.closest('a');
            if (link && window.innerWidth <= 1040 && !link.closest('.dropdown > .nav-link')) {
                setMenuState(menuToggle, navMenu, false);
            }
        });

        document.addEventListener('click', function(event) {
            if (
                window.innerWidth <= 1040 &&
                navMenu.classList.contains('active') &&
                !event.target.closest('#nav-menu') &&
                !event.target.closest('#mobile-menu')
            ) {
                setMenuState(menuToggle, navMenu, false);
            }
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 1040) {
                if (navMenu.classList.contains('active')) {
                    setMenuState(menuToggle, navMenu, false);
                }

                navMenu.querySelectorAll('.dropdown.active').forEach(function(dropdown) {
                    dropdown.classList.remove('active');
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSiteShellNav);
    } else {
        initSiteShellNav();
    }
})();
