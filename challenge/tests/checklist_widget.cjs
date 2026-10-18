const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/checklist-widget.js'), 'utf8');

function harness(native = true, requested = true, section = '') {
    const calls = {refresh: 0, pin: 0, logs: 0};
    let queued;
    let observer;
    const button = {hidden: true, addEventListener: (_, fn) => { button.click = fn; }};
    const status = {textContent: ''};
    const checklist = {};
    const context = {
        URLSearchParams, location: {search: section ? '?widget_log=' + section : ''},
        setTimeout: fn => { queued = fn; return 1; }, clearTimeout: () => { queued = null; },
        document: {hidden: false, addEventListener() {}, getElementById: id => ({dailyRhythm: checklist, addChecklistWidget: button, checklistWidgetStatus: status})[id]},
        MutationObserver: class { constructor(fn) { observer = fn; } observe() {} },
        window: {Capacitor: {isPluginAvailable: () => native, Plugins: {ChecklistWidget: {
            refresh: async () => { calls.refresh++; }, pin: async () => { calls.pin++; return {requested}; }
        }}}, showWaterModal: () => { calls.logs++; }}
    };
    vm.runInNewContext(source, context);
    return {calls, button, status, flush: () => { const fn = queued; queued = null; fn?.(); }, mutate: records => observer(records)};
}

(async () => {
    const browser = harness(false);
    assert.equal(browser.button.hidden, true);
    browser.flush();
    assert.equal(browser.calls.refresh, 0);
    const native = harness(true, true, 'water');
    native.flush();
    assert.equal(native.button.hidden, false);
    assert.equal(native.calls.logs, 1);
    assert.equal(native.calls.refresh, 1);
    native.mutate([{target: {matches: () => false}, oldValue: ''}]);
    native.flush();
    assert.equal(native.calls.refresh, 1, 'Countdown changes must not fetch tasks every second');
    native.mutate([{target: {matches: () => true, classList: {contains: () => true}}, oldValue: 'checklist-item'}]);
    native.flush();
    assert.equal(native.calls.refresh, 2);
    await native.button.click();
    assert.match(native.status.textContent, /Confirm placement/);
    const fallback = harness(true, false);
    await fallback.button.click();
    assert.match(fallback.status.textContent, /Touch and hold/);
    console.log('Widget bridge, completion sync, countdown isolation, activity links, and pin fallback passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
