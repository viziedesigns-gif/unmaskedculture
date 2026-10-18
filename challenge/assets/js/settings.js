/**
 * Settings sub-page helpers.
 */

function setPushMessage(message, isError = false) {
    const el = document.getElementById('pushHelpText');
    if (!el) return;
    el.textContent = message;
    el.style.color = isError ? 'var(--danger)' : 'var(--text-secondary)';
}

async function reminderAction(action) {
    const enable = document.getElementById('enablePushBtn');
    if (enable) enable.disabled = true;
    try {
        if (!window.KintoReminders) throw new Error('Reminders are still loading. Please try again.');
        setPushMessage(await window.KintoReminders[action]());
    } catch (error) {
        setPushMessage(error.message || 'Unable to update reminders.', true);
    } finally {
        if (enable) enable.disabled = false;
    }
}
window.enablePushNotifications = () => reminderAction('enable');
window.disablePushNotifications = () => reminderAction('disable');
window.sendTestPush = () => reminderAction('test');

function openRestartModal() {
    const m = document.getElementById('restartModal');
    if (!m) return;
    const input = document.getElementById('restartConfirm');
    const btn = document.getElementById('restartSubmitBtn');
    if (input) input.value = '';
    if (btn) btn.disabled = true;
    m.classList.add('active');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function closeRestartModal() {
    const m = document.getElementById('restartModal');
    if (m) m.classList.remove('active');
}

function validateRestartConfirm(value) {
    const btn = document.getElementById('restartSubmitBtn');
    if (!btn) return;
    btn.disabled = (value.trim().toUpperCase() !== 'RESTART');
}

function openDeleteAccountModal() {
    const m = document.getElementById('deleteAccountModal');
    if (!m) return;
    const input = document.getElementById('deleteAccountConfirm');
    const btn = document.getElementById('deleteAccountSubmitBtn');
    if (input) input.value = '';
    if (btn) btn.disabled = true;
    m.classList.add('active');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function closeDeleteAccountModal() {
    const m = document.getElementById('deleteAccountModal');
    if (m) m.classList.remove('active');
}

function validateDeleteAccountConfirm(value) {
    const btn = document.getElementById('deleteAccountSubmitBtn');
    if (!btn) return;
    btn.disabled = (value.trim() !== 'DELETE ACCOUNT');
}

document.getElementById('restartModal')?.addEventListener('click', (e) => {
    if (e.target.id === 'restartModal') closeRestartModal();
});

document.getElementById('deleteAccountModal')?.addEventListener('click', (e) => {
    if (e.target.id === 'deleteAccountModal') closeDeleteAccountModal();
});

(function initBottleToggle() {
    const wrap = document.querySelector('.bottle-toggle');
    if (!wrap) return;

    const hiddenValue = document.getElementById('water_bottle_oz');
    const hiddenMode = document.getElementById('bottle_mode');
    const customWrap = document.querySelector('.bottle-custom-wrap');
    const customInput = document.getElementById('water_bottle_custom');
    const presetBtns = wrap.querySelectorAll('[data-bottle-preset]');
    const customBtn = wrap.querySelector('[data-bottle-custom]');

    function setActive(btn) {
        wrap.querySelectorAll('.bottle-toggle-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
    }

    presetBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const val = parseInt(btn.dataset.bottlePreset, 10);
            hiddenValue.value = val;
            hiddenMode.value = 'preset';
            setActive(btn);
            customWrap.hidden = true;
        });
    });

    customBtn.addEventListener('click', () => {
        hiddenMode.value = 'custom';
        setActive(customBtn);
        customWrap.hidden = false;
        const cur = parseInt(customInput.value, 10);
        if (!cur || isNaN(cur)) {
            customInput.value = hiddenValue.value || 24;
        }
        hiddenValue.value = customInput.value;
        customInput.focus();
        customInput.select();
    });

    customInput.addEventListener('input', () => {
        const v = parseInt(customInput.value, 10);
        if (!isNaN(v) && v >= 1 && v <= 128) {
            hiddenValue.value = v;
        }
    });
})();

document.addEventListener('DOMContentLoaded', () => {
    window.KintoReminders?.refreshSettings();
});
