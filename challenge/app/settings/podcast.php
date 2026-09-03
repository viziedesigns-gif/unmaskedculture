<?php
$pageTitle = 'The Unmasked Podcast';
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/settings_layout.php';
require_once __DIR__ . '/../../includes/podcast_service.php';

$podcast = getKintoPodcastFeed();
$playerEpisodes = array_map(static function (array $episode) use ($podcast): array {
    return [
        'id' => (string) $episode['id'],
        'title' => (string) $episode['title'],
        'audioUrl' => (string) $episode['audio_url'],
        'image' => (string) $episode['image'],
        'link' => (string) $episode['link'],
        'publishedLabel' => (string) $episode['published_label'],
        'durationLabel' => (string) $episode['duration'],
        'show' => (string) $podcast['title'],
    ];
}, $podcast['episodes']);

include __DIR__ . '/../../includes/header.php';
?>

<div class="profile-page settings-detail-page podcast-library-page">
    <?php renderSettingsBackNav('Podcast', '/challenge/app/settings/resources.php', 'Resources'); ?>

    <section class="podcast-library-hero">
        <?php if ($podcast['image'] !== ''): ?>
            <img src="<?= h($podcast['image']) ?>" alt="The Unmasked Podcast cover artwork">
        <?php else: ?>
            <span class="podcast-library-hero__placeholder" aria-hidden="true"><i data-lucide="podcast"></i></span>
        <?php endif; ?>
        <div>
            <p class="profile-kicker">Listen in Kinto</p>
            <h1><?= h($podcast['title']) ?></h1>
            <p><?= h($podcast['description'] ?: 'Real conversations around mental health, vulnerability, and hope.') ?></p>
        </div>
    </section>

    <?php if (empty($podcast['episodes'])): ?>
        <section class="settings-card settings-detail-card podcast-empty-state">
            <i data-lucide="wifi-off"></i>
            <h2>Episodes are temporarily unavailable</h2>
            <p>Please check your connection and try again shortly.</p>
            <a href="<?= h($podcast['link']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">Open Podbean</a>
        </section>
    <?php else: ?>
        <div class="podcast-library-heading">
            <div><h2>All episodes</h2><p><?= count($podcast['episodes']) ?> available</p></div>
            <span>Updated automatically</span>
        </div>

        <div class="podcast-episode-list">
            <?php foreach ($podcast['episodes'] as $index => $episode): ?>
                <article class="podcast-episode-card">
                    <?php if ($episode['image'] !== ''): ?>
                        <img class="podcast-episode-card__art" src="<?= h($episode['image']) ?>" alt="" loading="lazy">
                    <?php endif; ?>
                    <div class="podcast-episode-card__body">
                        <div class="podcast-episode-card__meta">
                            <?php if ($episode['published_label'] !== ''): ?><time datetime="<?= h($episode['published_at']) ?>"><?= h($episode['published_label']) ?></time><?php endif; ?>
                            <?php if ($episode['duration'] !== ''): ?><span><?= h($episode['duration']) ?></span><?php endif; ?>
                        </div>
                        <h2><?= h($episode['title']) ?></h2>
                        <?php if ($episode['description'] !== ''): ?><p><?= h($episode['description']) ?></p><?php endif; ?>
                        <div class="podcast-episode-card__actions">
                            <button type="button" class="btn btn-primary btn-sm podcast-episode-play" data-podcast-index="<?= (int) $index ?>">
                                <i data-lucide="play"></i><span>Play episode</span>
                            </button>
                            <?php if ($episode['link'] !== ''): ?>
                                <a href="<?= h($episode['link']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm" aria-label="Open <?= h($episode['title']) ?> on Podbean"><i data-lucide="external-link"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
window.KINTO_PODCAST_EPISODES = <?= json_encode($playerEpisodes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
