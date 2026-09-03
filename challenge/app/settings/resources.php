<?php
$pageTitle = 'Mental Health Resources';
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/settings_layout.php';

if (empty($_SESSION['newsletter_csrf_token'])) {
    $_SESSION['newsletter_csrf_token'] = bin2hex(random_bytes(32));
}

$newsletterCsrfToken = $_SESSION['newsletter_csrf_token'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="profile-page settings-detail-page">
    <?php renderSettingsBackNav('Mental Health Resources'); ?>
    <?php renderSettingsAlerts($error, $success); ?>

    <section class="settings-card settings-detail-card">
        <h3>Videos</h3>
        <div class="settings-hub-list">
            <a href="/challenge/app/settings/videos.php" class="settings-hub-row">
                <span class="settings-hub-row-icon" aria-hidden="true"><i data-lucide="circle-play"></i></span>
                <span class="settings-hub-row-copy">
                    <span class="settings-hub-row-label">Unmasked Culture Productions</span>
                    <span class="settings-hub-row-subtitle">Watch productions inside the app</span>
                </span>
                <i data-lucide="chevron-right" class="settings-hub-row-chevron"></i>
            </a>
        </div>
    </section>

    <section class="settings-card settings-detail-card">
        <h3>Podcasts</h3>
        <div class="settings-hub-list">
            <a href="/challenge/app/settings/podcast.php" class="settings-hub-row">
                <span class="settings-hub-row-icon" aria-hidden="true"><i data-lucide="podcast"></i></span>
                <span class="settings-hub-row-copy">
                    <span class="settings-hub-row-label">The Unmasked Podcast</span>
                    <span class="settings-hub-row-subtitle">Listen with the in-app podcast player</span>
                </span>
                <i data-lucide="chevron-right" class="settings-hub-row-chevron"></i>
            </a>
            <a href="https://open.spotify.com/show/4oU7M1LWyn87ooVcC5i1P1" target="_blank" rel="noopener noreferrer" class="settings-hub-row">
                <span class="settings-hub-row-icon" aria-hidden="true"><i data-lucide="music"></i></span>
                <span class="settings-hub-row-copy">
                    <span class="settings-hub-row-label">Spotify</span>
                    <span class="settings-hub-row-subtitle">Listen on Spotify</span>
                </span>
                <i data-lucide="external-link" class="settings-hub-row-chevron"></i>
            </a>
            <a href="https://podcasts.apple.com/us/podcast/the-unmasked-podcast/id1746422573" target="_blank" rel="noopener noreferrer" class="settings-hub-row">
                <span class="settings-hub-row-icon" aria-hidden="true"><i data-lucide="podcast"></i></span>
                <span class="settings-hub-row-copy">
                    <span class="settings-hub-row-label">Apple Podcasts</span>
                    <span class="settings-hub-row-subtitle">Listen on Apple Podcasts</span>
                </span>
                <i data-lucide="external-link" class="settings-hub-row-chevron"></i>
            </a>
            <a href="https://music.amazon.com/podcasts/8236fb23-eafa-4375-9716-edf7e0717e54" target="_blank" rel="noopener noreferrer" class="settings-hub-row">
                <span class="settings-hub-row-icon" aria-hidden="true"><i data-lucide="headphones"></i></span>
                <span class="settings-hub-row-copy">
                    <span class="settings-hub-row-label">Amazon Music</span>
                    <span class="settings-hub-row-subtitle">Listen on Amazon Music</span>
                </span>
                <i data-lucide="external-link" class="settings-hub-row-chevron"></i>
            </a>
        </div>
    </section>

    <section class="settings-card settings-detail-card">
        <h3>Connect With Kinto</h3>
        <div class="settings-hub-list">
            <?php
            $socialLinks = [
                ['https://www.facebook.com/unmaskedculture', 'thumbs-up', 'Facebook'],
                ['https://www.instagram.com/unmaskedculture', 'camera', 'Instagram'],
                ['https://www.twitter.com/unaboringstory', 'message-circle', 'X'],
                ['https://www.youtube.com/@unmaskedculture', 'circle-play', 'YouTube'],
                ['https://www.linkedin.com/company/unmasked-culture', 'briefcase-business', 'LinkedIn'],
            ];
            foreach ($socialLinks as [$href, $icon, $label]): ?>
                <a href="<?= h($href) ?>" target="_blank" rel="noopener noreferrer" class="settings-hub-row">
                    <span class="settings-hub-row-icon" aria-hidden="true"><i data-lucide="<?= h($icon) ?>"></i></span>
                    <span class="settings-hub-row-copy">
                        <span class="settings-hub-row-label"><?= h($label) ?></span>
                        <span class="settings-hub-row-subtitle">Follow Kinto</span>
                    </span>
                    <i data-lucide="external-link" class="settings-hub-row-chevron"></i>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="settings-card settings-detail-card">
        <h3>Crisis Support</h3>
        <div class="alert alert-warning">
            <p>This page offers general resources and is not a substitute for professional care.</p>
            <p>If you are in the United States and need immediate crisis support, call or text <a href="tel:988">988</a>. If you or someone else is in immediate danger, call 911 or your local emergency number now.</p>
        </div>
        <div class="form-actions">
            <a href="/help" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">View More Help Resources</a>
            <a href="tel:988" class="btn btn-primary">Call 988</a>
        </div>
    </section>

    <section class="settings-card settings-detail-card">
        <h3>Newsletter</h3>
        <p>Get mental health resources, new podcast episodes, and Kinto updates in your inbox.</p>
        <form method="POST" action="/challenge/api/submit-newsletter.php" class="settings-detail-form">
            <input type="hidden" name="csrf_token" value="<?= h($newsletterCsrfToken) ?>">
            <div class="form-group">
                <label for="newsletter_email">Email Address</label>
                <input type="email" id="newsletter_email" name="email" value="<?= h($user['email'] ?? '') ?>"
                       maxlength="254" autocomplete="email" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Join the Newsletter</button>
            </div>
        </form>
    </section>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
