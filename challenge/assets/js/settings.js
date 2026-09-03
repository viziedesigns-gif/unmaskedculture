/**
 * Settings sub-page helpers.
 */

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

function setPushMessage(message, isError = false) {
    const el = document.getElementById('pushHelpText');
    if (!el) return;
    el.textContent = message;
    el.style.color = isError ? 'var(--danger, #dc2626)' : 'var(--text-secondary)';
}

function updatePushButtons(enabled) {
    const testBtn = document.getElementById('testPushBtn');
    const disableBtn = document.getElementById('disablePushBtn');
    const badge = document.getElementById('pushStatusBadge');
    if (testBtn) testBtn.disabled = !enabled;
    if (disableBtn) disableBtn.disabled = !enabled;
    if (badge) badge.textContent = enabled ? 'Enabled on this device' : 'Not enabled';
}

async function getPushRegistration() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        throw new Error('Push notifications are not supported by this browser.');
    }
    return await navigator.serviceWorker.ready;
}

async function enablePushNotifications() {
    try {
        const configResponse = await fetch('/challenge/api/push_config.php', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const config = await configResponse.json();
        if (!config.success || !config.configured || !config.public_key) {
            throw new Error('Push is not configured on the server yet.');
        }

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            throw new Error('Notification permission was not granted.');
        }

        const registration = await getPushRegistration();
        let subscription = await registration.pushManager.getSubscription();
        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(config.public_key)
            });
        }

        const response = await fetch('/challenge/api/push_subscribe.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(subscription.toJSON())
        });
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.error || 'Unable to save subscription.');
        }

        updatePushButtons(true);
        setPushMessage('Notifications are enabled for this device.');
        if (typeof reportNotificationPermission === 'function') reportNotificationPermission();
        if (typeof lucide !== 'undefined') lucide.createIcons();
    } catch (error) {
        setPushMessage(error.message || 'Unable to enable notifications.', true);
    }
}

async function disablePushNotifications() {
    try {
        const registration = await getPushRegistration();
        const subscription = await registration.pushManager.getSubscription();
        if (subscription) {
            await fetch('/challenge/api/push_unsubscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ endpoint: subscription.endpoint })
            });
            await subscription.unsubscribe();
        }
        updatePushButtons(false);
        setPushMessage('Notifications are disabled for this device.');
        if (typeof reportNotificationPermission === 'function') reportNotificationPermission();
    } catch (error) {
        setPushMessage(error.message || 'Unable to disable notifications.', true);
    }
}

async function sendTestPush() {
    try {
        const response = await fetch('/challenge/api/push_test.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.error || 'Unable to send test notification.');
        }
        setPushMessage('Test notification sent.');
    } catch (error) {
        setPushMessage(error.message || 'Unable to send test notification.', true);
    }
}

window.enablePushNotifications = enablePushNotifications;
window.disablePushNotifications = disablePushNotifications;
window.sendTestPush = sendTestPush;

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

document.addEventListener('DOMContentLoaded', async () => {
    if (!document.getElementById('pushHelpText')) return;

    try {
        const registration = await getPushRegistration();
        const subscription = await registration.pushManager.getSubscription();
        updatePushButtons(!!subscription);
    } catch (error) {
        updatePushButtons(false);
    }
});
