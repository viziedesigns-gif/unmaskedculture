/* One permission introduction, with native daily reminders and browser push. */
(() => {
    'use strict';
    const DAILY_ID = 17001, TEST_ID = 17002;
    const INTRO_KEY = 'kintoReminderIntroV1', DEVICE_OFF_KEY = 'kintoRemindersOff';
    const native = window.Capacitor?.isNativePlatform?.() === true;
    const plugin = native && window.Capacitor?.isPluginAvailable?.('LocalNotifications')
        ? window.Capacitor.Plugins.LocalNotifications : null;
    const contextElement = document.getElementById('kinto-reminder-context');
    let context = {signedIn: false, enabled: true, time: '18:00'};
    try { if (contextElement) context = {...context, ...JSON.parse(contextElement.textContent)}; } catch (_) {}
    const storage = {
        get(key) { try { return localStorage.getItem(key); } catch (_) { try { return sessionStorage.getItem(key); } catch (_) { return null; } } },
        set(key, value) { try { localStorage.setItem(key, value); } catch (_) { try { sessionStorage.setItem(key, value); } catch (_) {} } }
    };
    const supported = native ? !!plugin : 'Notification' in window && 'PushManager' in window && 'serviceWorker' in navigator;
    let overlay, previousFocus, syncing = null;

    async function permission() {
        if (!supported) return 'unsupported';
        if (plugin) {
            const result = await plugin.checkPermissions();
            return result.display === 'granted' ? 'granted' : result.display === 'denied' ? 'denied' : 'default';
        }
        return Notification.permission;
    }

    async function report() {
        if (!context.signedIn) return;
        try {
            const state = await permission();
            await fetch('/challenge/api/report_notification_status.php', {
                method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({supported, permission: state})
            });
        } catch (_) {}
    }

    function fromBase64(value) {
        const normalized = value.replace(/-/g, '+').replace(/_/g, '/');
        return Uint8Array.from(atob(normalized + '='.repeat((4 - normalized.length % 4) % 4)), char => char.charCodeAt(0));
    }

    async function requestJson(url, options = {}) {
        const response = await fetch(url, {credentials: 'same-origin', ...options,
            headers: {'X-Requested-With': 'XMLHttpRequest', ...options.headers}});
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error('We couldn’t save your notification settings. Please try again.');
        return data;
    }

    async function registration() {
        if (!supported || native) throw new Error('Install Kinto or use a browser that supports notifications.');
        let timeout;
        try {
            return await Promise.race([
                navigator.serviceWorker.ready,
                new Promise((_, reject) => { timeout = setTimeout(() => reject(new Error('Reminders are taking a moment to connect. Please try again.')), 8000); })
            ]);
        } finally { clearTimeout(timeout); }
    }

    async function syncNative() {
        if (!plugin) return;
        if (syncing) return syncing;
        syncing = (async () => {
            // Cancel only notifications owned by this feature, including on logout.
            await plugin.cancel({notifications: context.signedIn ? [{id: DAILY_ID}] : [{id: DAILY_ID}, {id: TEST_ID}]});
            if (!context.signedIn || !context.enabled || storage.get(DEVICE_OFF_KEY) === '1' || await permission() !== 'granted') return;
            const match = /^(\d{2}):(\d{2})$/.exec(context.time || '');
            const hour = match && Number(match[1]) < 24 ? Number(match[1]) : 18;
            const minute = match && Number(match[2]) < 60 ? Number(match[2]) : 0;
            await plugin.createChannel({id: 'kinto-daily', name: 'Daily reminders', description: 'A gentle nudge for your daily rhythm', importance: 3, visibility: 0});
            await plugin.schedule({notifications: [{
                id: DAILY_ID, title: 'A little time for you',
                body: 'Your daily rhythm is here. Take one small step with Kinto.',
                channelId: 'kinto-daily', smallIcon: 'ic_kinto_notification', iconColor: '#B99A59',
                schedule: {on: {hour, minute}, repeats: true}, isExactNotification: false
            }]});
        })();
        try { await syncing; } finally { syncing = null; }
    }

    async function enable() {
        if (!supported) throw new Error(native ? 'Update Kinto to enable reminders on this device.' : 'On iPhone, add Kinto to your Home Screen in Safari first. Otherwise, use a browser that supports notifications.');
        // Called directly from the user's tap, preserving browser user activation.
        const result = plugin ? await plugin.requestPermissions() : await Notification.requestPermission();
        const granted = plugin ? result.display === 'granted' : result === 'granted';
        if (!granted) throw new Error('Notifications are off. You can allow them in your device or browser settings whenever you’re ready.');
        storage.set(DEVICE_OFF_KEY, '0');
        if (plugin) {
            await syncNative();
        } else {
            if (!context.signedIn) throw new Error('Sign in to finish setting up your reminders.');
            const config = await requestJson('/challenge/api/push_config.php');
            if (!config.configured || !config.public_key) throw new Error('Reminders aren’t available right now. Please try again later.');
            const worker = await registration();
            let subscription = await worker.pushManager.getSubscription();
            const created = !subscription;
            if (!subscription) subscription = await worker.pushManager.subscribe({userVisibleOnly: true, applicationServerKey: fromBase64(config.public_key)});
            try {
                await requestJson('/challenge/api/push_subscribe.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(subscription.toJSON())});
            } catch (error) {
                if (created) await subscription.unsubscribe().catch(() => {});
                throw error;
            }
        }
        storage.set(INTRO_KEY, 'enabled');
        await refreshSettings();
        report();
        return !context.signedIn ? 'Notifications are allowed. Sign in to set up your daily reminder.'
            : !context.enabled ? 'Notifications are allowed. Turn on the daily reminder above to start receiving it.'
            : plugin ? 'Your daily reminder is set for ' + context.time + ', in this device’s timezone.' : 'Notifications are enabled on this device.';
    }

    async function disable() {
        storage.set(DEVICE_OFF_KEY, '1');
        if (plugin) {
            await plugin.cancel({notifications: [{id: DAILY_ID}, {id: TEST_ID}]});
        } else if (supported) {
            const worker = await registration();
            const subscription = await worker.pushManager.getSubscription();
            if (subscription) {
                await requestJson('/challenge/api/push_unsubscribe.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({endpoint: subscription.endpoint})});
                await subscription.unsubscribe();
            }
        }
        await refreshSettings();
        return 'Reminders are off on this device. You can turn them back on here.';
    }

    async function test() {
        if (plugin) {
            if (await permission() !== 'granted') throw new Error('Enable notifications on this device first.');
            await plugin.schedule({notifications: [{id: TEST_ID, title: 'Your Kinto reminders are ready', body: 'A little support for your daily rhythm.', smallIcon: 'ic_kinto_notification', isExactNotification: false, schedule: {at: new Date(Date.now() + 3000)}}]});
            return 'A test reminder will arrive in a few seconds.';
        }
        await requestJson('/challenge/api/push_test.php', {method: 'POST'});
        return 'Test notification sent.';
    }

    async function refreshSettings() {
        const badge = document.getElementById('pushStatusBadge');
        if (!badge) return;
        let enabled = false;
        try {
            if (plugin) enabled = await permission() === 'granted' && storage.get(DEVICE_OFF_KEY) !== '1';
            else if (supported) enabled = !!(await (await registration()).pushManager.getSubscription());
        } catch (_) {}
        badge.textContent = enabled ? 'Allowed on this device' : 'Not enabled on this device';
        for (const id of ['testPushBtn', 'disablePushBtn']) {
            const button = document.getElementById(id);
            if (button) button.disabled = !enabled;
        }
        const help = document.getElementById('pushHelpText');
        if (help && plugin) help.textContent = 'Daily reminders follow this device’s clock and work when Kinto is closed. Android may deliver them near your chosen time.';
    }

    function close() {
        if (!overlay) return;
        overlay.hidden = true;
        for (const [element, wasInert] of overlay._inertSiblings || []) element.inert = wasInert;
        overlay._inertSiblings = [];
        document.body.style.overflow = '';
        previousFocus?.focus?.();
    }

    function open() {
        if (overlay && !overlay.hidden) return;
        if (!overlay) {
            overlay = document.createElement('section');
            overlay.className = 'reminder-intro';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-labelledby', 'reminderIntroTitle');
            overlay.setAttribute('aria-describedby', 'reminderIntroDescription');
            overlay.innerHTML = `<div class="reminder-intro__content">
                <div class="reminder-art" aria-hidden="true"><svg viewBox="0 0 48 48"><path d="M12 31h24l-4-6V17a8 8 0 0 0-16 0v8zM20 37a4 4 0 0 0 8 0M24 5V2M9 9l-3-3M39 9l3-3" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                <span class="kinto-eyebrow">A little support, every day</span>
                <h2 id="reminderIntroTitle">Make time for what matters.</h2>
                <p class="reminder-intro__lead" id="reminderIntroDescription">Life gets busy. A gentle reminder can help you come back to yourself—and the small habits you’re building.</p>
                <ul class="reminder-benefits"><li><span aria-hidden="true">01</span><span>A nudge to check in, move, or reflect.</span></li><li><span aria-hidden="true">02</span><span>A daily rhythm that fits your life.</span></li><li><span aria-hidden="true">03</span><span>Your time, your choice. Change it in Settings.</span></li></ul>
                <button type="button" class="btn btn-primary" data-reminder-enable>Enable reminders</button>
                <button type="button" class="reminder-later" data-reminder-later>Maybe later</button>
                <p class="reminder-result" role="status" hidden></p>
                <p class="reminder-privacy">You’re always in control. Kinto works with reminders off, too.</p>
            </div>`;
            document.body.appendChild(overlay);
            overlay.querySelector('[data-reminder-later]').addEventListener('click', () => {
                storage.set(INTRO_KEY, 'later'); close();
            });
            overlay.querySelector('[data-reminder-enable]').addEventListener('click', async event => {
                const button = event.currentTarget, result = overlay.querySelector('.reminder-result');
                button.disabled = true;
                try {
                    result.textContent = await enable();
                    button.hidden = true;
                    overlay.querySelector('[data-reminder-later]').textContent = 'Continue to Kinto';
                    overlay.querySelector('[data-reminder-later]').focus();
                } catch (error) {
                    result.textContent = error.message || 'We couldn’t enable reminders. Please try again.';
                    button.disabled = false;
                }
                result.hidden = false;
            });
            overlay.addEventListener('keydown', event => {
                if (event.key === 'Escape') { storage.set(INTRO_KEY, 'later'); close(); }
                if (event.key !== 'Tab') return;
                const controls = [...overlay.querySelectorAll('button')].filter(button => !button.disabled && !button.hidden);
                const first = controls[0], last = controls[controls.length - 1];
                if (event.shiftKey && (document.activeElement === first || !overlay.contains(document.activeElement))) { event.preventDefault(); last?.focus(); }
                else if (!event.shiftKey && (document.activeElement === last || !overlay.contains(document.activeElement))) { event.preventDefault(); first?.focus(); }
            });
        }
        previousFocus = document.activeElement;
        overlay.hidden = false;
        overlay._inertSiblings = [...document.body.children].filter(element => element !== overlay).map(element => [element, element.inert]);
        for (const [element] of overlay._inertSiblings) element.inert = true;
        document.body.style.overflow = 'hidden';
        overlay.querySelector('[data-reminder-enable]').focus();
    }

    async function init() {
        if (plugin) {
            await plugin.addListener('localNotificationActionPerformed', () => { window.location.assign('/challenge/app/dashboard.php'); });
            await syncNative().catch(() => {});
        }
        await refreshSettings();
        report();
        const installed = native || window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
        if (!installed || !supported || storage.get(INTRO_KEY) || (!native && !context.signedIn)) return;
        if (await permission() === 'granted') return;
        // Intro never interrupts another dialog or an onboarding form.
        const showWhenReady = () => {
            if (document.querySelector('.modal.active, .podcast-player-overlay:not([hidden])') || document.documentElement.classList.contains('splash-pending')) {
                setTimeout(showWhenReady, 500); return;
            }
            if (!location.pathname.includes('/onboarding/')) open();
        };
        setTimeout(showWhenReady, 650);
    }

    window.KintoReminders = {enable, disable, test, open, close, report, refreshSettings, syncNative};
    window.addEventListener('appinstalled', () => { if (supported && context.signedIn && !storage.get(INTRO_KEY)) open(); });
    window.addEventListener('pageshow', () => { if (plugin) syncNative().catch(() => {}); });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) return;
        if (plugin) syncNative().catch(() => {});
        refreshSettings().catch(() => {});
        report();
    });
    init().catch(() => {});
})();
