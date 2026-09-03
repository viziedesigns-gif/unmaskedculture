/**
 * Kinto App - JavaScript
 */

// ============================
// Utility Functions
// ============================

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatTime(utcString) {
    const date = new Date(utcString + (utcString.includes('Z') ? '' : 'Z'));
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + 
           ', ' + date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

// ============================
// Modal Management
// ============================

function addModalBranding() {
    document.querySelectorAll('.modal-content, .celebration-content').forEach(content => {
        if (content.querySelector(':scope > .modal-brand')) return;

        const brand = document.createElement('div');
        brand.className = 'modal-brand';
        brand.setAttribute('aria-hidden', 'true');
        brand.innerHTML = `
            <img src="/challenge/assets/kinto-favicon/kinto-app-icon-192.png" alt="">
            <span>Kinto</span>
        `;
        content.prepend(brand);
    });
}

function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(modal => {
            modal.classList.remove('active');
        });
        document.body.style.overflow = '';
    }
});

// Close modal on outside click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
        document.body.style.overflow = '';
    }
});

// ============================
// Flash Messages
// ============================

function showFlash(type, message) {
    const existing = document.querySelector('.flash-message');
    if (existing) {
        existing.remove();
    }
    
    const flash = document.createElement('div');
    flash.className = `flash-message flash-${type}`;
    flash.innerHTML = `
        <span class="flash-text">${escapeHtml(message)}</span>
        <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    document.body.appendChild(flash);

    if (window.KintoHaptics) {
        if (type === 'success') window.KintoHaptics.success();
        if (type === 'error') window.KintoHaptics.error();
        if (type === 'warning') window.KintoHaptics.warning();
    }
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (flash.parentElement) {
            flash.remove();
        }
    }, 5000);
}

// ============================
// API Helpers
// ============================

async function apiCall(url, data = null) {
    const options = {
        method: data ? 'POST' : 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    if (data) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.error || 'Request failed');
        }
        
        return result;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

// ============================
// Progress Ring Update
// ============================

function updateProgressRings() {
    document.querySelectorAll('.progress-ring[data-progress]').forEach(ring => {
        const progress = parseInt(ring.dataset.progress) || 0;
        ring.style.setProperty('--progress', progress);
    });
}

// Initialize progress rings on page load
document.addEventListener('DOMContentLoaded', function() {
    updateProgressRings();
    addModalBranding();
});

// ============================
// Password Toggle
// ============================

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    if (input) {
        input.type = input.type === 'password' ? 'text' : 'password';
    }
}

// ============================
// Countdown Timer
// ============================

function startCountdown(elementSelector, callback) {
    const element = document.querySelector(elementSelector);
    if (!element) return;
    
    const update = () => {
        const now = new Date();
        const midnight = new Date(now);
        midnight.setHours(24, 0, 0, 0);
        
        const diff = midnight - now;
        const hours = Math.floor(diff / 3600000);
        const minutes = Math.floor((diff % 3600000) / 60000);
        const seconds = Math.floor((diff % 60000) / 1000);
        
        element.textContent = 
            `${hours.toString().padStart(2, '0')}:` +
            `${minutes.toString().padStart(2, '0')}:` +
            `${seconds.toString().padStart(2, '0')} remaining`;
        
        // Check if we hit midnight
        if (hours === 0 && minutes === 0 && seconds === 0 && callback) {
            callback();
        }
    };
    
    update();
    setInterval(update, 1000);
}

// ============================
// Form Validation
// ============================

function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function validatePassword(password) {
    const errors = [];
    
    if (password.length < 8) {
        errors.push('At least 8 characters');
    }
    if (!/[A-Z]/.test(password)) {
        errors.push('One uppercase letter');
    }
    if (!/[a-z]/.test(password)) {
        errors.push('One lowercase letter');
    }
    if (!/[0-9]/.test(password)) {
        errors.push('One number');
    }
    
    return errors;
}

// ============================
// Image Preview
// ============================

function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (!preview || !input.files || !input.files[0]) return;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
    };
    reader.readAsDataURL(input.files[0]);
}

// ============================
// Clipboard
// ============================

async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        showFlash('success', 'Copied to clipboard!');
        return true;
    } catch (error) {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        
        try {
            document.execCommand('copy');
            showFlash('success', 'Copied to clipboard!');
            return true;
        } catch (e) {
            showFlash('error', 'Failed to copy');
            return false;
        } finally {
            document.body.removeChild(textarea);
        }
    }
}

// ============================
// Debounce
// ============================

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// ============================
// Local Storage Helpers
// ============================

const storage = {
    get(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch {
            return defaultValue;
        }
    },
    
    set(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch {
            return false;
        }
    },
    
    remove(key) {
        try {
            localStorage.removeItem(key);
            return true;
        } catch {
            return false;
        }
    }
};

// ============================
// Haptic Feedback
// ============================

const KINTO_HAPTICS_KEY = 'kintoHapticsEnabled';

const KintoHaptics = (() => {
    const vibrationPatterns = {
        light: 10,
        medium: 18,
        heavy: 28,
        success: [12, 40, 18],
        warning: [18, 45, 18],
        error: [28, 45, 28]
    };

    function isEnabled() {
        try {
            return localStorage.getItem(KINTO_HAPTICS_KEY) !== '0';
        } catch {
            return true;
        }
    }

    function getNativePlugin() {
        return window.Capacitor?.Plugins?.Haptics || null;
    }

    async function trigger(type = 'light') {
        if (!isEnabled()) return false;

        const nativeHaptics = getNativePlugin();
        try {
            if (nativeHaptics) {
                if (['success', 'warning', 'error'].includes(type) && typeof nativeHaptics.notification === 'function') {
                    await nativeHaptics.notification({ type: type.toUpperCase() });
                    return true;
                }

                if (typeof nativeHaptics.impact === 'function') {
                    const style = type === 'heavy' ? 'HEAVY' : type === 'medium' ? 'MEDIUM' : 'LIGHT';
                    await nativeHaptics.impact({ style });
                    return true;
                }
            }

            if (typeof navigator.vibrate === 'function') {
                return navigator.vibrate(vibrationPatterns[type] || vibrationPatterns.light);
            }
        } catch (error) {
            console.debug('Haptic feedback unavailable', error);
        }

        return false;
    }

    function setEnabled(enabled) {
        if (!enabled) trigger('light');
        try {
            localStorage.setItem(KINTO_HAPTICS_KEY, enabled ? '1' : '0');
        } catch {
            return false;
        }
        if (enabled) trigger('medium');
        window.dispatchEvent(new CustomEvent('kintoHapticsPreferenceChanged', { detail: { enabled } }));
        return true;
    }

    return {
        isEnabled,
        setEnabled,
        impact: (style = 'light') => trigger(style),
        success: () => trigger('success'),
        warning: () => trigger('warning'),
        error: () => trigger('error')
    };
})();

window.KintoHaptics = KintoHaptics;

document.addEventListener('pointerdown', event => {
    const control = event.target.closest('button, .btn, .bottom-nav-item, .settings-hub-row, .checklist-item');
    if (!control || control.matches(':disabled, [aria-disabled="true"], [data-haptic="none"]')) return;
    KintoHaptics.impact('light');
}, { passive: true });

document.addEventListener('submit', () => {
    KintoHaptics.impact('medium');
}, { capture: true });

// ============================
// PWA Install + Service Worker
// ============================

let deferredPwaInstallPrompt = null;

function isPwaStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

function isIosDevice() {
    return /iphone|ipad|ipod/i.test(window.navigator.userAgent) ||
        (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
}

function setPwaInstallMessage(message, isError = false) {
    const el = document.getElementById('pwaInstallHelpText');
    if (!el) return;
    el.textContent = message;
    el.style.color = isError ? 'var(--danger, #dc2626)' : 'var(--text-secondary)';
}

function updatePwaInstallUi() {
    const card = document.getElementById('pwaInstallCard');
    const btn = document.getElementById('installPwaBtn');
    const badge = document.getElementById('pwaInstallStatusBadge');
    const steps = document.getElementById('pwaIosInstallSteps');
    const installed = isPwaStandalone();

    if (card) {
        card.hidden = installed;
        card.classList.toggle('is-hidden', installed);
    }

    if (installed) {
        return;
    }

    if (badge) badge.textContent = 'Not installed';
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="download"></i> Download App';
    }

    if (steps) {
        steps.hidden = !isIosDevice();
    }

    if (deferredPwaInstallPrompt) {
        setPwaInstallMessage('Download Kinto to your home screen for a full-screen app experience.');
    } else if (isIosDevice()) {
        setPwaInstallMessage('Use Safari share, then Add to Home Screen to install Kinto.');
    } else {
        setPwaInstallMessage('If your browser supports installation, use Download App or your browser install menu.');
    }

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

async function installKintoApp() {
    if (isPwaStandalone()) {
        updatePwaInstallUi();
        return;
    }

    if (deferredPwaInstallPrompt) {
        deferredPwaInstallPrompt.prompt();
        const choice = await deferredPwaInstallPrompt.userChoice;
        deferredPwaInstallPrompt = null;
        if (choice.outcome === 'accepted') {
            setPwaInstallMessage('Installing Kinto...');
        } else {
            setPwaInstallMessage('Install canceled. You can try again from Settings or the browser menu.');
        }
        updatePwaInstallUi();
        return;
    }

    if (isIosDevice()) {
        setPwaInstallMessage('On iPhone or iPad, open this page in Safari, tap Share, then Add to Home Screen.');
    } else {
        setPwaInstallMessage('Your browser is not offering the install prompt yet. Try the browser menu and choose Install app or Add to Home screen.', true);
    }
}

window.installKintoApp = installKintoApp;

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPwaInstallPrompt = event;
    updatePwaInstallUi();
});

window.addEventListener('appinstalled', () => {
    deferredPwaInstallPrompt = null;
    updatePwaInstallUi();
});

if ('serviceWorker' in navigator) {
    const hadServiceWorkerController = !!navigator.serviceWorker.controller;
    let reloadingForServiceWorkerUpdate = false;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (!hadServiceWorkerController || reloadingForServiceWorkerUpdate) return;
        reloadingForServiceWorkerUpdate = true;
        window.location.reload();
    });

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/challenge/sw.php', {
            scope: '/challenge/',
            updateViaCache: 'none'
        })
            .then(registration => registration.update())
            .catch(error => console.log('SW registration failed', error));
    });
}

function reportNotificationPermission() {
    const supported = 'Notification' in window && 'serviceWorker' in navigator;
    const permission = supported ? Notification.permission : 'unsupported';
    fetch('/challenge/api/report_notification_status.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        body: JSON.stringify({supported, permission})
    }).catch(() => {});
}

window.addEventListener('load', reportNotificationPermission);

// ============================
// Initialize
// ============================

function initAppSplash() {
    const splash = document.getElementById('app-splash');
    const root = document.documentElement;
    if (!splash || !root.classList.contains('splash-pending')) {
        splash?.remove();
        return;
    }

    try {
        sessionStorage.setItem('kintoSplashSeen', '1');
    } catch (error) {
        // Storage can be unavailable in private browsing; the splash still exits safely.
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const canvas = splash.querySelector('canvas');
    const context = canvas?.getContext?.('2d');
    let frameId = 0;
    const startedAt = performance.now();
    const duration = reduceMotion ? 450 : 2100;

    const finish = () => {
        cancelAnimationFrame(frameId);
        splash.classList.add('app-splash--leaving');
        window.setTimeout(() => {
            root.classList.remove('splash-pending');
            splash.remove();
        }, reduceMotion ? 120 : 650);
    };

    if (!context || reduceMotion) {
        window.setTimeout(finish, duration);
        return;
    }

    const draw = (now) => {
        const width = canvas.clientWidth;
        const height = canvas.clientHeight;
        const ratio = Math.min(window.devicePixelRatio || 1, 2);
        if (canvas.width !== Math.round(width * ratio) || canvas.height !== Math.round(height * ratio)) {
            canvas.width = Math.round(width * ratio);
            canvas.height = Math.round(height * ratio);
        }
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        context.clearRect(0, 0, width, height);

        const progress = Math.min(1, (now - startedAt) / duration);
        const eased = 1 - Math.pow(1 - Math.min(progress * 1.45, 1), 3);
        const surface = height - (height * (0.18 + eased * 0.88));
        const phase = now * 0.0035;
        const gradient = context.createLinearGradient(0, surface, 0, height);
        gradient.addColorStop(0, 'rgba(232, 213, 163, .92)');
        gradient.addColorStop(1, '#C4A35A');
        context.fillStyle = gradient;
        context.beginPath();
        context.moveTo(0, height);
        context.lineTo(0, surface);
        for (let x = 0; x <= width + 12; x += 12) {
            const y = surface + Math.sin((x / Math.max(width, 1)) * 10 + phase) * 10
                + Math.sin((x / Math.max(width, 1)) * 19 - phase * 1.3) * 4;
            context.lineTo(x, y);
        }
        context.lineTo(width, height);
        context.closePath();
        context.fill();

        if (progress < 1) {
            frameId = requestAnimationFrame(draw);
        } else {
            finish();
        }
    };

    frameId = requestAnimationFrame(draw);
}

document.addEventListener('DOMContentLoaded', function() {
    initAppSplash();
    // Update progress rings
    updateProgressRings();
    updatePwaInstallUi();
    
    // Auto-focus first input in forms
    const firstInput = document.querySelector('form input:not([type="hidden"]):not([readonly])');
    if (firstInput && !firstInput.value) {
        // firstInput.focus(); // Uncomment if you want auto-focus
    }
    
    // Add loading state to forms
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                submitBtn.dataset.originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="loading-spinner"></span> Processing...';
            }
        });
    });
});

// Re-enable buttons on page show (for browser back button)
window.addEventListener('pageshow', function() {
    document.querySelectorAll('button[data-original-text]').forEach(btn => {
        btn.disabled = false;
        btn.innerHTML = btn.dataset.originalText;
        delete btn.dataset.originalText;
    });
});
