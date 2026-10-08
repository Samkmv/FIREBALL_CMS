$(function () {
    'use strict';
    const app = $('[data-chat-app]').first();
    const thread = window.FireballChatThread;
    if (!app.length || !thread) return;
    const data = JSON.parse($('[data-chat-workspace-data]').first().text() || '{}');
    const form = app.find('[data-chat-form]');
    const input = form.find('[data-chat-message-input]');
    const csrf = () => form.find('[name="needCSRFToken"]').val();
    const params = () => ({[thread.groupMode ? 'conversation_id' : 'user_id']: thread.activeId});
    const esc = value => $('<div>').text(String(value ?? '')).html();
    const errorText = request => request.responseJSON?.message || 'Не удалось выполнить действие. Попробуйте ещё раз.';
    const modal = selector => bootstrap.Modal.getOrCreateInstance($(selector)[0]);
    const groupModal = $('[data-chat-group-modal]');
    const groupError = groupModal.find('[data-chat-group-error]');
    let preferences = {};
    let draftLoadedFor = 0;
    let draftTimer = 0;
    const drafts = new Map();
    let archiveView = false;
    let groupPending = false;
    let searchTimer = 0;
    let searchRequest = null;
    let searchBefore = null;
    let lastRevision = null;
    let galleryBefore = null;
    let galleryPending = false;
    let historyPending = false;
    let historyExhausted = false;
    let historyRefreshVersion = 0;
    let forwardId = 0;
    const historyButton = $('<button type="button" class="btn btn-sm btn-outline-secondary chat-history-more">Более ранние сообщения</button>').insertBefore(app.find('[data-chat-messages]'));
    const searchMore = $('<button type="button" class="btn btn-sm btn-outline-secondary d-none">Ещё результаты</button>').appendTo(app.find('.chat-thread__searchbar'));

    const savePreference = changes => $.ajax({url: app.data('workspace-url'), method: 'POST', dataType: 'json', data: {...params(), needCSRFToken: csrf(), ...changes}});
    const filterSidebar = () => {
        const query = String($('[data-chat-contact-search]').first().val() || '').trim().toLowerCase();
        $('[data-chat-contact], [data-chat-group-link]').each(function () {
            const row = $(this);
            const archived = Number(row.attr('data-archived')) === 1;
            const match = !query || `${row.data('user-name') || ''} ${row.data('last-message-preview') || ''} ${row.data('user-role-label') || ''}`.toLowerCase().includes(query);
            row.toggleClass('d-none', archived !== archiveView || !match);
        });
        $('.chat-contact-group').each(function () {
            const rows = $(this).find('[data-chat-contact], [data-chat-group-link]');
            const visible = rows.toArray().filter(row => !row.classList.contains('d-none')).length;
            $(this).toggleClass('d-none', !visible);
            $(this).find('.chat-contact-group__head .badge').text(visible);
        });
        const visible = $('[data-chat-contact], [data-chat-group-link]').toArray().some(row => !row.classList.contains('d-none'));
        $('[data-chat-search-empty]').toggleClass('d-none', visible).text(archiveView ? 'В архиве пока нет диалогов.' : (app.data('search-empty-text') || 'Диалоги не найдены.'));
    };
    const refreshPreferenceLabels = () => {
        const labels = {pinned: preferences.pinned ? 'Открепить диалог' : 'Закрепить диалог', muted: preferences.muted ? 'Включить уведомления' : 'Выключить уведомления', archived: preferences.archived ? 'Вернуть из архива' : 'В архив'};
        Object.entries(labels).forEach(([key, label]) => $(document).find(`[data-chat-preference="${key}"]`).text(label));
        const rows = thread.groupMode ? $(`[data-chat-group-link][data-group-id="${thread.activeId}"]`) : $(`[data-chat-contact][data-user-id="${thread.activeId}"]`);
        rows.attr({'data-archived': preferences.archived ? 1 : 0, 'data-pinned': preferences.pinned ? 1 : 0});
        rows.each(function () {
            const row = $(this);
            row.find('.chat-preference-mark').remove();
            const marks = (preferences.pinned ? '<span class="chat-preference-mark me-1" title="Закреплён" aria-label="Закреплён"><i class="ci-paperclip" aria-hidden="true"></i></span>' : '')
                + (preferences.muted ? '<span class="chat-preference-mark me-1 text-body-secondary" title="Без уведомлений" aria-label="Без уведомлений"><i class="ci-bell-off" aria-hidden="true"></i></span>' : '');
            row.find('.fw-semibold').first().prepend(marks);
            const list = row.parent();
            list.children('[data-chat-contact], [data-chat-group-link]').sort((a, b) => Number(b.dataset.pinned || 0) - Number(a.dataset.pinned || 0)).appendTo(list);
        });
        filterSidebar();
    };
    const saveDraft = () => {
        if (!app.data('workspace-url') || form.hasClass('is-editing')) return;
        const id = thread.activeId;
        const queue = drafts.get(id) || {request: null, next: null};
        queue.next = String(input.val() || '');
        drafts.set(id, queue);
        const send = () => {
            if (queue.request || queue.next === null) return;
            const text = queue.next; queue.next = null;
            queue.request = $.ajax({url: app.data('workspace-url'), method: 'POST', dataType: 'json', data: {[thread.groupMode ? 'conversation_id' : 'user_id']: id, needCSRFToken: csrf(), draft: text}});
            queue.request.always(() => { queue.request = null; send(); });
        };
        send();
    };
    input.on('input', () => { clearTimeout(draftTimer); draftTimer = setTimeout(saveDraft, 500); });
    input.on('blur', saveDraft);
    $(document).on('chat:before-switch', () => { clearTimeout(draftTimer); saveDraft(); draftLoadedFor = 0; });
    $(document).on('chat:sent', () => { clearTimeout(draftTimer); saveDraft(); });
    $(document).on('chat:active-contact-changed', () => { draftLoadedFor = 0; lastRevision = null; searchBefore = null; historyExhausted = false; historyRefreshVersion++; searchMore.addClass('d-none'); historyButton.removeClass('d-none'); });

    const groupRoleLabel = role => ({owner: 'Владелец', admin: 'Администратор', member: 'Участник'}[role] || 'Участник');
    const renderMembers = () => {
        const group = data.group;
        if (!group) return;
        const owner = group.role === 'owner';
        const manager = owner || group.role === 'admin';
        const me = Number(app.data('current-user-id'));
        groupModal.find('[data-chat-group-profile], [data-chat-group-add]').toggleClass('d-none', !manager);
        groupModal.find('[data-chat-group-delete], [data-chat-group-owner-help]').toggleClass('d-none', !owner);
        groupModal.find('[data-chat-group-leave]').prop('disabled', owner);
        groupModal.find('[data-chat-group-member-list]').html((data.members || []).map(member => {
            const canChange = Number(member.id) !== me && member.group_role !== 'owner';
            const canRemove = manager && canChange && (owner || member.group_role !== 'admin');
            return `<div class="chat-group-settings-member"><div class="min-w-0 flex-grow-1"><strong class="d-block text-break">${esc(member.name)}</strong><span class="small text-body-secondary">${esc(groupRoleLabel(member.group_role))}</span></div>
                ${owner && canChange ? `<select class="form-select form-select-sm" aria-label="Роль ${esc(member.name)}" data-chat-member-role="${Number(member.id)}"><option value="member" ${member.group_role === 'member' ? 'selected' : ''}>Участник</option><option value="admin" ${member.group_role === 'admin' ? 'selected' : ''}>Администратор</option></select><button type="button" class="btn btn-sm btn-outline-secondary" data-chat-member-transfer="${Number(member.id)}" aria-label="Передать группу ${esc(member.name)}" title="Передать владение"><i class="ci-star" aria-hidden="true"></i></button>` : ''}
                ${canRemove ? `<button type="button" class="btn btn-sm btn-outline-danger" data-chat-member-remove="${Number(member.id)}" aria-label="Удалить участника ${esc(member.name)}"><i class="ci-trash" aria-hidden="true"></i></button>` : ''}</div>`;
        }).join(''));
        const members = new Set((data.members || []).map(member => Number(member.id)));
        const candidates = (data.candidates || []).filter(user => !members.has(Number(user.id)));
        groupModal.find('[data-chat-group-add] select').html('<option value="">Выберите участника</option>' + candidates.map(user => `<option value="${Number(user.id)}">${esc(user.name)}</option>`).join(''));
        groupModal.find('[data-chat-group-add] button').prop('disabled', !candidates.length);
    };
    const manageGroup = (action, fields = {}, files = null) => {
        if (groupPending) return;
        groupPending = true;
        groupError.addClass('d-none');
        const payload = files || new FormData();
        Object.entries({...params(), needCSRFToken: csrf(), action, ...fields}).forEach(([key, value]) => payload.set(key, value));
        groupModal.find('button, select, input').prop('disabled', true);
        $.ajax({url: app.data('group-manage-url'), method: 'POST', dataType: 'json', data: payload, processData: false, contentType: false})
            .done(response => {
                if (!response.status) { groupError.text(response.message).removeClass('d-none'); return; }
                if (response.redirect) { window.location.assign(response.redirect); return; }
                data.group = response.group; data.members = response.members;
                groupModal.find('[name="avatar"]').val(''); groupModal.find('[name="remove_avatar"]').prop('checked', false);
                renderMembers(); thread.reload();
            })
            .fail(request => groupError.text(errorText(request)).removeClass('d-none'))
            .always(() => { groupPending = false; groupModal.find('button, select, input').prop('disabled', false); renderMembers(); });
    };
    app.on('click', '[data-chat-group-settings]', () => { renderMembers(); groupModal.find('[name="title"]').val(data.group?.title || ''); modal('[data-chat-group-modal]').show(); });
    groupModal.find('[data-chat-group-profile]').on('submit', function (event) { event.preventDefault(); manageGroup('update', {}, new FormData(this)); });
    groupModal.find('[data-chat-group-add]').on('submit', function (event) { event.preventDefault(); manageGroup('add', {member_id: $(this).find('select').val()}); });
    groupModal.on('change', '[data-chat-member-role]', function () { manageGroup('role', {member_id: $(this).data('chat-member-role'), role: this.value}); });
    const confirmGroup = (text, action, fields = {}) => {
        groupModal.one('hidden.bs.modal', () => thread.confirm(text, () => manageGroup(action, fields)));
        modal('[data-chat-group-modal]').hide();
    };
    groupModal.on('click', '[data-chat-member-remove]', function () { confirmGroup('Удалить участника из группы? Он потеряет доступ к сообщениям и файлам.', 'remove', {member_id: $(this).data('chat-member-remove')}); });
    groupModal.on('click', '[data-chat-member-transfer]', function () { confirmGroup('Передать владение группой? Вы останетесь администратором.', 'transfer', {member_id: $(this).data('chat-member-transfer')}); });
    groupModal.find('[data-chat-group-leave]').on('click', () => confirmGroup('Выйти из группы?', 'leave'));
    groupModal.find('[data-chat-group-delete]').on('click', () => confirmGroup('Удалить группу? Все участники потеряют доступ к ней.', 'delete'));

    $(document).on('chat:payload', (_, response) => {
        if (lastRevision !== null && lastRevision !== response.revision) {
            if (app.find('[data-chat-message-search]').val()) search(false);
            if (thread.olderIds.length) refreshOlder();
        }
        lastRevision = response.revision;
        if (response.preferences) {
            preferences = response.preferences;
            refreshPreferenceLabels();
            if (draftLoadedFor !== thread.activeId) {
                draftLoadedFor = thread.activeId;
                if (!String(input.val() || '')) input.val(preferences.draft || '').trigger('input');
            }
        }
        if (response.group) {
            data.group = response.group; data.members = response.members || [];
            app.find('[data-chat-current-name]').text(response.group.title);
            app.find('[data-chat-current-avatar]').attr('alt', response.group.title);
            $(`[data-chat-group-link][data-group-id="${thread.activeId}"]`).attr('data-user-name', response.group.title).data('user-name', response.group.title).find('.fw-semibold').text(response.group.title);
            refreshPreferenceLabels();
            app.find('[data-chat-current-role]').text(`${response.group.member_count} участников`);
            app.find('[data-chat-current-status], [data-chat-current-presence]').hide();
            const avatarUrl = response.group.avatar_path ? `${String(app.data('group-manage-url')).replace(/manage$/, 'avatar')}?conversation_id=${thread.activeId}&v=${encodeURIComponent(response.group.avatar_path)}` : app.data('default-group-avatar');
            if (app.find('[data-chat-current-avatar]').attr('src') !== avatarUrl) app.find('[data-chat-current-avatar]').attr('src', avatarUrl);
            app.find('[data-chat-typing-indicator] > span').first().text(response.typing?.names?.length ? `${response.typing.names.join(', ')} печатает…` : 'Печатает…');
        }
        historyButton.toggleClass('d-none', historyExhausted || thread.messages.length < 100 || Boolean(app.find('[data-chat-message-search]').val()));

    });
    $(document).on('click', '[data-chat-preference]', function () {
        const key = String($(this).data('chat-preference'));
        savePreference({[key]: preferences[key] ? 0 : 1}).done(response => { preferences = response.preferences; refreshPreferenceLabels(); }).fail(request => window.toastr?.error(errorText(request)));
    });
    $(document).on('click', '[data-chat-archive-view]', function () {
        archiveView = Number($(this).data('chat-archive-view')) === 1;
        $('[data-chat-archive-view]').each(function () { const active = (Number($(this).data('chat-archive-view')) === 1) === archiveView; $(this).toggleClass('btn-secondary', active).toggleClass('btn-outline-secondary', !active); });
        filterSidebar();
    });
    $('[data-chat-contact-search]').on('input', filterSidebar);

    const loadHistory = () => {
        if (historyPending) return;
        const ids = thread.messages.map(message => Number(message.id));
        if (!ids.length) return;
        const id = thread.activeId;
        historyPending = true; historyButton.prop('disabled', true);
        $.getJSON(app.data('history-url'), {...params(), before: Math.min(...ids)})
            .done(response => { if (id !== thread.activeId) return; thread.prepend(response.messages || []); historyExhausted = !response.next_before; historyButton.toggleClass('d-none', historyExhausted); })
            .fail(request => window.toastr?.error(errorText(request)))
            .always(() => { historyPending = false; historyButton.prop('disabled', false); });
    };
    const refreshOlder = async () => {
        const ids = thread.olderIds;
        const activeId = thread.activeId;
        const version = ++historyRefreshVersion;
        try {
            const requests = [];
            for (let start = 0; start < ids.length; start += 300) requests.push($.getJSON(app.data('history-url'), {...params(), message_ids: ids.slice(start, start + 300)}));
            const responses = await Promise.all(requests);
            if (activeId === thread.activeId && version === historyRefreshVersion) thread.refreshOlder(ids, responses.flatMap(response => response.messages || []));
        } catch (_) { /* The next conversation revision retries; preserve the readable history on a network failure. */ }
    };
    historyButton.on('click', loadHistory);
    const search = (append = false) => {
        const query = String(app.find('[data-chat-message-search]').val() || '').trim();
        searchRequest?.abort();
        if (!query) { thread.search(null); searchMore.addClass('d-none'); return; }
        const id = thread.activeId;
        searchMore.prop('disabled', true);
        searchRequest = $.getJSON(app.data('history-url'), {...params(), q: query, before: append ? searchBefore : 0})
            .done(response => {
                if (id !== thread.activeId || query !== String(app.find('[data-chat-message-search]').val() || '').trim()) return;
                const messages = append ? [...(response.messages || []), ...thread.messages.filter(item => `${item.message} ${item.attachment?.name || ''}`.toLowerCase().includes(query.toLowerCase()))] : response.messages || [];
                thread.search(messages.filter((item, index, list) => list.findIndex(other => Number(other.id) === Number(item.id)) === index));
                searchBefore = response.next_before; searchMore.toggleClass('d-none', !searchBefore);
            })
            .fail(request => { if (request.statusText !== 'abort') window.toastr?.error(errorText(request)); })
            .always(() => searchMore.prop('disabled', false));
    };
    app.find('[data-chat-message-search]').on('input', () => { historyButton.toggleClass('d-none', historyExhausted || Boolean(app.find('[data-chat-message-search]').val()) || thread.messages.length < 100); clearTimeout(searchTimer); searchTimer = setTimeout(() => search(false), 250); });
    searchMore.on('click', () => search(true));

    const loadGallery = (append = false) => {
        if (galleryPending) return;
        galleryPending = true;
        const id = thread.activeId;
        const list = $('[data-chat-gallery-list]');
        if (!append) list.html('<p class="text-body-secondary">Загрузка…</p>');
        $.getJSON(app.data('history-url'), {...params(), media: 1, before: append ? galleryBefore : 0})
            .done(response => {
                if (id !== thread.activeId) return;
                const html = (response.messages || []).reverse().map(message => {
                    const a = message.attachment;
                    return `<button type="button" class="chat-gallery-item" data-chat-gallery-preview data-preview-url="${esc(a.url)}" data-preview-name="${esc(a.name)}" data-preview-kind="${esc(a.preview_kind)}" data-preview-type="${esc(a.type)}" data-preview-extension="${esc(a.extension)}">${a.is_image ? `<img src="${esc(a.url)}" alt="" loading="lazy">` : '<i class="ci-file fs-2" aria-hidden="true"></i>'}<span class="text-truncate w-100">${esc(a.name)}</span><small class="text-body-secondary">${esc(message.created_at)}</small></button>`;
                }).join('');
                if (append) list.append(html); else list.html(html || '<p class="text-body-secondary">Вложений пока нет.</p>');
                galleryBefore = response.next_before; $('[data-chat-gallery-more]').toggleClass('d-none', !galleryBefore);
            }).fail(request => list.text(errorText(request))).always(() => { galleryPending = false; });
    };
    $(document).on('click', '[data-chat-gallery]', () => { modal('[data-chat-gallery-modal]').show(); loadGallery(); });
    $('[data-chat-gallery-more]').on('click', () => loadGallery(true));
    $('[data-chat-gallery-list]').on('click', '[data-chat-gallery-preview]', function () {
        const trigger = $(this); const gallery = $('[data-chat-gallery-modal]');
        gallery.one('hidden.bs.modal', () => thread.preview(trigger)); modal('[data-chat-gallery-modal]').hide();
    });

    app.on('click', '[data-chat-forward-message]', function () {
        forwardId = Number($(this).data('chat-forward-message'));
        const message = thread.messages.find(item => Number(item.id) === forwardId);
        $('[data-chat-forward-preview]').text(message?.message || message?.attachment?.name || '');
        const options = (data.groups || []).map(group => `<option value="g:${Number(group.id)}">Группа: ${esc(group.title)}</option>`)
            .concat((data.candidates || []).map(user => `<option value="u:${Number(user.id)}">${esc(user.name)}</option>`));
        $('[data-chat-forward-form] select').html('<option value="">Выберите диалог</option>' + options.join(''));
        $('[data-chat-forward-error]').addClass('d-none'); modal('[data-chat-forward-modal]').show();
    });
    $('[data-chat-forward-form]').on('submit', function (event) {
        event.preventDefault();
        const [type, id] = String($(this).find('select').val()).split(':');
        const button = $(this).find('[type="submit"]').prop('disabled', true);
        $.ajax({url: app.data('forward-url'), method: 'POST', dataType: 'json', data: {...params(), needCSRFToken: csrf(), message_id: forwardId, [type === 'g' ? 'target_group_id' : 'target_user_id']: Number(id)}})
            .done(response => { if (response.status) { modal('[data-chat-forward-modal]').hide(); thread.reload(); } else $('[data-chat-forward-error]').text(response.message).removeClass('d-none'); })
            .fail(request => $('[data-chat-forward-error]').text(errorText(request)).removeClass('d-none')).always(() => button.prop('disabled', false));
    });

    // Mentions use visible participant names; suggestions never expose non-members.
    const mentions = $('<div class="chat-mention-suggestions d-none" role="listbox" aria-label="Упомянуть участника"></div>').insertBefore(form.find('.chat-composer__row'));
    input.on('input', function () {
        if (!thread.groupMode) return;
        const prefix = this.value.slice(0, this.selectionStart);
        const match = prefix.match(/(?:^|\s)@([^@\n]{0,40})$/u);
        if (!match) { mentions.addClass('d-none'); return; }
        const users = (data.members || []).filter(user => String(user.name).toLowerCase().startsWith(match[1].toLowerCase())).slice(0, 6);
        mentions.html(users.map(user => `<button type="button" role="option" class="btn btn-sm btn-outline-secondary" data-chat-mention="${Number(user.id)}">@${esc(user.name)}</button>`).join('')).toggleClass('d-none', !users.length);
    });
    mentions.on('click', '[data-chat-mention]', function () {
        const name = (data.members || []).find(user => Number(user.id) === Number($(this).data('chat-mention')))?.name;
        const el = input[0]; const pos = el.selectionStart; const prefix = el.value.slice(0, pos); const at = prefix.lastIndexOf('@');
        if (!name || at < 0) return;
        const next = prefix.slice(0, at) + '@' + name + ' ';
        el.value = next + el.value.slice(pos); el.focus(); el.setSelectionRange(next.length, next.length);
        mentions.addClass('d-none'); input.trigger('input');
    });
    $(document).on('chat:sent chat:before-switch', () => mentions.addClass('d-none'));
    input.on('blur', () => { if (!String(input.val() || '')) mentions.addClass('d-none'); });
    renderMembers(); filterSidebar();
});
