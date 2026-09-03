const page = document.getElementById('jarPage') || document.getElementById('jarHistoryPage');
if (page) {
    const csrf = page.dataset.csrf || '';
    let entryCount = Number(page.dataset.entryCount || 0);
    const form = document.getElementById('jarAddForm');
    const message = document.getElementById('jarMessage');
    const type = document.getElementById('jarEntryType');
    const count = document.getElementById('jarEntryCount');
    const pullButton = document.getElementById('jarPullButton');
    const status = document.getElementById('jarFormStatus');
    const characterCount = document.getElementById('jarCharacterCount');
    const history = document.getElementById('jarHistoryList');
    const modal = document.getElementById('jarRevealModal');

    const post = async (url, payload) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ ...payload, csrf_token: csrf })
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) throw new Error(data.error || 'Something went wrong.');
        return data;
    };

    const updateCount = (next) => {
        entryCount = Math.max(0, Number(next) || 0);
        if (count) count.textContent = String(entryCount);
        if (pullButton) {
            pullButton.disabled = entryCount < 1;
            const label = pullButton.querySelector('span');
            if (label) label.textContent = entryCount > 0 ? 'Pull a random note' : 'Add your first note';
        }
    };

    const createHistoryCard = (entry) => {
        const card = document.createElement('article');
        card.className = `jar-history-card jar-type-${entry.entry_type}`;
        card.dataset.entryId = String(entry.id);
        const top = document.createElement('div');
        top.className = 'jar-history-card__top';
        const label = document.createElement('span');
        label.className = 'jar-type-label';
        label.textContent = entry.entry_type_label;
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'jar-delete-button';
        remove.dataset.deleteJarEntry = String(entry.id);
        remove.setAttribute('aria-label', 'Delete this Jar entry');
        remove.innerHTML = '<i data-lucide="trash-2"></i>';
        top.append(label, remove);
        const body = document.createElement('p');
        body.textContent = entry.message;
        const footer = document.createElement('footer');
        ['From ' + entry.author_name, entry.created_at_label, '0 pulls'].forEach(text => {
            const el = document.createElement('span');
            el.textContent = text;
            footer.appendChild(el);
        });
        card.append(top, body, footer);
        return card;
    };

    message?.addEventListener('input', () => {
        if (characterCount) characterCount.textContent = String(message.value.length);
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = form.querySelector('button[type="submit"]');
        if (submit) submit.disabled = true;
        if (status) status.textContent = 'Adding your note...';
        try {
            const data = await post('/challenge/api/jar_add.php', {
                target_user_id: 0,
                entry_type: type?.value || 'general',
                message: message?.value || ''
            });
            updateCount(data.entry_count);
            document.getElementById('jarEmptyHistory')?.remove();
            history?.prepend(createHistoryCard(data.entry));
            window.KintoJarScene?.addNote(data.entry.entry_type);
            if (message) message.value = '';
            if (characterCount) characterCount.textContent = '0';
            if (status) status.textContent = 'Your note is safely in the Jar.';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        } catch (error) {
            if (status) status.textContent = error.message;
        } finally {
            if (submit) submit.disabled = false;
        }
    });

    pullButton?.addEventListener('click', async () => {
        pullButton.disabled = true;
        const label = pullButton.querySelector('span');
        if (label) label.textContent = 'Choosing a note...';
        try {
            const data = await post('/challenge/api/jar_pull.php', {});
            await (window.KintoJarScene?.pullNote?.() || Promise.resolve());
            document.getElementById('jarRevealType').textContent = data.entry.entry_type_label;
            document.getElementById('jarRevealMessage').textContent = data.entry.message;
            document.getElementById('jarRevealAuthor').textContent = 'From ' + data.entry.author_name;
            document.getElementById('jarRevealDate').textContent = data.entry.created_at_label;
            document.getElementById('jarUnfoldedNote').className = `jar-unfolded-note jar-type-${data.entry.entry_type}`;
            modal?.classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('jarReturnButton')?.focus();
        } catch (error) {
            alert(error.message);
        } finally {
            pullButton.disabled = entryCount < 1;
            if (label) label.textContent = entryCount > 0 ? 'Pull a random note' : 'Add your first note';
        }
    });

    const closeReveal = () => {
        modal?.classList.remove('active');
        document.body.style.overflow = '';
        pullButton?.focus();
    };
    document.getElementById('jarReturnButton')?.addEventListener('click', closeReveal);
    modal?.addEventListener('click', event => { if (event.target === modal) closeReveal(); });

    history?.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-delete-jar-entry]');
        if (!button) return;
        if (!window.confirm('Remove this note from your Jar? This cannot be undone.')) return;
        button.disabled = true;
        try {
            await post('/challenge/api/jar_delete.php', { entry_id: Number(button.dataset.deleteJarEntry) });
            button.closest('.jar-history-card')?.remove();
            updateCount(entryCount - 1);
            window.KintoJarScene?.setCount(entryCount);
            if (!history.querySelector('.jar-history-card')) location.href = page.id === 'jarHistoryPage' ? '/challenge/app/jar_history.php' : '/challenge/app/jar.php';
        } catch (error) {
            alert(error.message);
            button.disabled = false;
        }
    });
}
