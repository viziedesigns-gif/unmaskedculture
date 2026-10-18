/* Small enhancements; navigation and forms continue to work without JavaScript. */
(() => {
    document.documentElement.classList.toggle('native-app', window.Capacitor?.isNativePlatform?.() === true);
    document.querySelectorAll('.bottom-nav a.active').forEach(link => link.setAttribute('aria-current', 'page'));

    // Daily totals also change through API calls, so mirror their existing ring.
    const ring = document.querySelector('.progress-ring');
    if (ring) {
        const update = () => {
            const text = ring.querySelector('.progress-text')?.textContent || '';
            const target = document.getElementById('dailyProgressText');
            if (target && text) target.textContent = text + ' complete';
            const bar = document.getElementById('dailyProgressBar');
            if (bar) bar.style.width = Math.min(100, Math.max(0, Number(ring.dataset.progress) || 0)) + '%';
        };
        new MutationObserver(update).observe(ring, {attributes: true, childList: true, subtree: true, characterData: true});
        update();
    }
})();
