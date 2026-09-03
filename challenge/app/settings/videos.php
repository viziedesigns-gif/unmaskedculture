<?php
$pageTitle = 'Unmasked Culture Productions';
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/settings_layout.php';

$youtubePlaylistId = 'PLTtIKVrFzxWQ';
$youtubePlaylistUrl = 'https://www.youtube.com/playlist?list=' . rawurlencode($youtubePlaylistId);
$youtubeEmbedUrl = 'https://www.youtube-nocookie.com/embed/videoseries?list=' . rawurlencode($youtubePlaylistId) . '&rel=0';

include __DIR__ . '/../../includes/header.php';
?>

<div class="profile-page settings-detail-page video-library-page">
    <?php renderSettingsBackNav('Unmasked Culture Productions', '/challenge/app/settings/resources.php', 'Resources'); ?>

    <section class="settings-card settings-detail-card video-library-player-card">
        <div class="video-library-player">
            <iframe
                src="<?= h($youtubeEmbedUrl) ?>"
                title="Unmasked Culture Productions video playlist"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                referrerpolicy="strict-origin-when-cross-origin"
                allowfullscreen
            ></iframe>
        </div>
        <div class="video-library-help">
            <div>
                <h3>Production playlist</h3>
                <p>Use the playlist control to choose a video. Only videos added to this playlist appear here.</p>
            </div>
            <a href="<?= h($youtubePlaylistUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">
                <i data-lucide="external-link"></i> Open Playlist
            </a>
        </div>
    </section>

    <p class="video-library-note">A video may open on YouTube if its individual embedding settings require it.</p>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
