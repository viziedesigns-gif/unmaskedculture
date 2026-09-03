<?php
$pageTitle = 'Profile Information';
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/settings_layout.php';
$profileCsrfToken = $_SESSION['profile_csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['profile_csrf_token'] = $profileCsrfToken;
$profileForm = $user;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '') {
    foreach (['first_name', 'last_name', 'timezone', 'profile_bio', 'profile_prompt_key', 'profile_prompt_answer', 'profile_banner_x', 'profile_banner_y', 'profile_banner_zoom'] as $field) {
        if (array_key_exists($field, $_POST)) $profileForm[$field] = $_POST[$field];
    }
    $profileForm['profile_visible'] = isset($_POST['profile_visible']) ? 1 : 0;
}
$profileDisplayName = trim((string) (($profileForm['first_name'] ?? '') . ' ' . ($profileForm['last_name'] ?? '')));
$profileDisplayName = $profileDisplayName !== '' ? $profileDisplayName : 'Your name';
$profilePhotoUrl = !empty($user['profile_pic']) ? profilePicUrl($user['profile_pic']) : '';
include __DIR__ . '/../../includes/header.php';
?>

<div class="profile-page settings-detail-page">
    <?php renderSettingsBackNav('Profile Information'); ?>
    <?php renderSettingsAlerts($error, $success); ?>

    <div class="profile-editor-toolbar">
        <div>
            <h2>Make your profile feel like you</h2>
            <p>Update your story here, then dress your avatar or customize profile style.</p>
        </div>
        <div class="profile-editor-toolbar__actions">
            <a href="/challenge/app/member_profile.php?id=<?= (int) $userId ?>" class="btn btn-secondary btn-sm"><i data-lucide="eye"></i> View Profile</a>
            <a href="/challenge/app/avatar.php" class="btn btn-secondary btn-sm"><i data-lucide="smile"></i> Avatar</a>
            <a href="/challenge/app/settings/shop.php" class="btn btn-primary btn-sm"><i data-lucide="palette"></i> Customize Style</a>
        </div>
    </div>

    <div class="profile-editor-preview" id="profileEditorPreview" style="--profile-x:<?= (int) ($profileForm['profile_banner_x'] ?? 50) ?>%;--profile-y:<?= (int) ($profileForm['profile_banner_y'] ?? 50) ?>%;--profile-zoom:<?= h((string) ($profileForm['profile_banner_zoom'] ?? 1)) ?>">
        <div class="profile-editor-preview__cover">
            <?php if ($profilePhotoUrl !== ''): ?><img id="profileEditorCover" src="<?= h($profilePhotoUrl) ?>" alt=""><?php else: ?><img id="profileEditorCover" alt="" hidden><?php endif; ?>
            <span>Profile preview</span>
        </div>
        <div class="profile-editor-preview__identity">
            <div><strong id="profileEditorName"><?= h($profileDisplayName) ?></strong><span><i data-lucide="clock-3"></i> Active today</span></div>
        </div>
    </div>

    <div class="settings-card settings-detail-card">
        <form method="POST" enctype="multipart/form-data" class="settings-detail-form profile-editor-form" id="profileEditorForm">
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="csrf_token" value="<?= h($profileCsrfToken) ?>">

            <div class="profile-editor-section-heading"><span>Identity</span><p>The name and timezone shown across your challenge.</p></div>
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name"
                           value="<?= h($profileForm['first_name']) ?>" autocomplete="given-name" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name"
                           value="<?= h($profileForm['last_name']) ?>" autocomplete="family-name" required>
                </div>
            </div>

            <div class="form-group">
                <label for="timezone">Timezone</label>
                <select id="timezone" name="timezone" class="form-select">
                    <?php foreach ($timezones as $tz => $label): ?>
                        <option value="<?= h($tz) ?>" <?= $profileForm['timezone'] === $tz ? 'selected' : '' ?>>
                            <?= h($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="profile-editor-section-heading"><span>Your story</span><p>Give your circle a simple way to understand and encourage you.</p></div>
            <div class="form-group">
                <label for="profile_bio">Bio</label>
                <textarea id="profile_bio" name="profile_bio" rows="3" maxlength="500" class="form-textarea"
                          placeholder="A few words about what you're building in this season."><?= h($profileForm['profile_bio'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="profile_prompt_key">Profile Prompt</label>
                <select id="profile_prompt_key" name="profile_prompt_key" class="form-select">
                    <?php foreach ($profilePromptOptions as $key => $question): ?>
                        <option value="<?= h($key) ?>" <?= ($profileForm['profile_prompt_key'] ?? 'motivation') === $key ? 'selected' : '' ?>>
                            <?= h($question) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="profile_prompt_answer">Prompt Answer</label>
                <textarea id="profile_prompt_answer" name="profile_prompt_answer" rows="3" maxlength="500" class="form-textarea"
                          placeholder="Answer in a way your circle can encourage you."><?= h($profileForm['profile_prompt_answer'] ?? '') ?></textarea>
            </div>

            <div class="profile-editor-section-heading"><span>Sharing</span><p>Control whether your profile can be opened from a shared link.</p></div>
            <label class="checkbox-large profile-visible-choice profile-visibility-card">
                <input type="checkbox" name="profile_visible" value="1" <?= !empty($profileForm['profile_visible']) ? 'checked' : '' ?>>
                <span class="checkbox-label">Make my profile public and shareable</span>
            </label>

            <div class="profile-editor-section-heading"><span>Photo &amp; framing</span><p>Your photo becomes both the cover and avatar. Adjust the cover without changing the original.</p></div>
            <div class="form-group profile-photo-control">
                <label for="profile_pic">Profile Picture</label>
                <input type="file" id="profile_pic" name="profile_pic"
                       accept="image/jpeg,image/png,image/gif,image/webp">
                <p class="form-hint">JPG, PNG, GIF, or WebP up to 5 MB.</p>
            </div>

            <div class="form-group banner-framing-controls">
                <label>Cover framing</label>
                <label for="profile_banner_x">Horizontal focus</label>
                <input type="range" id="profile_banner_x" name="profile_banner_x" min="0" max="100" value="<?= (int) ($profileForm['profile_banner_x'] ?? 50) ?>">
                <label for="profile_banner_y">Vertical focus</label>
                <input type="range" id="profile_banner_y" name="profile_banner_y" min="0" max="100" value="<?= (int) ($profileForm['profile_banner_y'] ?? 50) ?>">
                <label for="profile_banner_zoom">Zoom</label>
                <input type="range" id="profile_banner_zoom" name="profile_banner_zoom" min="1" max="2.5" step="0.05" value="<?= h((string) ($profileForm['profile_banner_zoom'] ?? 1)) ?>">
                <p class="form-hint">These controls only change how your photo is framed; your original image is preserved.</p>
            </div>

            <div class="form-actions profile-editor-savebar">
                <button type="submit" class="btn btn-primary" id="profileSaveButton">Save Profile</button>
                <a href="/challenge/app/avatar.php" class="btn btn-secondary">
                    <i data-lucide="smile"></i> Avatar Studio
                </a>
                <a href="/challenge/app/settings/shop.php" class="btn btn-secondary">
                    <i data-lucide="palette"></i> Customize Style
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const editorPreview = document.getElementById('profileEditorPreview');
    const cover = document.getElementById('profileEditorCover');
    const photo = document.getElementById('profile_pic');
    const firstName = document.getElementById('first_name');
    const lastName = document.getElementById('last_name');
    const name = document.getElementById('profileEditorName');
    const form = document.getElementById('profileEditorForm');
    const saveButton = document.getElementById('profileSaveButton');
    const x = document.getElementById('profile_banner_x');
    const y = document.getElementById('profile_banner_y');
    const zoom = document.getElementById('profile_banner_zoom');
    const updatePreview = () => {
        if (!x || !y || !zoom) return;
        if (editorPreview) {
            editorPreview.style.setProperty('--profile-x', `${x.value}%`);
            editorPreview.style.setProperty('--profile-y', `${y.value}%`);
            editorPreview.style.setProperty('--profile-zoom', zoom.value);
        }
    };
    [x, y, zoom].forEach((control) => control?.addEventListener('input', updatePreview));

    const updateName = () => {
        const value = `${firstName?.value || ''} ${lastName?.value || ''}`.trim();
        if (name) name.textContent = value || 'Your name';
    };
    [firstName, lastName].forEach((input) => input?.addEventListener('input', updateName));

    photo?.addEventListener('change', () => {
        const file = photo.files && photo.files[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
            photo.value = '';
            window.alert('Choose a profile photo under 5 MB.');
            return;
        }
        const url = URL.createObjectURL(file);
        if (cover) {
            cover.src = url;
            cover.hidden = false;
        }
    });

    form?.addEventListener('submit', () => {
        if (!saveButton) return;
        saveButton.disabled = true;
        saveButton.textContent = 'Saving…';
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
