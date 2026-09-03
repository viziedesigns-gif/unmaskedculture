<?php
$pageTitle = 'Appearance';
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/settings_layout.php';

$bubbleColor = $user['chat_bubble_color'] ?? '#1C1917';
include __DIR__ . '/../../includes/header.php';
?>

<div class="profile-page settings-detail-page">
    <?php renderSettingsBackNav('Appearance'); ?>
    <?php renderSettingsAlerts($error, $success); ?>

    <div class="settings-card settings-detail-card">
        <form method="POST" class="settings-detail-form">
            <input type="hidden" name="action" value="update_appearance">

            <div class="color-picker-section">
                <label for="bubbleColorPicker">Your Chat Bubble Color</label>
                <p class="form-hint">This color appears on your messages in circle chats.</p>
                <div class="color-picker-row">
                    <input type="color" id="bubbleColorPicker" name="bubble_color" value="<?= h($bubbleColor) ?>">
                    <span class="color-preview" style="background-color: <?= h($bubbleColor) ?>"></span>
                    <span class="color-label"><?= h(strtoupper($bubbleColor)) ?></span>
                </div>
            </div>

            <div class="appearance-device-card">
                <div class="appearance-device-row">
                    <div>
                        <h3>Haptic Feedback</h3>
                        <p>Feel light taps for controls and stronger feedback for important actions.</p>
                    </div>
                    <button type="button" id="hapticsPreference" class="device-preference-control" aria-pressed="true" data-haptic="none">
                        <i data-lucide="vibrate"></i>
                        <span>Haptics on</span>
                    </button>
                </div>
                <p class="form-hint" id="hapticsHint">Haptics are enabled on this device.</p>
            </div>

            <div class="appearance-device-card">
                <div class="appearance-device-row">
                    <div>
                        <h3>Water Tilt</h3>
                        <p>Change tilt here to let this device's motion sensor move the water tracker.</p>
                    </div>
                    <button type="button" id="waterTiltPreference" class="device-preference-control" aria-pressed="false">
                        <i data-lucide="move-3d"></i>
                        <span>Enable tilt</span>
                    </button>
                </div>
                <p class="form-hint" id="waterTiltHint">This setting is saved on this phone or browser.</p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('bubbleColorPicker')?.addEventListener('input', function(e) {
    const preview = document.querySelector('.color-preview');
    const label = document.querySelector('.color-label');
    if (preview) preview.style.backgroundColor = e.target.value;
    if (label) label.textContent = e.target.value.toUpperCase();
});

const tiltPreferenceButton = document.getElementById('waterTiltPreference');
const tiltPreferenceHint = document.getElementById('waterTiltHint');
const hapticsPreferenceButton = document.getElementById('hapticsPreference');
const hapticsPreferenceHint = document.getElementById('hapticsHint');

function setHapticsPreferenceState(enabled) {
    if (!hapticsPreferenceButton) return;
    hapticsPreferenceButton.setAttribute('aria-pressed', enabled ? 'true' : 'false');
    hapticsPreferenceButton.classList.toggle('is-enabled', enabled);
    const label = hapticsPreferenceButton.querySelector('span');
    if (label) label.textContent = enabled ? 'Haptics on' : 'Haptics off';
    if (hapticsPreferenceHint) {
        hapticsPreferenceHint.textContent = enabled
            ? 'Haptics are enabled on this device.'
            : 'Haptics are disabled on this device.';
    }
}

hapticsPreferenceButton?.addEventListener('click', function() {
    const currentlyEnabled = localStorage.getItem('kintoHapticsEnabled') !== '0';
    const enabled = !currentlyEnabled;
    if (window.KintoHaptics) {
        window.KintoHaptics.setEnabled(enabled);
    } else {
        localStorage.setItem('kintoHapticsEnabled', enabled ? '1' : '0');
    }
    setHapticsPreferenceState(enabled);
});

function setTiltPreferenceState(enabled, message) {
    if (!tiltPreferenceButton) return;
    tiltPreferenceButton.setAttribute('aria-pressed', enabled ? 'true' : 'false');
    tiltPreferenceButton.classList.toggle('is-enabled', enabled);
    tiltPreferenceButton.classList.remove('is-denied');
    const label = tiltPreferenceButton.querySelector('span');
    if (label) label.textContent = enabled ? 'Tilt enabled' : 'Enable tilt';
    if (tiltPreferenceHint && message) tiltPreferenceHint.textContent = message;
}

async function enableWaterTiltPreference() {
    if (!('DeviceOrientationEvent' in window)) {
        if (tiltPreferenceHint) tiltPreferenceHint.textContent = 'This device does not expose motion controls.';
        return false;
    }

    try {
        if (typeof DeviceOrientationEvent.requestPermission === 'function') {
            const permission = await DeviceOrientationEvent.requestPermission();
            if (permission !== 'granted') {
                tiltPreferenceButton?.classList.add('is-denied');
                if (tiltPreferenceHint) tiltPreferenceHint.textContent = 'Motion permission was not granted for this device.';
                return false;
            }
        }
        localStorage.setItem('waterTiltEnabled', '1');
        setTiltPreferenceState(true, 'Water tilt is enabled on this device.');
        window.dispatchEvent(new CustomEvent('waterTiltPreferenceChanged', { detail: { enabled: true } }));
        return true;
    } catch (error) {
        tiltPreferenceButton?.classList.add('is-denied');
        if (tiltPreferenceHint) tiltPreferenceHint.textContent = 'Motion controls could not be enabled. Try again from this device.';
        return false;
    }
}

tiltPreferenceButton?.addEventListener('click', async function() {
    const enabled = localStorage.getItem('waterTiltEnabled') === '1';
    if (enabled) {
        localStorage.removeItem('waterTiltEnabled');
        setTiltPreferenceState(false, 'Water tilt is disabled on this device.');
        window.dispatchEvent(new CustomEvent('waterTiltPreferenceChanged', { detail: { enabled: false } }));
        return;
    }
    await enableWaterTiltPreference();
});

try {
    setHapticsPreferenceState(localStorage.getItem('kintoHapticsEnabled') !== '0');
    setTiltPreferenceState(
        localStorage.getItem('waterTiltEnabled') === '1',
        localStorage.getItem('waterTiltEnabled') === '1'
            ? 'Water tilt is enabled on this device.'
            : 'This setting is saved on this phone or browser.'
    );
} catch (error) {
    setHapticsPreferenceState(true);
    setTiltPreferenceState(false, 'This setting is saved on this phone or browser.');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
