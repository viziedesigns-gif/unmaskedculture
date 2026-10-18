const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/reminders.js'), 'utf8');

async function harness(options = {}) {
    const calls = {requests: 0, schedules: [], cancels: [], subscribes: 0, unsubscribes: 0, fetches: []};
    let permission = options.permission || 'default';
    const stored = new Map(Object.entries(options.storage || {}));
    const subscription = {toJSON: () => ({endpoint: 'https://push.example.test/device'}), endpoint: 'https://push.example.test/device', unsubscribe: async () => { calls.unsubscribes++; }};
    const plugin = {
        checkPermissions: async () => ({display: permission === 'default' ? 'prompt' : permission}),
        requestPermissions: async () => { calls.requests++; permission = options.deny ? 'denied' : 'granted'; return {display: permission}; },
        addListener: async () => ({}), cancel: async value => { calls.cancels.push(value); },
        createChannel: async () => {}, schedule: async value => { calls.schedules.push(value); }
    };
    const context = {signedIn: options.signedIn !== false, userId: 7, enabled: options.enabled !== false, time: options.time || '07:30'};
    const sandbox = {
        console, Uint8Array, Date, atob,
        localStorage: {getItem: key => stored.get(key) || null, setItem: (key, value) => stored.set(key, value)},
        sessionStorage: {getItem: () => null, setItem: () => {}},
        document: {
            getElementById: id => id === 'kinto-reminder-context' ? {textContent: JSON.stringify(context)} : null,
            querySelector: () => null, documentElement: {classList: {contains: () => false}},
            addEventListener: () => {},
            body: {style: {}}, activeElement: null,
            createElement: () => { throw new Error('Permission dialog must not be created before the deferred introduction'); }
        },
        location: {pathname: '/challenge/app/dashboard.php'},
        navigator: {serviceWorker: {ready: Promise.resolve({pushManager: {
            getSubscription: async () => options.existingSubscription ? subscription : null,
            subscribe: async () => { calls.subscribes++; return subscription; }
        }})}},
        Notification: {get permission() { return permission; }, requestPermission: async () => { calls.requests++; permission = options.deny ? 'denied' : 'granted'; return permission; }},
        PushManager: function() {},
        fetch: async (url, opts) => {
            calls.fetches.push({url, opts});
            return {ok: !(options.saveFails && url.endsWith('push_subscribe.php')), json: async () => ({success: !(options.saveFails && url.endsWith('push_subscribe.php')), configured: !options.unconfigured, public_key: 'AQID'})};
        },
        setTimeout: () => 1, clearTimeout: () => {},
        addEventListener: () => {}, matchMedia: () => ({matches: false})
    };
    sandbox.window = sandbox;
    sandbox.Capacitor = {
        isNativePlatform: () => options.web !== true,
        isPluginAvailable: () => options.pluginMissing !== true,
        Plugins: {LocalNotifications: plugin}
    };
    vm.runInNewContext(source, sandbox);
    await new Promise(resolve => setImmediate(resolve));
    return {api: sandbox.KintoReminders, calls, stored};
}

(async () => {
    const fresh = await harness();
    assert.equal(fresh.calls.requests, 0, 'First launch must explain, never auto-request permission');
    assert.equal(fresh.calls.schedules.length, 0, 'No scheduling before consent');
    await fresh.api.enable();
    assert.equal(fresh.calls.requests, 1);
    const notification = fresh.calls.schedules[0].notifications[0];
    assert.equal(notification.id, 17001);
    assert.equal(notification.schedule.on.hour, 7);
    assert.equal(notification.schedule.on.minute, 30);
    assert.equal(notification.schedule.repeats, true);
    assert.equal(notification.isExactNotification, false, 'Wellness reminders must not ask for exact-alarm access');
    assert.equal(fresh.stored.get('kintoReminderIntroV1'), 'enabled');
    await fresh.api.disable();
    assert.equal(fresh.stored.get('kintoRemindersOff'), '1');
    assert.deepEqual(Array.from(fresh.calls.cancels.at(-1).notifications, value => value.id), [17001, 17002]);
    const count = fresh.calls.schedules.length;
    await fresh.api.syncNative();
    assert.equal(fresh.calls.schedules.length, count, 'Device opt-out survives page loads');

    const denied = await harness({deny: true});
    await assert.rejects(() => denied.api.enable(), /Notifications are off/);
    assert.equal(denied.calls.schedules.length, 0);

    const logout = await harness({permission: 'granted', signedIn: false});
    assert.equal(logout.calls.schedules.length, 0, 'Signed-out devices must not schedule reminders');
    assert.ok(logout.calls.cancels.length, 'Logout must clear the existing daily reminder');

    const disabled = await harness({permission: 'granted', enabled: false});
    assert.equal(disabled.calls.schedules.length, 0, 'Account preferences must be respected');

    const returning = await harness({permission: 'granted', time: '21:15'});
    assert.equal(returning.calls.requests, 0, 'Existing permission must not be requested again');
    assert.equal(returning.calls.schedules[0].notifications[0].schedule.on.hour, 21);

    const oldApp = await harness({pluginMissing: true});
    await assert.rejects(() => oldApp.api.enable(), /Update Kinto/);
    assert.equal(oldApp.calls.requests, 0, 'Old APKs must not fall back to unsupported WebView push');

    const web = await harness({web: true});
    await web.api.enable();
    assert.equal(web.calls.requests, 1);
    assert.equal(web.calls.subscribes, 1);
    assert.ok(web.calls.fetches.some(call => call.url.endsWith('push_subscribe.php')));

    const rollback = await harness({web: true, saveFails: true});
    await assert.rejects(() => rollback.api.enable(), /couldn’t save/);
    assert.equal(rollback.calls.unsubscribes, 1, 'A failed save must undo a newly-created browser subscription');

    const unavailable = await harness({web: true, unconfigured: true});
    await assert.rejects(() => unavailable.api.enable(), /aren’t available/);
    assert.equal(unavailable.calls.subscribes, 0);
    console.log('PASS reminders: consent, denial, scheduling, preferences, logout, old APKs, browser push and rollback');
})().catch(error => { console.error(error); process.exitCode = 1; });
