/**
 * Shared Kinto podcast player.
 * Audio state is restored between server-rendered pages and the player attempts
 * to resume when a user was already listening before navigation.
 */
(function () {
    'use strict';

    const STATE_KEY = 'kintoPodcastStateV1';
    const QUEUE_KEY = 'kintoPodcastQueueV1';
    const RESUME_KEY = 'kintoPodcastResumeAfterNavigation';

    function readJson(key, fallback) {
        try {
            const value = JSON.parse(localStorage.getItem(key) || 'null');
            return value ?? fallback;
        } catch (error) {
            return fallback;
        }
    }

    function writeJson(key, value) {
        try { localStorage.setItem(key, JSON.stringify(value)); } catch (error) {}
    }

    function safeEpisode(value) {
        if (!value || typeof value !== 'object') return null;
        if (typeof value.audioUrl !== 'string' || !value.audioUrl.startsWith('https://')) return null;
        return {
            id: String(value.id || ''),
            title: String(value.title || 'Podcast episode'),
            audioUrl: value.audioUrl,
            image: typeof value.image === 'string' && value.image.startsWith('https://') ? value.image : '',
            link: typeof value.link === 'string' && value.link.startsWith('https://') ? value.link : '',
            publishedLabel: String(value.publishedLabel || ''),
            durationLabel: String(value.durationLabel || ''),
            show: String(value.show || 'The Unmasked Podcast')
        };
    }

    function formatSeconds(value) {
        const seconds = Number.isFinite(value) && value > 0 ? Math.floor(value) : 0;
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remainder = seconds % 60;
        return hours > 0
            ? `${hours}:${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`
            : `${minutes}:${String(remainder).padStart(2, '0')}`;
    }

    function refreshIcons() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
    }

    function setIcon(button, icon, label) {
        if (!button) return;
        button.innerHTML = `<i data-lucide="${icon}"></i>`;
        if (label) button.setAttribute('aria-label', label);
        refreshIcons();
    }

    function initPodcastPlayer() {
        const audio = document.getElementById('kintoPodcastAudio');
        const mini = document.getElementById('podcastMiniPlayer');
        const overlay = document.getElementById('podcastPlayerOverlay');
        if (!audio || !mini || !overlay) return;

        const elements = {
            miniOpen: document.getElementById('podcastMiniOpen'),
            miniPlay: document.getElementById('podcastMiniPlay'),
            miniClose: document.getElementById('podcastMiniClose'),
            miniArtwork: document.getElementById('podcastMiniArtwork'),
            miniFallback: document.getElementById('podcastMiniFallback'),
            miniTitle: document.getElementById('podcastMiniTitle'),
            miniStatus: document.getElementById('podcastMiniStatus'),
            miniProgress: document.getElementById('podcastMiniProgress'),
            minimize: document.getElementById('podcastPlayerMinimize'),
            close: document.getElementById('podcastPlayerClose'),
            artwork: document.getElementById('podcastPlayerArtwork'),
            artworkFallback: document.getElementById('podcastPlayerArtworkFallback'),
            show: document.getElementById('podcastPlayerShow'),
            title: document.getElementById('podcastPlayerTitle'),
            message: document.getElementById('podcastPlayerMessage'),
            seek: document.getElementById('podcastPlayerSeek'),
            elapsed: document.getElementById('podcastPlayerElapsed'),
            duration: document.getElementById('podcastPlayerDuration'),
            previous: document.getElementById('podcastPlayerPrevious'),
            rewind: document.getElementById('podcastPlayerRewind'),
            play: document.getElementById('podcastPlayerPlay'),
            forward: document.getElementById('podcastPlayerForward'),
            next: document.getElementById('podcastPlayerNext'),
            speed: document.getElementById('podcastPlayerSpeed'),
            episodeLink: document.getElementById('podcastPlayerEpisodeLink')
        };

        let queue = (Array.isArray(window.KINTO_PODCAST_EPISODES) ? window.KINTO_PODCAST_EPISODES : readJson(QUEUE_KEY, []))
            .map(safeEpisode).filter(Boolean);
        if (Array.isArray(window.KINTO_PODCAST_EPISODES) && queue.length) writeJson(QUEUE_KEY, queue);

        const restored = readJson(STATE_KEY, {});
        let current = safeEpisode(restored.episode);
        let pendingTime = Number(restored.currentTime || 0);
        let lastSavedSecond = -1;
        let isSeeking = false;

        function queueIndex() {
            return current ? queue.findIndex(item => item.id === current.id || item.audioUrl === current.audioUrl) : -1;
        }

        function saveState() {
            if (!current) return;
            writeJson(STATE_KEY, {
                episode: current,
                currentTime: Number.isFinite(audio.currentTime) ? audio.currentTime : pendingTime,
                playbackRate: audio.playbackRate || 1,
                wasPlaying: !audio.paused && !audio.ended,
                savedAt: Date.now()
            });
        }

        function setArtwork(image) {
            [elements.miniArtwork, elements.artwork].forEach(img => {
                if (!img) return;
                if (image) {
                    img.src = image;
                    img.hidden = false;
                } else {
                    img.removeAttribute('src');
                    img.hidden = true;
                }
            });
            if (elements.miniFallback) elements.miniFallback.hidden = !!image;
            if (elements.artworkFallback) elements.artworkFallback.hidden = !!image;
        }

        function updateMetadata() {
            if (!current) return;
            elements.miniTitle.textContent = current.title;
            elements.title.textContent = current.title;
            elements.show.textContent = current.show;
            elements.message.textContent = current.publishedLabel || 'The Unmasked Podcast';
            elements.episodeLink.href = current.link || 'https://umaskedculture.podbean.com';
            setArtwork(current.image);

            const index = queueIndex();
            elements.previous.disabled = index < 0 || index >= queue.length - 1;
            elements.next.disabled = index <= 0;

            if ('mediaSession' in navigator && 'MediaMetadata' in window) {
                navigator.mediaSession.metadata = new window.MediaMetadata({
                    title: current.title,
                    artist: 'Unmasked Culture',
                    album: current.show,
                    artwork: current.image ? [
                        { src: current.image, sizes: '512x512', type: 'image/jpeg' }
                    ] : []
                });
            }
        }

        function showMini() {
            mini.hidden = false;
            document.body.classList.add('has-podcast-mini');
        }

        function openOverlay() {
            if (!current) return;
            overlay.hidden = false;
            document.body.classList.add('podcast-overlay-open');
            elements.minimize.focus({ preventScroll: true });
        }

        function minimizeOverlay() {
            overlay.hidden = true;
            document.body.classList.remove('podcast-overlay-open');
        }

        async function requestPlay() {
            if (!current) return;
            try {
                await audio.play();
            } catch (error) {
                elements.miniStatus.textContent = 'Tap play to resume';
                updatePlayButtons();
            }
        }

        function loadEpisode(episode, options = {}) {
            const safe = safeEpisode(episode);
            if (!safe) return;
            const sameEpisode = current && (current.id === safe.id || current.audioUrl === safe.audioUrl);
            current = safe;
            pendingTime = options.startTime !== undefined
                ? Number(options.startTime || 0)
                : (sameEpisode ? audio.currentTime : 0);
            if (!sameEpisode || audio.src !== safe.audioUrl) {
                audio.src = safe.audioUrl;
                audio.load();
            }
            audio.playbackRate = Number(options.playbackRate || audio.playbackRate || 1);
            elements.speed.value = String(audio.playbackRate);
            updateMetadata();
            updateTimeline();
            showMini();
            saveState();
            if (options.open !== false) openOverlay();
            if (options.play) requestPlay();
        }

        function stopAndClose() {
            audio.pause();
            audio.removeAttribute('src');
            audio.load();
            current = null;
            pendingTime = 0;
            mini.hidden = true;
            overlay.hidden = true;
            document.body.classList.remove('has-podcast-mini', 'podcast-overlay-open');
            try {
                localStorage.removeItem(STATE_KEY);
                sessionStorage.removeItem(RESUME_KEY);
            } catch (error) {}
            if ('mediaSession' in navigator) navigator.mediaSession.metadata = null;
        }

        function updatePlayButtons() {
            const playing = !audio.paused && !audio.ended;
            setIcon(elements.miniPlay, playing ? 'pause' : 'play', playing ? 'Pause episode' : 'Play episode');
            setIcon(elements.play, playing ? 'pause' : 'play', playing ? 'Pause episode' : 'Play episode');
            elements.miniStatus.textContent = playing ? 'Playing' : (audio.ended ? 'Finished' : 'Paused');
            if ('mediaSession' in navigator) navigator.mediaSession.playbackState = playing ? 'playing' : 'paused';
        }

        function updateTimeline() {
            const duration = Number.isFinite(audio.duration) ? audio.duration : 0;
            const currentTime = Number.isFinite(audio.currentTime) ? audio.currentTime : pendingTime;
            if (!isSeeking) elements.seek.value = duration > 0 ? String(Math.round((currentTime / duration) * 1000)) : '0';
            elements.elapsed.textContent = formatSeconds(currentTime);
            elements.duration.textContent = duration > 0 ? formatSeconds(duration) : (current?.durationLabel || '0:00');
            elements.miniProgress.style.width = duration > 0 ? `${Math.min(100, (currentTime / duration) * 100)}%` : '0%';

            const second = Math.floor(currentTime);
            if (second !== lastSavedSecond && second % 5 === 0) {
                lastSavedSecond = second;
                saveState();
            }
            if ('mediaSession' in navigator && duration > 0 && currentTime <= duration) {
                try { navigator.mediaSession.setPositionState({ duration, playbackRate: audio.playbackRate, position: Math.max(0, currentTime) }); } catch (error) {}
            }
        }

        function togglePlayback() {
            if (!current) return;
            if (audio.paused || audio.ended) requestPlay();
            else audio.pause();
        }

        function moveBy(seconds) {
            if (!current || !Number.isFinite(audio.duration)) return;
            audio.currentTime = Math.max(0, Math.min(audio.duration, audio.currentTime + seconds));
            updateTimeline();
        }

        function moveQueue(direction) {
            const index = queueIndex();
            const target = index + direction;
            if (index < 0 || target < 0 || target >= queue.length) return;
            loadEpisode(queue[target], { play: true, open: true });
        }

        document.addEventListener('click', event => {
            const button = event.target.closest('.podcast-episode-play');
            if (!button) return;
            const pageQueue = Array.isArray(window.KINTO_PODCAST_EPISODES)
                ? window.KINTO_PODCAST_EPISODES.map(safeEpisode).filter(Boolean)
                : queue;
            if (pageQueue.length) {
                queue = pageQueue;
                writeJson(QUEUE_KEY, queue);
            }
            const index = Number(button.dataset.podcastIndex);
            if (Number.isInteger(index) && queue[index]) loadEpisode(queue[index], { play: true, open: true });
        });

        elements.miniOpen.addEventListener('click', openOverlay);
        elements.miniPlay.addEventListener('click', togglePlayback);
        elements.miniClose.addEventListener('click', stopAndClose);
        elements.minimize.addEventListener('click', minimizeOverlay);
        elements.close.addEventListener('click', stopAndClose);
        elements.play.addEventListener('click', togglePlayback);
        elements.rewind.addEventListener('click', () => moveBy(-15));
        elements.forward.addEventListener('click', () => moveBy(30));
        elements.previous.addEventListener('click', () => moveQueue(1));
        elements.next.addEventListener('click', () => moveQueue(-1));
        elements.speed.addEventListener('change', () => {
            audio.playbackRate = Number(elements.speed.value) || 1;
            saveState();
        });
        elements.seek.addEventListener('pointerdown', () => { isSeeking = true; });
        elements.seek.addEventListener('input', () => {
            if (!Number.isFinite(audio.duration)) return;
            elements.elapsed.textContent = formatSeconds((Number(elements.seek.value) / 1000) * audio.duration);
        });
        elements.seek.addEventListener('change', () => {
            if (Number.isFinite(audio.duration)) audio.currentTime = (Number(elements.seek.value) / 1000) * audio.duration;
            isSeeking = false;
            updateTimeline();
        });
        overlay.addEventListener('click', event => { if (event.target === overlay) minimizeOverlay(); });
        document.addEventListener('keydown', event => { if (event.key === 'Escape' && !overlay.hidden) minimizeOverlay(); });

        audio.addEventListener('loadedmetadata', () => {
            if (pendingTime > 0 && pendingTime < audio.duration - 2) audio.currentTime = pendingTime;
            pendingTime = 0;
            updateTimeline();
        });
        audio.addEventListener('play', updatePlayButtons);
        audio.addEventListener('pause', () => { updatePlayButtons(); saveState(); });
        audio.addEventListener('timeupdate', updateTimeline);
        audio.addEventListener('durationchange', updateTimeline);
        audio.addEventListener('ended', () => {
            updatePlayButtons();
            const index = queueIndex();
            if (index > 0) loadEpisode(queue[index - 1], { play: true, open: false });
            else saveState();
        });
        audio.addEventListener('error', () => {
            if (!current) return;
            elements.miniStatus.textContent = 'Playback unavailable';
            elements.message.textContent = 'This episode could not be loaded. Try opening its episode page.';
        });

        if ('mediaSession' in navigator) {
            const actions = {
                play: requestPlay,
                pause: () => audio.pause(),
                seekbackward: details => moveBy(-(details.seekOffset || 15)),
                seekforward: details => moveBy(details.seekOffset || 30),
                previoustrack: () => moveQueue(1),
                nexttrack: () => moveQueue(-1),
                seekto: details => {
                    if (!Number.isFinite(audio.duration)) return;
                    audio.currentTime = Math.max(0, Math.min(audio.duration, details.seekTime || 0));
                }
            };
            Object.entries(actions).forEach(([action, handler]) => {
                try { navigator.mediaSession.setActionHandler(action, handler); } catch (error) {}
            });
        }

        window.addEventListener('pagehide', () => {
            saveState();
            try {
                if (current && !audio.paused && !audio.ended) sessionStorage.setItem(RESUME_KEY, '1');
                else sessionStorage.removeItem(RESUME_KEY);
            } catch (error) {}
        });

        if (current) {
            loadEpisode(current, {
                startTime: pendingTime,
                playbackRate: Number(restored.playbackRate || 1),
                open: false,
                play: false
            });
            let shouldResume = false;
            try {
                shouldResume = sessionStorage.getItem(RESUME_KEY) === '1';
                sessionStorage.removeItem(RESUME_KEY);
            } catch (error) {}
            if (shouldResume) audio.addEventListener('canplay', requestPlay, { once: true });
        }

        updatePlayButtons();
        refreshIcons();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initPodcastPlayer);
    else initPodcastPlayer();
})();
