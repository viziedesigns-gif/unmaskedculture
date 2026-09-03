(function () {
    const catalog = (window.KINTO_AVATAR && window.KINTO_AVATAR.catalog) || {};

    function itemOf(id) {
        return id && catalog[id] ? catalog[id] : null;
    }

    function resolveConfig(config) {
        const defaults = (window.KINTO_AVATAR && window.KINTO_AVATAR.defaults) || {
            skin: 'skin_warm',
            hair: 'hair_short',
            eyes: 'eyes_round',
            outfit: 'outfit_tee',
            hat: null,
            accessory: null,
            extra: null
        };
        const out = Object.assign({}, defaults, config || {});
        ['hat', 'accessory', 'extra'].forEach((slot) => {
            const item = itemOf(out[slot]);
            if (item && item.shape === 'none') out[slot] = null;
        });
        return out;
    }

    function layersFrom(config) {
        const resolved = resolveConfig(config);
        const pick = (slot, fallback) => {
            const item = itemOf(resolved[slot]);
            if (item && item.shape !== 'none') return item;
            return itemOf(fallback) || item;
        };
        return {
            skin: pick('skin', 'skin_warm'),
            hair: pick('hair', 'hair_short'),
            eyes: pick('eyes', 'eyes_round'),
            outfit: pick('outfit', 'outfit_tee'),
            hat: pick('hat', null),
            accessory: pick('accessory', null),
            extra: pick('extra', null)
        };
    }

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function hairBack(hair) {
        if (!hair) return '';
        const fill2 = esc(hair.fill2 || hair.fill);
        if (hair.shape === 'long') {
            return `<path class="kinto-avatar__hair-back" d="M46 92c-8 22-6 58 8 78 6-18 18-28 46-28s40 10 46 28c14-20 16-56 8-78-18 14-38 20-54 20s-36-6-54-20z" fill="${fill2}"/>`;
        }
        if (hair.shape === 'wave' || hair.shape === 'locs') {
            return `<path class="kinto-avatar__hair-back" d="M40 88c-6 20-2 52 12 66 8-16 20-24 48-24s40 8 48 24c14-14 18-46 12-66-16 12-36 20-60 20S56 100 40 88z" fill="${fill2}"/>`;
        }
        if (hair.shape === 'bob') {
            return `<ellipse class="kinto-avatar__hair-back" cx="100" cy="96" rx="62" ry="58" fill="${fill2}"/>`;
        }
        return '';
    }

    function hairFront(hair) {
        if (!hair) return '';
        const fill = esc(hair.fill);
        const fill2 = esc(hair.fill2 || hair.fill);
        const shapes = {
            short: `<path d="M48 86c2-42 28-62 52-62s50 20 52 62c-14-18-30-26-52-26S62 68 48 86z" fill="${fill}"/>`,
            bob: `<path d="M42 92c4-48 30-70 58-70s54 22 58 70c-12-10-28-16-58-16S54 82 42 92z" fill="${fill}"/><path d="M46 118c6 18 18 28 54 28s48-10 54-28c-10 8-28 14-54 14s-44-6-54-14z" fill="${fill2}"/>`,
            curl: `<path d="M48 84c4-40 26-58 52-58s48 18 52 58c-10-14-24-22-52-22S58 70 48 84z" fill="${fill}"/><circle cx="62" cy="52" r="14" fill="${fill}"/><circle cx="86" cy="40" r="15" fill="${fill2}"/><circle cx="114" cy="38" r="16" fill="${fill}"/><circle cx="140" cy="52" r="14" fill="${fill2}"/>`,
            wave: `<path d="M44 90c6-46 30-68 56-68s50 22 56 68c-14-16-32-24-56-24S58 74 44 90z" fill="${fill}"/>`,
            bun: `<circle cx="100" cy="28" r="18" fill="${fill}"/><circle cx="100" cy="28" r="10" fill="${fill2}"/><path d="M50 86c4-40 26-56 50-56s46 16 50 56c-12-16-28-24-50-24S62 70 50 86z" fill="${fill}"/>`,
            locs: `<path d="M48 84c4-40 28-58 52-58s48 18 52 58c-12-16-30-24-52-24S60 68 48 84z" fill="${fill}"/>` +
                [58, 72, 86, 100, 114, 128, 142].map((x, i) => `<rect x="${x - 5}" y="42" width="10" height="${46 + (i % 3) * 8}" rx="5" fill="${i % 2 ? fill2 : fill}"/>`).join(''),
            fade: `<path d="M54 88c2-36 24-50 46-50s44 14 46 50c-12-12-26-18-46-18S66 76 54 88z" fill="${fill}"/><path d="M46 96c8-8 16-12 22-8 0 14-6 28-22 34-4-8-4-18 0-26z" fill="${fill2}"/><path d="M154 96c-8-8-16-12-22-8 0 14 6 28 22 34 4-8 4-18 0-26z" fill="${fill2}"/>`,
            long: `<path d="M46 88c6-46 30-66 54-66s48 20 54 66c-16-18-34-26-54-26S62 70 46 88z" fill="${fill}"/>`,
            braid: `<path d="M48 86c4-42 28-60 52-60s48 18 52 60c-14-16-30-24-52-24S62 70 48 86z" fill="${fill}"/><path d="M58 48c12 8 28 10 42 10s30-2 42-10c-8 16-24 26-42 26S66 64 58 48z" fill="${fill2}"/><circle cx="78" cy="52" r="6" fill="#F6E7B2"/><circle cx="100" cy="56" r="6" fill="#F6E7B2"/><circle cx="122" cy="52" r="6" fill="#F6E7B2"/>`
        };
        return `<g class="kinto-avatar__hair-front">${shapes[hair.shape] || shapes.short}</g>`;
    }

    function outfitSvg(outfit) {
        if (!outfit) return '';
        const fill = esc(outfit.fill);
        const fill2 = esc(outfit.fill2 || outfit.fill);
        const accent = esc(outfit.stroke || outfit.fill2 || outfit.fill);
        let extra = '';
        if (outfit.shape === 'henley') extra = `<path d="M88 138c4 10 8 14 12 14s8-4 12-14" fill="none" stroke="${accent}" stroke-width="2"/><circle cx="100" cy="158" r="2.4" fill="${accent}"/><circle cx="100" cy="168" r="2.4" fill="${accent}"/>`;
        else if (outfit.shape === 'collar') extra = `<path d="M78 140l22 16 22-16" fill="${fill2}"/>`;
        else if (outfit.shape === 'wrap') extra = `<path d="M62 168c20-10 38-6 38 6 0-16 22-22 40-8-18 18-40 24-78 2z" fill="${fill2}" opacity=".7"/>`;
        else if (outfit.shape === 'vneck') extra = `<path d="M82 140l18 22 18-22" fill="none" stroke="${accent}" stroke-width="2.4"/>`;
        else if (outfit.shape === 'sash') extra = `<path d="M70 176l60-8 8 16-64 10z" fill="${fill2}"/><path d="M86 140l14 18 14-18" fill="none" stroke="${accent}" stroke-width="2"/>`;
        else if (outfit.shape === 'gold') extra = `<path d="M74 142h52l-8 14H82z" fill="${fill2}"/><path d="M70 184h60" stroke="${accent}" stroke-width="3"/>`;
        else extra = `<path d="M86 140c4 8 8 12 14 12s10-4 14-12" fill="none" stroke="${accent}" stroke-width="2" opacity=".55"/>`;
        return `<g class="kinto-avatar__outfit"><path d="M56 164c8-18 20-28 44-28s36 10 44 28c6 16 8 36 0 48H56c-8-12-6-32 0-48z" fill="${fill}"/>${extra}</g>`;
    }

    function eyesSvg(eyes) {
        if (!eyes) return '';
        const iris = esc(eyes.fill);
        const white = esc(eyes.fill2 || '#F7F1E6');
        const specs = { round: [10, 10, 5.2], soft: [11, 9, 4.8], almond: [12, 8, 4.6], bright: [11.5, 11, 5.6], sleepy: [12, 7, 3.8], spark: [10.5, 10.5, 5] };
        const [rx, ry, pr] = specs[eyes.shape] || specs.round;
        const pair = [[80, 88], [120, 88]].map(([cx, cy]) => {
            let spark = eyes.shape === 'spark' ? '<path d="M6-7l1.1 2.4 2.5.2-2 1.8.7 2.5L6-1.6 3.7-.1l.7-2.5-2-1.8 2.5-.2z" fill="#C4A35A"/>' : '';
            let lid = eyes.shape === 'sleepy' ? `<path d="M-${rx} -1 Q0 ${-ry - 1} ${rx} -1" fill="${white}" stroke="#1A1714" stroke-width="1.4"/>` : '';
            return `<g class="kinto-avatar__eye" transform="translate(${cx} ${cy})"><ellipse class="kinto-avatar__eye-white" cx="0" cy="0" rx="${rx}" ry="${ry}" fill="${white}"/><circle class="kinto-avatar__iris" cx="0" cy="1" r="${pr}" fill="${iris}"/><circle class="kinto-avatar__pupil" cx="0" cy="1.4" r="${pr * 0.46}" fill="#1A1714"/><circle class="kinto-avatar__shine" cx="-2" cy="-1.4" r="1.6" fill="#fff"/>${spark}${lid}</g>`;
        }).join('');
        return `<g class="kinto-avatar__look"><g class="kinto-avatar__eyes">${pair}</g></g>`;
    }

    function extras(layers) {
        const extra = layers.extra;
        const acc = layers.accessory;
        const hat = layers.hat;
        const line = esc((layers.skin && (layers.skin.stroke || layers.skin.fill2)) || '#B07848');
        const brow = esc((layers.hair && layers.hair.fill) || line);
        const blush = extra && extra.shape === 'blush'
            ? `<g class="kinto-avatar__blush" fill="${esc(extra.fill)}" opacity=".55"><ellipse cx="62" cy="104" rx="10" ry="6"/><ellipse cx="138" cy="104" rx="10" ry="6"/></g>`
            : '';
        const brows = `<g class="kinto-avatar__brows" stroke="${brow}" stroke-width="3.2" stroke-linecap="round" fill="none"><path d="M68 72q12-8 24 0"/><path d="M108 72q12-8 24 0"/></g>`;
        const mouth = `<path class="kinto-avatar__mouth" d="M88 112q12 10 24 0" fill="none" stroke="${line}" stroke-width="2.8" stroke-linecap="round"/>`;
        return {
            blush,
            brows,
            mouth,
            hat: hatSvg(hat),
            glasses: acc && acc.shape === 'glasses' ? `<g class="kinto-avatar__glasses" fill="none" stroke="${esc(acc.fill)}" stroke-width="3"><circle cx="80" cy="88" r="14"/><circle cx="120" cy="88" r="14"/><path d="M94 88h12" stroke="${esc(acc.fill2 || acc.fill)}" stroke-width="2.4"/></g>` : '',
            leaf: extra && extra.shape === 'leafclip' ? `<g class="kinto-avatar__leafclip" transform="translate(138 44) rotate(28)"><ellipse cx="0" cy="0" rx="9" ry="16" fill="${esc(extra.fill)}"/><path d="M0-12v22" stroke="${esc(extra.fill2 || extra.fill)}" stroke-width="1.6"/></g>` : '',
            pin: extra && extra.shape === 'pin' ? `<g class="kinto-avatar__pin" transform="translate(132 168)"><circle cx="0" cy="0" r="8" fill="${esc(extra.fill)}"/><circle cx="0" cy="0" r="4.2" fill="${esc(extra.fill2 || '#1A1714')}"/></g>` : '',
            sparkles: extra && extra.shape === 'sparkles' ? `<g class="kinto-avatar__sparkles" fill="${esc(extra.fill)}"><path class="kinto-avatar__spark kinto-avatar__spark--a" d="M36 70l2.2 5 5.2.4-4.2 3.4 1.4 5.2L36 80.6 31.4 83.8l1.4-5.2-4.2-3.4 5.2-.4z"/><path class="kinto-avatar__spark kinto-avatar__spark--b" d="M164 62l2 4.4 4.6.4-3.6 3 1.2 4.6L164 71.4 159.8 74.4l1.2-4.6-3.6-3 4.6-.4z" fill="${esc(extra.fill2 || extra.fill)}"/><path class="kinto-avatar__spark kinto-avatar__spark--c" d="M168 120l1.6 3.6 3.8.3-3 2.5 1 3.8L168 128l-3.4 2.4 1-3.8-3-2.5 3.8-.3z"/></g>` : ''
        };
    }

    function hatSvg(hat) {
        if (!hat) return '';
        const fill = esc(hat.fill);
        const fill2 = esc(hat.fill2 || hat.fill);
        if (hat.shape === 'beanie') return `<g class="kinto-avatar__hat"><path d="M52 70c6-38 28-54 48-54s42 16 48 54c-16-10-32-14-48-14S68 60 52 70z" fill="${fill}"/><ellipse cx="100" cy="70" rx="50" ry="8" fill="${fill2}"/><circle cx="100" cy="18" r="6" fill="${esc(hat.stroke || fill2)}"/></g>`;
        if (hat.shape === 'cap') return `<g class="kinto-avatar__hat"><path d="M54 72c8-34 26-46 46-46s38 12 46 46H54z" fill="${fill}"/><path d="M50 72h92c-8 8-28 12-46 12s-38-4-46-12z" fill="${fill2}"/><path d="M132 70c18 2 28 8 34 14-16 2-30 0-40-6z" fill="${fill}"/></g>`;
        if (hat.shape === 'leaf') return `<g class="kinto-avatar__hat"><path d="M58 48c18-22 40-18 42-2 2-16 24-20 42 2-18 8-32 16-42 16S76 56 58 48z" fill="${fill}"/><path d="M100 46c-8 8-14 16-16 24" fill="none" stroke="${fill2}" stroke-width="2"/></g>`;
        if (hat.shape === 'beret') return `<g class="kinto-avatar__hat"><ellipse cx="96" cy="48" rx="46" ry="20" fill="${fill}"/><ellipse cx="100" cy="62" rx="40" ry="8" fill="${fill2}"/><circle cx="56" cy="42" r="5" fill="${fill}"/></g>`;
        if (hat.shape === 'crown') return `<g class="kinto-avatar__hat"><path d="M58 64l10-22 16 14 16-20 16 20 16-14 10 22z" fill="${fill}" stroke="${fill2}" stroke-width="2"/><circle cx="84" cy="50" r="3.2" fill="#F6E7B2"/><circle cx="100" cy="42" r="3.2" fill="#F6E7B2"/><circle cx="116" cy="50" r="3.2" fill="#F6E7B2"/></g>`;
        return '';
    }

    function render(config, options) {
        const opts = options || {};
        const size = opts.size || 'md';
        const animate = opts.animate !== undefined ? !!opts.animate : (size === 'lg' || size === 'xl');
        const layers = layersFrom(config);
        const skin = layers.skin || { fill: '#E8B88A', stroke: '#B07848', fill2: '#C99262' };
        const fill = esc(skin.fill);
        const stroke = esc(skin.stroke || skin.fill2 || '#B07848');
        const bits = extras(layers);
        const classes = ['kinto-avatar', 'kinto-avatar--' + size];
        if (animate) classes.push('kinto-avatar--animate');
        if (opts.className) classes.push(opts.className);
        return `<svg class="${classes.join(' ')}" viewBox="0 0 200 240" role="img" aria-hidden="true" focusable="false"><g class="kinto-avatar__bob">${hairBack(layers.hair)}<g class="kinto-avatar__body"><rect x="90" y="132" width="20" height="16" rx="7" fill="${fill}" stroke="${stroke}" stroke-width="2"/><ellipse cx="100" cy="188" rx="44" ry="36" fill="${fill}" stroke="${stroke}" stroke-width="2.2"/></g>${outfitSvg(layers.outfit)}<g class="kinto-avatar__head"><g class="kinto-avatar__skin"><ellipse class="kinto-avatar__ear kinto-avatar__ear--l" cx="48" cy="90" rx="11" ry="14" fill="${fill}" stroke="${stroke}" stroke-width="2"/><ellipse class="kinto-avatar__ear kinto-avatar__ear--r" cx="152" cy="90" rx="11" ry="14" fill="${fill}" stroke="${stroke}" stroke-width="2"/><circle cx="100" cy="86" r="54" fill="${fill}" stroke="${stroke}" stroke-width="2.4"/></g><g class="kinto-avatar__face">${bits.blush}${eyesSvg(layers.eyes)}${bits.brows}${bits.mouth}</g></g>${hairFront(layers.hair)}${bits.leaf}${bits.hat}${bits.glasses}${bits.pin}${bits.sparkles}</g></svg>`;
    }

    function renderFaceHtml(msg) {
        if (msg && msg.use_avatar && msg.avatar_html) return msg.avatar_html;
        if (msg && msg.use_avatar && msg.avatar) return render(msg.avatar, { size: 'sm', animate: false });
        const url = msg && (msg.profile_pic_url || '');
        const initial = ((msg && msg.first_name) ? msg.first_name : 'U').charAt(0).toUpperCase();
        if (url) {
            return `<img src="${esc(url)}" alt="" data-initial="${esc(initial)}" onerror="window.__feedAvatarFallback && window.__feedAvatarFallback(this)">`;
        }
        return `<span class="avatar-placeholder">${esc(initial)}</span>`;
    }

    async function postAction(root, payload) {
        const res = await fetch(root.dataset.api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(Object.assign({ csrf_token: root.dataset.csrf }, payload))
        });
        const data = await res.json();
        if (!data || !data.ok) {
            throw new Error((data && data.error) || 'Unable to update your avatar.');
        }
        return data;
    }

    function applyState(root, state, previewHtml) {
        if (!state) return;
        window.KINTO_AVATAR.config = state.config;
        window.KINTO_AVATAR.balance = state.balance;
        window.KINTO_AVATAR.publicFace = !!state.public_face;
        const balanceEl = document.getElementById('avatarBalance');
        if (balanceEl) balanceEl.textContent = Number(state.balance || 0).toLocaleString();
        const preview = document.getElementById('avatarPreview');
        if (preview) {
            preview.innerHTML = previewHtml || render(state.config, { size: 'xl', animate: true });
        }
        const toggle = document.getElementById('avatarPublicFace');
        if (toggle) toggle.checked = !!state.public_face;

        root.querySelectorAll('.avatar-item').forEach((btn) => {
            const itemId = btn.dataset.itemId;
            const item = itemOf(itemId);
            const equipped = item && item.shape === 'none'
                ? !state.config[item.slot]
                : state.config && state.config[item.slot] === itemId;
            const isOwned = Number(btn.dataset.price) <= 0 || !!(state.owned_set && state.owned_set[itemId]) || (Array.isArray(state.owned) && state.owned.indexOf(itemId) !== -1) || btn.dataset.owned === '1';
            if (Array.isArray(state.owned) && state.owned.indexOf(itemId) !== -1) btn.dataset.owned = '1';
            btn.classList.toggle('is-equipped', !!equipped);
            btn.classList.toggle('is-owned', isOwned);
            btn.classList.toggle('is-locked', !isOwned);
            btn.setAttribute('aria-pressed', equipped ? 'true' : 'false');
            const meta = btn.querySelector('.avatar-item-meta');
            if (meta) {
                if (equipped) meta.textContent = 'Equipped';
                else if (isOwned) meta.textContent = 'Owned';
                else if (Number(btn.dataset.price) <= 0) meta.textContent = 'Free';
                else meta.textContent = Number(btn.dataset.price).toLocaleString() + ' pts';
            }
            const need = btn.querySelector('.avatar-item-need');
            if (need) need.hidden = isOwned || state.balance >= Number(btn.dataset.price);
        });
    }

    function setStatus(text) {
        const status = document.getElementById('avatarStatus');
        if (status) status.textContent = text;
    }

    function mountStudio(root) {
        if (!root) return;
        let config = resolveConfig(window.KINTO_AVATAR.config);
        let pending = false;

        root.querySelectorAll('.avatar-tray-tab').forEach((tab) => {
            tab.addEventListener('click', () => {
                const tray = tab.dataset.tray;
                root.querySelectorAll('.avatar-tray-tab').forEach((other) => {
                    const on = other === tab;
                    other.classList.toggle('is-active', on);
                    other.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                root.querySelectorAll('.avatar-rail').forEach((rail) => {
                    const on = rail.dataset.rail === tray;
                    rail.classList.toggle('is-active', on);
                    rail.hidden = !on;
                });
                const url = new URL(window.location.href);
                url.searchParams.set('tray', tray);
                window.history.replaceState({}, '', url);
            });
        });

        root.querySelectorAll('.avatar-item').forEach((btn) => {
            btn.addEventListener('click', async () => {
                if (pending) return;
                const itemId = btn.dataset.itemId;
                const item = itemOf(itemId);
                if (!item) return;
                const owned = btn.dataset.owned === '1' || Number(btn.dataset.price) <= 0;
                const price = Number(btn.dataset.price || 0);
                const balance = Number(window.KINTO_AVATAR.balance || 0);

                config = Object.assign({}, config, { [item.slot]: item.shape === 'none' ? null : itemId });
                const preview = document.getElementById('avatarPreview');
                if (preview) preview.innerHTML = render(config, { size: 'xl', animate: true });

                if (!owned && price > balance) {
                    setStatus('You need more Calm Points for ' + (btn.dataset.name || 'that item') + '.');
                    return;
                }

                pending = true;
                btn.classList.add('is-busy');
                try {
                    const action = owned ? 'equip' : 'buy_and_equip';
                    const data = await postAction(root, { action, item_id: itemId });
                    if (!owned) btn.dataset.owned = '1';
                    applyState(root, data.state, data.preview_html);
                    config = resolveConfig(data.state.config);
                    setStatus(data.message || 'Saved.');
                } catch (error) {
                    setStatus(error.message || 'Unable to update your avatar.');
                    if (preview) preview.innerHTML = render(window.KINTO_AVATAR.config, { size: 'xl', animate: true });
                    config = resolveConfig(window.KINTO_AVATAR.config);
                } finally {
                    pending = false;
                    btn.classList.remove('is-busy');
                }
            });
        });

        const toggle = document.getElementById('avatarPublicFace');
        toggle?.addEventListener('change', async () => {
            if (pending) {
                toggle.checked = !toggle.checked;
                return;
            }
            pending = true;
            try {
                const data = await postAction(root, { action: 'set_public_face', enabled: toggle.checked ? 1 : 0 });
                applyState(root, data.state, data.preview_html);
                setStatus(data.message);
            } catch (error) {
                toggle.checked = !toggle.checked;
                setStatus(error.message || 'Unable to update your public face.');
            } finally {
                pending = false;
            }
        });
    }

    window.KintoAvatar = { render, renderFaceHtml, mountStudio };

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('avatarStudio');
        if (root) mountStudio(root);
    });
})();
