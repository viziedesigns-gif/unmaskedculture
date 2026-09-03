    </main>

    <?php
    $currentScript = basename($_SERVER['PHP_SELF']);
    $insightsActive = in_array($currentScript, ['mood_stats.php', 'insight_mood.php', 'insight_water.php', 'insight_weight.php'], true);
    $settingsActive = $currentScript === 'profile.php'
        || $currentScript === 'avatar.php'
        || str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/settings/');
    $activeFeedCircleId = (int) ($_SESSION['active_circle_id'] ?? 0);
    $feedNavHref = '/challenge/app/feed.php'
        . ($activeFeedCircleId > 0 ? '?circle=' . $activeFeedCircleId : '');
    require_once __DIR__ . '/jar_service.php';
    $jarUnreadCount = isLoggedIn() ? getUnreadJarCount((int) getCurrentUserId()) : 0;
    $showJarIntro = !empty($showBottomNav)
        && isLoggedIn()
        && consumeJarFeatureIntro((int) getCurrentUserId(), $currentUser['created_at'] ?? null);
    ?>
    <?php if ($showBottomNav ?? false): ?>
    <div class="podcast-mini-player" id="podcastMiniPlayer" hidden>
        <button type="button" class="podcast-mini-player__open" id="podcastMiniOpen" aria-label="Open podcast player">
            <img id="podcastMiniArtwork" alt="" hidden>
            <span class="podcast-mini-player__fallback" id="podcastMiniFallback" aria-hidden="true"><i data-lucide="podcast"></i></span>
            <span class="podcast-mini-player__copy">
                <strong id="podcastMiniTitle">The Unmasked Podcast</strong>
                <span id="podcastMiniStatus">Ready to listen</span>
            </span>
        </button>
        <button type="button" class="podcast-player-icon-button podcast-mini-player__play" id="podcastMiniPlay" aria-label="Play episode"><i data-lucide="play"></i></button>
        <button type="button" class="podcast-player-icon-button podcast-mini-player__close" id="podcastMiniClose" aria-label="Close podcast player"><i data-lucide="x"></i></button>
        <span class="podcast-mini-player__progress" aria-hidden="true"><span id="podcastMiniProgress"></span></span>
    </div>

    <div class="podcast-player-overlay" id="podcastPlayerOverlay" hidden>
        <section class="podcast-player-sheet" role="dialog" aria-modal="true" aria-labelledby="podcastPlayerTitle">
            <header class="podcast-player-sheet__header">
                <button type="button" class="podcast-player-icon-button" id="podcastPlayerMinimize" aria-label="Minimize podcast player"><i data-lucide="chevron-down"></i></button>
                <span>Now Playing</span>
                <button type="button" class="podcast-player-icon-button" id="podcastPlayerClose" aria-label="Close podcast and stop playback"><i data-lucide="x"></i></button>
            </header>

            <div class="podcast-player-sheet__body">
                <div class="podcast-player-artwork-wrap">
                    <img class="podcast-player-artwork" id="podcastPlayerArtwork" alt="" hidden>
                    <span class="podcast-player-artwork podcast-player-artwork--fallback" id="podcastPlayerArtworkFallback" aria-hidden="true"><i data-lucide="podcast"></i></span>
                </div>
                <p class="podcast-player-show" id="podcastPlayerShow">The Unmasked Podcast</p>
                <h2 id="podcastPlayerTitle">Choose an episode</h2>
                <p class="podcast-player-message" id="podcastPlayerMessage">Select an episode from the podcast library to begin.</p>

                <div class="podcast-player-timeline">
                    <label class="visually-hidden" for="podcastPlayerSeek">Episode progress</label>
                    <input type="range" id="podcastPlayerSeek" min="0" max="1000" value="0" step="1">
                    <div><span id="podcastPlayerElapsed">0:00</span><span id="podcastPlayerDuration">0:00</span></div>
                </div>

                <div class="podcast-player-controls" aria-label="Playback controls">
                    <button type="button" class="podcast-player-icon-button" id="podcastPlayerPrevious" aria-label="Previous episode"><i data-lucide="skip-back"></i></button>
                    <button type="button" class="podcast-player-icon-button podcast-player-skip" id="podcastPlayerRewind" aria-label="Rewind 15 seconds"><i data-lucide="rotate-ccw"></i><span>15</span></button>
                    <button type="button" class="podcast-player-main-button" id="podcastPlayerPlay" aria-label="Play episode"><i data-lucide="play"></i></button>
                    <button type="button" class="podcast-player-icon-button podcast-player-skip" id="podcastPlayerForward" aria-label="Forward 30 seconds"><i data-lucide="rotate-cw"></i><span>30</span></button>
                    <button type="button" class="podcast-player-icon-button" id="podcastPlayerNext" aria-label="Next episode"><i data-lucide="skip-forward"></i></button>
                </div>

                <div class="podcast-player-options">
                    <label for="podcastPlayerSpeed">Playback speed</label>
                    <select id="podcastPlayerSpeed" class="form-select">
                        <option value="0.75">0.75×</option>
                        <option value="1" selected>Normal</option>
                        <option value="1.25">1.25×</option>
                        <option value="1.5">1.5×</option>
                        <option value="1.75">1.75×</option>
                        <option value="2">2×</option>
                    </select>
                    <a id="podcastPlayerEpisodeLink" href="https://umaskedculture.podbean.com" target="_blank" rel="noopener noreferrer" class="podcast-player-external-link"><i data-lucide="external-link"></i> Episode page</a>
                </div>
            </div>
        </section>
    </div>
    <audio id="kintoPodcastAudio" preload="metadata"></audio>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <div class="bottom-nav-container">
            <a href="/challenge/app/mood_stats.php" class="bottom-nav-item <?= $insightsActive ? 'active' : '' ?>">
                <i data-lucide="bar-chart-3"></i>
                <span>Insights</span>
            </a>
            <a href="/challenge/app/jar.php" class="bottom-nav-item <?= in_array($currentScript, ['jar.php', 'jar_history.php'], true) ? 'active' : '' ?>">
                <span class="bottom-nav-jar-wrap">
                    <i data-lucide="archive"></i>
                    <?php if ($jarUnreadCount > 0): ?><span class="bottom-nav-badge" aria-label="<?= $jarUnreadCount ?> new Jar entries"><?= min(99, $jarUnreadCount) ?></span><?php endif; ?>
                </span>
                <span>Jar</span>
            </a>
            
            <a href="/challenge/app/dashboard.php" class="bottom-nav-item primary <?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                <div class="nav-item-primary">
                    <i data-lucide="flower-2"></i>
                </div>
                <span>Daily</span>
            </a>
            
            <a href="<?= h($feedNavHref) ?>" class="bottom-nav-item <?= in_array($currentScript, ['feed.php', 'circles.php', 'chat.php', 'member_profile.php'], true) ? 'active' : '' ?>">
                <i data-lucide="heart"></i>
                <span>Feed</span>
            </a>
            
            <a href="/challenge/app/profile.php" class="bottom-nav-item <?= $settingsActive ? 'active' : '' ?>">
                <i data-lucide="user"></i>
                <span>You</span>
            </a>
        </div>
    </nav>
    <?php endif; ?>

    <?php if ($showJarIntro): ?>
    <div id="jarIntroModal" class="modal jar-intro-modal active" role="dialog" aria-modal="true" aria-labelledby="jarIntroTitle">
        <div class="modal-content">
            <div class="modal-body jar-intro-content">
                <div class="jar-intro-art" aria-hidden="true">
                    <div class="jar-intro-note jar-intro-note--one"></div><div class="jar-intro-note jar-intro-note--two"></div><div class="jar-intro-note jar-intro-note--three"></div>
                </div>
                <p class="jar-eyebrow">New in Kinto</p>
                <h2 id="jarIntroTitle">Meet your Jar</h2>
                <p class="jar-intro-lead">A beautiful place to keep encouragement, memories, gratitude, and happy moments for the days you need them.</p>
                <div class="jar-intro-steps">
                    <div><i data-lucide="archive"></i><span><strong>Add what matters</strong><small>Place a note in your Jar whenever something is worth keeping.</small></span></div>
                    <div><i data-lucide="sparkles"></i><span><strong>Pull one at random</strong><small>Every note gets a turn before the Jar repeats one.</small></span></div>
                    <div><i data-lucide="users"></i><span><strong>Encouragement from your circle</strong><small>Circle members can add to your Jar from your profile.</small></span></div>
                </div>
                <div class="jar-intro-actions">
                    <a href="/challenge/app/jar.php" class="btn btn-primary btn-lg"><i data-lucide="archive"></i> Open my Jar</a>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('jarIntroModal')">Explore later</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <footer class="main-footer">
        <div class="footer-container">
            <p>&copy; <?= date('Y') ?> Kinto · Heal. Grow. Become.</p>
            <?php if (function_exists('isCurrentUserSuperAdmin') && isCurrentUserSuperAdmin()): ?>
                <a class="footer-admin-link" href="/challenge/app/admin/">Admin Console</a>
            <?php endif; ?>
        </div>
    </footer>

    <script>
        // Initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
</body>
</html>
