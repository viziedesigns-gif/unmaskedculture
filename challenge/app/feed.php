<?php
/**
 * Feed Page - Single Circle Chat
 * Kinto App
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/streak_service.php';
require_once __DIR__ . '/../includes/feed_service.php';
require_once __DIR__ . '/../includes/shop_service.php';
require_once __DIR__ . '/../includes/avatar_render.php';

requireOnboarding();

$user = getCurrentUser();
$userId = getCurrentUserId();
$userBubbleColor = $user['chat_bubble_color'] ?? '#1C1917';

// Legacy manage URL â†’ settings
if (isset($_GET['manage'])) {
    $circleRedirect = (int) ($_GET['circle'] ?? 0);
    redirect('/challenge/app/settings/circles.php' . ($circleRedirect > 0 ? '?circle=' . $circleRedirect : ''));
}

$circles = dbFetchAll(
    "SELECT ic.id, ic.name, ic.description, ic.invite_code, ic.created_by, icm.role
     FROM inner_circles ic
     JOIN inner_circle_members icm ON ic.id = icm.circle_id
     WHERE icm.user_id = ?
     ORDER BY icm.joined_at ASC",
    [$userId]
);

$queryCircleId = (int) ($_GET['circle'] ?? 0);
$requestedCircleId = $queryCircleId > 0
    ? $queryCircleId
    : (int) ($_SESSION['active_circle_id'] ?? 0);
$circle = null;
foreach ($circles as $candidateCircle) {
    if ($requestedCircleId > 0 && (int) $candidateCircle['id'] === $requestedCircleId) {
        $circle = $candidateCircle;
        break;
    }
}
if (!$circle && !empty($circles)) {
    $circle = $circles[0];
}

$members = [];
$circleId = 0;
$userDate = computeUserDate($user['timezone'] ?? DEFAULT_TIMEZONE);

if ($circle) {
    $circleId = (int) $circle['id'];
    $_SESSION['active_circle_id'] = $circleId;
    $members = getRankedCircleMembers($circleId, $userDate);
} else {
    unset($_SESSION['active_circle_id']);
}

$pageTitle = 'Feed';
$bodyClass = 'feed-immersive';
include __DIR__ . '/../includes/header.php';
?>

<div class="feed-page feed-page--immersive">
    <?php if (!$circle): ?>
        <div class="feed-empty-screen">
            <h1>Feed</h1>
            <p>Create or join an Inner Circle to start chatting.</p>
            <a href="/challenge/app/settings/circles.php" class="btn btn-primary">
                <i data-lucide="users"></i> Circles &amp; Feed Settings
            </a>
        </div>
    <?php else: ?>
        <header class="feed-progress-banner" aria-label="Circle checklist progress">
            <div class="feed-progress-banner__top">
                <div class="feed-progress-banner__title">
                    <span class="feed-circle-name"><?= h($circle['name']) ?></span>
                </div>
                <a id="feedSettingsLink" href="/challenge/app/settings/circles.php?circle=<?= $circleId ?>" class="feed-settings-link" title="Circle settings">
                    <i data-lucide="settings"></i>
                </a>
            </div>
            <div class="feed-progress-banner__members" id="feedMemberList" role="list">
                <?php foreach ($members as $member): ?>
                    <?php
                    $mid = (int) $member['id'];
                    $tone = $member['tone'];
                    $rank = (int) $member['rank'];
                    ?>
                    <a class="feed-member-chip tone-<?= h($tone) ?><?= $rank ? ' daily-rank-' . $rank : '' ?>"
                       role="listitem"
                       href="/challenge/app/member_profile.php?id=<?= $mid ?>"
                       title="<?= h($member['first_name']) ?>: <?= (int) $member['done'] ?>/<?= (int) $member['required'] ?>">
                        <span class="feed-member-chip__avatar<?= !empty($member['frame_css']) ? ' shop-avatar-frame ' . h($member['frame_css']) : '' ?>">
                            <?= renderUserPublicFace($member, 'sm') ?>
                            <?php if ($rank): ?>
                                <span class="feed-member-chip__crown rank-<?= $rank ?>" aria-label="Rank <?= $rank ?>">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M3 6.5 7.5 10 12 4l4.5 6L21 6.5l-2 11H5l-2-11Z" />
                                        <path d="M5 20h14" />
                                    </svg>
                                </span>
                            <?php endif; ?>
                        </span>
                        <span class="feed-member-chip__score"><?= (int) $member['done'] ?>/<?= (int) $member['required'] ?></span>
                        <span class="feed-member-chip__name"><?= h($member['first_name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </header>

        <div class="chat-container" id="chatContainer">
            <div class="feed-botanical" aria-hidden="true"></div>
            <div class="messages-container" id="messagesContainer">
                <div class="loading-spinner">
                    <i data-lucide="loader-2" class="spin"></i>
                    <span>Loading messages...</span>
                </div>
            </div>

            <form class="message-form" id="messageForm" onsubmit="sendMessage(event)">
                <div class="message-input-wrapper">
                    <span class="message-input-leaf" aria-hidden="true">
                        <i data-lucide="sprout"></i>
                    </span>
                    <textarea
                        id="messageInput"
                        placeholder="Share something real..."
                        rows="1"
                        maxlength="2000"
                        onkeydown="handleKeyDown(event)"
                    ></textarea>
                    <div class="mention-suggestions" id="mentionSuggestions" hidden></div>
                    <button type="submit" class="btn btn-primary btn-send" id="sendBtn" aria-label="Send">
                        <i data-lucide="send"></i>
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php if ($circle): ?>
<script src="<?= h(assetUrl('/challenge/assets/js/kinto-avatar.js')) ?>"></script>
<script>
const USER_ID = <?= $userId ?>;
const USER_FIRST_NAME = <?= json_encode($user['first_name']) ?>;
const USER_LAST_NAME = <?= json_encode($user['last_name']) ?>;
const USER_PROFILE_PIC = <?= json_encode($user['profile_pic']) ?>;
const USER_PROFILE_PIC_URL = <?= json_encode(profilePicUrl($user['profile_pic'] ?? null)) ?>;
const USER_FRAME_CSS = <?= json_encode(resolveFrameCssFromId($user['equipped_frame'] ?? null)) ?>;
const USER_USE_AVATAR = <?= !empty($user['avatar_public_face']) ? 'true' : 'false' ?>;
const USER_AVATAR_HTML = <?= json_encode(!empty($user['avatar_public_face']) ? renderKintoAvatar(resolveEquippedAvatar($user), ['size' => 'sm', 'animate' => false]) : '') ?>;
let ACTIVE_CIRCLE_ID = <?= $circleId ?>;
let CIRCLE_MEMBERS = <?= json_encode(array_map(static fn($m) => [
    'id' => (int) $m['id'],
    'name' => trim((string) $m['first_name'] . ' ' . (string) $m['last_name'])
], $members), JSON_UNESCAPED_SLASHES) ?>;
let USER_BUBBLE_COLOR = <?= json_encode($userBubbleColor) ?>;
const SELECTED_MENTIONS = new Map();

let lastMessageId = 0;
let eventSource = null;
let sentMessageIds = new Set();
let feedGeneration = 0;

document.addEventListener('DOMContentLoaded', function() {
    const syncFeedViewport = () => {
        const height = window.visualViewport?.height || window.innerHeight;
        document.documentElement.style.setProperty('--feed-viewport-height', `${Math.round(height)}px`);
        document.body.style.setProperty('--feed-viewport-height', `${Math.round(height)}px`);
    };
    syncFeedViewport();
    window.visualViewport?.addEventListener('resize', syncFeedViewport);
    window.visualViewport?.addEventListener('scroll', syncFeedViewport);
    window.addEventListener('resize', syncFeedViewport);

    if (ACTIVE_CIRCLE_ID > 0) {
        loadMessages();
        setInterval(refreshReactionCounts, 15000);
    }
    const input = document.getElementById('messageInput');
    if (input) {
        input.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 104) + 'px';
            updateMentionSuggestions();
        });
        input.addEventListener('focus', () => setTimeout(syncFeedViewport, 60));
        input.addEventListener('blur', () => setTimeout(syncFeedViewport, 60));
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();

    const botanical = document.querySelector('.feed-botanical');
    const messagesEl = document.getElementById('messagesContainer');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (botanical && messagesEl && !reduceMotion) {
        messagesEl.addEventListener('scroll', () => {
            const y = Math.min(messagesEl.scrollTop * 0.08, 36);
            botanical.style.setProperty('--botanical-y', `${y}px`);
        }, { passive: true });
    }
});

async function loadMessages() {
    const circleId = ACTIVE_CIRCLE_ID;
    const generation = feedGeneration;
    try {
        const response = await fetch(`/challenge/api/get_messages.php?circle=${circleId}`);
        const data = await response.json();
        if (circleId !== ACTIVE_CIRCLE_ID || generation !== feedGeneration) return;
        if (data.success) {
            renderMessages(data.messages || []);
            lastMessageId = data.messages.length > 0
                ? data.messages[data.messages.length - 1].id
                : 0;
            initSSE();
        }
    } catch (error) {
        if (circleId !== ACTIVE_CIRCLE_ID || generation !== feedGeneration) return;
        console.error('Error loading messages:', error);
        document.getElementById('messagesContainer').innerHTML =
            '<div class="empty-chat"><p>Failed to load messages</p></div>';
    }
}

function renderMessages(messages) {
    const container = document.getElementById('messagesContainer');
    if (messages.length === 0) {
        container.innerHTML = `
            <div class="empty-chat">
                <div class="empty-icon"><i data-lucide="message-circle"></i></div>
                <p>No messages yet. Start the conversation!</p>
            </div>`;
        if (typeof lucide !== 'undefined') lucide.createIcons();
        return;
    }
    let html = '';
    for (const msg of messages) {
        html += createMessageHTML(msg);
    }
    container.innerHTML = html;
    scrollToBottom();
}

function getContrastColor(hexColor) {
    const r = parseInt(hexColor.slice(1, 3), 16);
    const g = parseInt(hexColor.slice(3, 5), 16);
    const b = parseInt(hexColor.slice(5, 7), 16);
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    return luminance > 0.5 ? '#000000' : '#ffffff';
}

function createMessageHTML(msg, isOptimistic = false) {
    if (msg.message_type && msg.message_type !== 'message') {
        if (msg.message_type === 'system_milestone') {
            return `
            <div class="system-message system_milestone kinto-milestone" data-message-id="${msg.id}">
                <img class="kinto-milestone__art" src="/challenge/assets/brand/kinto-bowl.svg" alt="" aria-hidden="true">
                <div class="kinto-milestone__body">
                    <strong class="kinto-milestone__title">${escapeHtml(msg.message)}</strong>
                    <span class="kinto-milestone__sub">Every day you choose yourself, you heal. You're inspiring others. Keep going.</span>
                </div>
                <button type="button" class="kinto-milestone__heart" aria-label="Celebrate" onclick="toggleMessageHeart(${Number(msg.id)})">
                    <i data-lucide="heart"></i>
                </button>
            </div>`;
        }
        const icon = msg.message_type === 'system_join' ? 'user-plus' : 'trophy';
        return `
            <div class="system-message ${msg.message_type}" data-message-id="${msg.id}">
                <i data-lucide="${icon}"></i>
                <span>${escapeHtml(msg.message)}</span>
            </div>`;
    }

    const isOwn = msg.user_id == USER_ID;
    const bubbleColor = msg.chat_bubble_color || '#1C1917';
    const textColor = getContrastColor(bubbleColor);
    const avatar = renderAvatarHtml(msg);
    const frameCss = (msg.frame_css || '').trim();
    const avatarFrameClass = frameCss ? ` shop-avatar-frame ${escapeHtml(frameCss)}` : '';
    const profileUrl = `/challenge/app/member_profile.php?id=${encodeURIComponent(msg.user_id)}`;
    const bubbleStyle = isOwn ? `style="--bubble:${bubbleColor};--bubble-text:${textColor}"` : '';
    const optimisticClass = isOptimistic ? ' optimistic' : '';

    return `
        <div class="message ${isOwn ? 'own' : ''}${optimisticClass}" data-message-id="${msg.id}">
            <a class="message-avatar${avatarFrameClass}" href="${profileUrl}" aria-label="View ${escapeHtml(msg.first_name || 'member')}'s profile">${avatar}</a>
            <div class="message-content">
                <div class="message-header">
                    <span class="message-time">${isOptimistic ? 'Sending...' : formatTime(msg.created_at_utc)}</span>
                    <span class="message-author">${escapeHtml(msg.first_name + ' ' + msg.last_name)}</span>
                </div>
                <div class="message-text" ${bubbleStyle} ${isOptimistic ? '' : `onclick="handleMessageTap(event, ${Number(msg.id)})"`}>${formatMessageText(msg.message)}</div>
                ${Number(msg.heart_count) > 0 ? `<span class="message-reaction ${Number(msg.hearted_by_me) ? 'is-mine' : ''}" aria-label="${Number(msg.heart_count)} hearts"><i data-lucide="heart" class="message-reaction__icon" aria-hidden="true"></i><span class="message-reaction__count">${Number(msg.heart_count)}</span></span>` : ''}
            </div>
        </div>`;
}

function formatMessageText(message) {
    let html = escapeHtml(message).replace(/@\[([^\]]+)\]\((\d+)\)/g, '@$1');
    for (const member of CIRCLE_MEMBERS) {
        const token = escapeHtml(`@${member.name}`);
        html = html.split(token).join(`<span class="feed-mention">${token}</span>`);
    }
    return html.replace(/\n/g, '<br>');
}

let lastMessageTap = {messageId: 0, time: 0, hadSelection: false};
const pendingHeartRequests = new Set();

function handleMessageTap(event, messageId) {
    const now = Date.now();
    const hasSelection = !!(window.getSelection && String(window.getSelection()).trim() !== '');
    if (lastMessageTap.messageId === messageId && now - lastMessageTap.time <= 350) {
        const shouldReact = !lastMessageTap.hadSelection;
        lastMessageTap = {messageId: 0, time: 0, hadSelection: false};
        if (shouldReact) toggleMessageHeart(messageId);
        event.preventDefault();
        return;
    }
    lastMessageTap = {messageId, time: now, hadSelection: hasSelection};
}

async function toggleMessageHeart(messageId) {
    if (pendingHeartRequests.has(messageId)) return;
    pendingHeartRequests.add(messageId);
    try {
        const response = await fetch('/challenge/api/toggle_message_heart.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({message_id: messageId})
        });
        const data = await response.json();
        if (!data.success) throw new Error(data.error || 'Unable to react');
        applyReactionState(messageId, data.hearted, Number(data.heart_count) || 0);
    } catch (error) {
        alert(error.message || 'Unable to heart this message');
    } finally {
        pendingHeartRequests.delete(messageId);
    }
}

function applyReactionState(messageId, heartedByMe, count) {
    const content = document.querySelector(`.message[data-message-id="${messageId}"] .message-content`);
    if (!content) return;
    let reaction = content.querySelector('.message-reaction');
    if (count <= 0) {
        reaction?.remove();
        return;
    }
    if (!reaction) {
        reaction = document.createElement('span');
        reaction.className = 'message-reaction';
        content.appendChild(reaction);
    }
    reaction.classList.toggle('is-mine', !!heartedByMe);
    reaction.setAttribute('aria-label', `${count} hearts`);
    reaction.innerHTML = `<i data-lucide="heart" class="message-reaction__icon" aria-hidden="true"></i><span class="message-reaction__count">${count}</span>`;
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

async function refreshReactionCounts() {
    if (!ACTIVE_CIRCLE_ID || document.hidden) return;
    const circleId = ACTIVE_CIRCLE_ID;
    try {
        const response = await fetch(`/challenge/api/get_messages.php?circle=${circleId}`);
        const data = await response.json();
        if (circleId !== ACTIVE_CIRCLE_ID) return;
        if (!data.success) return;
        for (const message of data.messages || []) {
            if (pendingHeartRequests.has(Number(message.id))) continue;
            applyReactionState(Number(message.id), !!Number(message.hearted_by_me), Number(message.heart_count) || 0);
        }
    } catch (_) {
        // The live message stream remains primary; reaction refresh can retry.
    }
}

function updateMentionSuggestions() {
    const input = document.getElementById('messageInput');
    const box = document.getElementById('mentionSuggestions');
    if (!input || !box) return;
    const beforeCursor = input.value.slice(0, input.selectionStart);
    const match = beforeCursor.match(/(?:^|\s)@([\p{L}\p{N}.'-]*)$/u);
    if (!match) {
        box.hidden = true;
        return;
    }
    const query = match[1].toLowerCase();
    const choices = CIRCLE_MEMBERS.filter(member => member.id !== USER_ID && member.name.toLowerCase().includes(query)).slice(0, 6);
    if (!choices.length) {
        box.hidden = true;
        return;
    }
    box.innerHTML = choices.map(member =>
        `<button type="button" onclick="insertMention(${member.id})">@${escapeHtml(member.name)}</button>`
    ).join('');
    box.hidden = false;
}

function insertMention(memberId) {
    const input = document.getElementById('messageInput');
    const box = document.getElementById('mentionSuggestions');
    const member = CIRCLE_MEMBERS.find(item => item.id === memberId);
    if (!input || !member) return;
    const cursor = input.selectionStart;
    const before = input.value.slice(0, cursor);
    const start = before.lastIndexOf('@');
    input.value = input.value.slice(0, start) + `@${member.name} ` + input.value.slice(cursor);
    SELECTED_MENTIONS.set(member.id, member.name);
    const nextCursor = start + member.name.length + 2;
    input.focus();
    input.setSelectionRange(nextCursor, nextCursor);
    if (box) box.hidden = true;
}

function initSSE() {
    if (!ACTIVE_CIRCLE_ID) return;
    if (eventSource) eventSource.close();
    const streamCircleId = ACTIVE_CIRCLE_ID;
    eventSource = new EventSource(`/challenge/api/sse_messages.php?circle=${streamCircleId}&last_id=${lastMessageId}`);
    eventSource.onmessage = function(event) {
        const data = JSON.parse(event.data);
        if (streamCircleId !== ACTIVE_CIRCLE_ID) return;
        if (data.type === 'access_revoked') {
            eventSource.close();
            window.location.assign('/challenge/app/feed.php');
            return;
        }
        if (data.type === 'message' && data.message.circle_id == ACTIVE_CIRCLE_ID) {
            if (sentMessageIds.has(data.message.id)) {
                updateOptimisticMessage(data.message);
                sentMessageIds.delete(data.message.id);
            } else if (data.message.user_id != USER_ID || (data.message.message_type && data.message.message_type !== 'message')) {
                addMessageToUI(data.message);
            }
            lastMessageId = data.message.id;
        }
    };
    eventSource.onerror = function() {
        setTimeout(() => { if (streamCircleId === ACTIVE_CIRCLE_ID) initSSE(); }, 3000);
    };
}

function updateOptimisticMessage(msg) {
    const optimistic = document.querySelector('.message.optimistic');
    if (optimistic) {
        optimistic.outerHTML = createMessageHTML(msg);
    }
}

function addMessageToUI(msg) {
    const container = document.getElementById('messagesContainer');
    const emptyChat = container.querySelector('.empty-chat');
    if (emptyChat) emptyChat.remove();
    container.insertAdjacentHTML('beforeend', createMessageHTML(msg));
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    scrollToBottom();
}

async function sendMessage(e) {
    e.preventDefault();
    const input = document.getElementById('messageInput');
    const btn = document.getElementById('sendBtn');
    const message = input.value.trim();
    if (!message || !ACTIVE_CIRCLE_ID) return;
    const sendCircleId = ACTIVE_CIRCLE_ID;

    const tempId = 'temp-' + Date.now();
    const optimisticMsg = {
        id: tempId,
        user_id: USER_ID,
        first_name: USER_FIRST_NAME,
        last_name: USER_LAST_NAME,
        profile_pic: USER_PROFILE_PIC,
        profile_pic_url: USER_PROFILE_PIC_URL,
        frame_css: USER_FRAME_CSS,
        use_avatar: USER_USE_AVATAR,
        avatar_html: USER_AVATAR_HTML,
        chat_bubble_color: USER_BUBBLE_COLOR,
        message: message,
        created_at_utc: null
    };

    const container = document.getElementById('messagesContainer');
    const emptyChat = container.querySelector('.empty-chat');
    if (emptyChat) emptyChat.remove();
    container.insertAdjacentHTML('beforeend', createMessageHTML(optimisticMsg, true));
    scrollToBottom();

    input.value = '';
    input.style.height = 'auto';
    input.disabled = true;
    btn.disabled = true;

    try {
        const response = await fetch('/challenge/api/send_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                circle_id: sendCircleId,
                message: message,
                mention_ids: Array.from(SELECTED_MENTIONS.entries())
                    .filter(([, name]) => message.includes(`@${name}`))
                    .map(([id]) => id)
            })
        });
        const data = await response.json();
        if (sendCircleId !== ACTIVE_CIRCLE_ID) return;
        if (data.success) {
            SELECTED_MENTIONS.clear();
            sentMessageIds.add(data.message.id);
            updateOptimisticMessage(data.message);
            lastMessageId = data.message.id;
        } else {
            const optimistic = document.querySelector('.message.optimistic');
            if (optimistic) optimistic.remove();
            alert(data.error || 'Failed to send');
        }
    } catch (error) {
        console.error(error);
        const optimistic = document.querySelector('.message.optimistic');
        if (optimistic) optimistic.remove();
        alert('Failed to send message');
    } finally {
        input.disabled = false;
        btn.disabled = false;
        input.focus();
    }
}

function handleKeyDown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('messageForm').requestSubmit();
    }
}

function scrollToBottom() {
    const container = document.getElementById('messagesContainer');
    if (container) container.scrollTop = container.scrollHeight;
}

function formatTime(utc) {
    if (!utc) return '';
    const d = new Date(utc.replace(' ', 'T') + 'Z');
    return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

function resolveProfilePicUrl(msg) {
    if (msg && msg.profile_pic_url) return msg.profile_pic_url;
    const pic = (msg && msg.profile_pic) ? String(msg.profile_pic).trim() : '';
    if (!pic) return '';
    if (/^https?:\/\//i.test(pic) || pic.startsWith('/')) return pic;
    if (pic.startsWith('uploads/profile-pictures/')) return '/' + pic;
    if (pic.startsWith('profile-pictures/')) return '/uploads/' + pic;
    if (pic.startsWith('uploads/')) return '/challenge/' + pic;
    return '/challenge/' + pic.replace(/^\/+/, '');
}

function renderAvatarHtml(msg) {
    if (window.KintoAvatar && typeof window.KintoAvatar.renderFaceHtml === 'function') {
        return window.KintoAvatar.renderFaceHtml(msg);
    }
    const url = resolveProfilePicUrl(msg);
    const initial = ((msg && msg.first_name) ? msg.first_name : 'U').charAt(0).toUpperCase();
    if (!url) {
        return `<div class="avatar-placeholder">${escapeHtml(initial)}</div>`;
    }
    const safeInitial = escapeHtml(initial);
    return `<img src="${escapeHtml(url)}" alt="" data-initial="${safeInitial}" onerror="window.__feedAvatarFallback && window.__feedAvatarFallback(this)">`;
}

window.__feedAvatarFallback = function(img) {
    const fallback = document.createElement('div');
    fallback.className = 'avatar-placeholder';
    fallback.textContent = img.dataset.initial || 'U';
    img.replaceWith(fallback);
};

window.addEventListener('beforeunload', () => {
    if (eventSource) eventSource.close();
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
