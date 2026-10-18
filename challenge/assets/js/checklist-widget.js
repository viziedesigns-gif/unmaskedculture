/* Bridge is available only in Android builds containing the native widget. */
(() => {
    if (!window.Capacitor?.isPluginAvailable?.('ChecklistWidget')) return;
    const widget = window.Capacitor.Plugins.ChecklistWidget;
    let timer;
    const refresh = () => {
        clearTimeout(timer);
        timer = setTimeout(() => widget.refresh().catch(() => {}), 350);
    };
    refresh();
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    const checklist = document.getElementById('dailyRhythm');
    if (checklist) new MutationObserver(records => {
        // The dashboard countdown changes every second. Only completion changes need a sync.
        if (records.some(record => record.target.matches('.checklist-item')
            && (record.oldValue || '').split(/\s+/).includes('completed') !== record.target.classList.contains('completed'))) refresh();
    }).observe(checklist, {subtree: true, attributes: true, attributeFilter: ['class'], attributeOldValue: true});
    const button = document.getElementById('addChecklistWidget');
    if (button) {
        button.hidden = false;
        button.addEventListener('click', async () => {
            const status = document.getElementById('checklistWidgetStatus');
            try {
                const result = await widget.pin();
                status.textContent = result.requested
                    ? 'Confirm placement on your home screen. You can resize the widget afterward.'
                    : 'Touch and hold your home screen, choose Widgets, then Kinto · Daily checklist.';
            } catch (_) {
                status.textContent = 'Touch and hold your home screen, choose Widgets, then Kinto · Daily checklist.';
            }
        });
    }
    const section = new URLSearchParams(location.search).get('widget_log');
    const open = {water: 'showWaterModal', mood: 'showMoodModal', workout: 'showWorkoutModal'}[section];
    if (open && typeof window[open] === 'function') window[open]();
})();
