$(function () {
    const chatApp = $('[data-chat-app]');
    if (!chatApp.length) {
        return;
    }

    const mobileFullscreenQuery = window.matchMedia('(max-width: 767.98px)');
    const rootElement = document.documentElement;
    let mobileViewportFrame = 0;

    const syncMobileFullscreen = () => {
        mobileViewportFrame = 0;
        const isMobile = mobileFullscreenQuery.matches;

        if (!rootElement.classList.contains('chat-viewport-fullscreen')) {
            window.scrollTo({top: 0, left: 0, behavior: 'auto'});
        }

        rootElement.classList.add('chat-viewport-fullscreen');
        document.body.classList.add('chat-viewport-fullscreen');
        rootElement.classList.toggle('chat-mobile-fullscreen', isMobile);
        document.body.classList.toggle('chat-mobile-fullscreen', isMobile);

        const viewport = window.visualViewport;
        const viewportTop = Math.max(0, Number(viewport ? viewport.offsetTop : 0) || 0);
        const viewportHeight = Math.max(0, Number(viewport ? viewport.height : window.innerHeight) || window.innerHeight);
        const siteHeader = document.querySelector('body > header') || document.querySelector('header');
        const headerRect = siteHeader ? siteHeader.getBoundingClientRect() : null;
        const visibleHeaderHeight = headerRect
            ? Math.max(0, Math.min(viewportHeight, headerRect.bottom - viewportTop))
            : 0;

        rootElement.style.setProperty('--chat-mobile-viewport-top', `${viewportTop + visibleHeaderHeight}px`);
        rootElement.style.setProperty('--chat-mobile-viewport-height', `${Math.max(0, viewportHeight - visibleHeaderHeight)}px`);
    };

    const scheduleMobileFullscreenSync = () => {
        if (mobileViewportFrame) {
            cancelAnimationFrame(mobileViewportFrame);
        }
        mobileViewportFrame = requestAnimationFrame(syncMobileFullscreen);
    };

    if (typeof mobileFullscreenQuery.addEventListener === 'function') {
        mobileFullscreenQuery.addEventListener('change', scheduleMobileFullscreenSync);
    } else if (typeof mobileFullscreenQuery.addListener === 'function') {
        mobileFullscreenQuery.addListener(scheduleMobileFullscreenSync);
    }
    window.addEventListener('resize', scheduleMobileFullscreenSync, {passive: true});
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', scheduleMobileFullscreenSync, {passive: true});
        window.visualViewport.addEventListener('scroll', scheduleMobileFullscreenSync, {passive: true});
    }
    syncMobileFullscreen();

    const fetchUrl = String(chatApp.data('fetch-url') || '');
    const sendUrl = String(chatApp.data('send-url') || '');
    const deleteUrl = String(chatApp.data('delete-url') || '');
    const clearUrl = String(chatApp.data('clear-url') || '');
    const auditUrl = String(chatApp.data('audit-url') || '');
    const auditClearUrl = String(chatApp.data('audit-clear-url') || '');
    const bootstrapApi = typeof bootstrap !== 'undefined'
        ? bootstrap
        : (window.bootstrap || null);
    const verifiedTitle = String(chatApp.data('verified-title') || 'Verified customer');
    const previewUnavailableText = String(chatApp.data('preview-unavailable-text') || 'Preview unavailable.');
    const previewLoadingText = String(chatApp.data('preview-loading-text') || 'Loading...');
    const maxFileSize = Number(chatApp.data('max-file-size')) || (200 * 1024 * 1024);
    const blockedExtensions = ['exe', 'bat', 'cmd', 'sh', 'apk', 'js'];
    const allowedExtensions = String(chatApp.find('[data-chat-attachment]').attr('accept') || '')
        .split(',')
        .map((item) => item.trim().replace(/^\./, '').toLowerCase())
        .filter(Boolean);

    const getContactButtons = () => chatApp.find('[data-chat-contact]');
    const contactGroupLists = chatApp.find('[data-chat-contact-group-list]');
    const contactGroups = chatApp.find('[data-chat-contact-group]');
    const contactSearchInput = chatApp.find('[data-chat-contact-search]');
    const contactSearchEmpty = chatApp.find('[data-chat-search-empty]');
    const messagesBox = chatApp.find('[data-chat-messages]');
    const currentName = chatApp.find('[data-chat-current-name]');
    const currentRole = chatApp.find('[data-chat-current-role]');
    const currentAvatar = chatApp.find('[data-chat-current-avatar]');
    const currentStatus = chatApp.find('[data-chat-current-status]');
    const messageSearchInput = chatApp.find('[data-chat-message-search]');
    const messageSearchResults = chatApp.find('[data-chat-message-search-results]');
    const selectionCountBadge = chatApp.find('[data-chat-selection-count]');
    const form = chatApp.find('[data-chat-form]');
    const userIdInput = form.find('[data-chat-user-id]');
    const messageInput = form.find('[data-chat-message-input]');
    const attachmentInput = form.find('[data-chat-attachment]');
    const siteFileInput = form.find('[data-chat-site-file-input]');
    const cameraInput = form.find('[data-chat-camera-input]');
    const galleryInput = form.find('[data-chat-gallery-input]');
    const recordVoiceButton = form.find('[data-chat-record-voice]');
    const voiceRecorderPanel = form.find('[data-chat-voice-recorder]');
    const voiceRecordingTimer = form.find('[data-chat-recording-timer]');
    const voiceStopButton = form.find('[data-chat-record-stop]');
    const voiceCancelButton = form.find('[data-chat-record-cancel]');
    const sendButton = form.find('button[type="submit"]');
    const dropzone = form.find('[data-chat-dropzone]');
    const dropzoneOverlay = form.find('[data-chat-dropzone-overlay]');
    const pendingAttachment = form.find('[data-chat-pending-attachment]');
    const pendingList = form.find('[data-chat-pending-list]');
    const pendingMeta = form.find('[data-chat-pending-meta]');
    const progressWrap = form.find('[data-chat-upload-progress-wrap]');
    const progressBar = form.find('[data-chat-upload-progress]');
    const offcanvasElement = $('#accountSidebar');
    const previewModalElement = $('[data-chat-preview-modal]').first();
    const previewModalTitle = previewModalElement.find('[data-chat-preview-modal-title]');
    const previewModalBody = previewModalElement.find('[data-chat-preview-modal-body]');
    const previewModalOpen = previewModalElement.find('[data-chat-preview-modal-open]');
    const previewModalDownload = previewModalElement.find('[data-chat-preview-modal-download]');
    const auditModalElement = $('[data-chat-audit-modal]').first();
    const auditList = auditModalElement.find('[data-chat-audit-list]');
    const auditClearButton = auditModalElement.find('[data-chat-clear-audit]');
    const confirmModalElement = $('[data-chat-confirm-modal]').first();
    const confirmModalMessage = confirmModalElement.find('[data-chat-confirm-message]');
    const confirmModalReasonWrap = confirmModalElement.find('[data-chat-confirm-reason-wrap]');
    const confirmModalReason = confirmModalElement.find('[data-chat-confirm-reason]');
    const confirmModalSubmit = confirmModalElement.find('[data-chat-confirm-submit]');
    const selectionToggleButton = chatApp.find('[data-chat-selection-toggle]');
    const selectionCancelButton = chatApp.find('[data-chat-selection-cancel]');
    const deleteSelectedButton = chatApp.find('[data-chat-delete-selected]');

    const offcanvas = offcanvasElement.length && bootstrapApi && bootstrapApi.Offcanvas
        ? bootstrapApi.Offcanvas.getOrCreateInstance(offcanvasElement[0])
        : null;
    const previewModal = previewModalElement.length && bootstrapApi && bootstrapApi.Modal
        ? bootstrapApi.Modal.getOrCreateInstance(previewModalElement[0])
        : null;
    const auditModal = auditModalElement.length && bootstrapApi && bootstrapApi.Modal
        ? bootstrapApi.Modal.getOrCreateInstance(auditModalElement[0])
        : null;
    const confirmModal = confirmModalElement.length && bootstrapApi && bootstrapApi.Modal
        ? bootstrapApi.Modal.getOrCreateInstance(confirmModalElement[0])
        : null;

    offcanvasElement.on('show.bs.offcanvas shown.bs.offcanvas', function () {
        document.body.classList.add('chat-sidebar-open');
    });
    offcanvasElement.on('hidden.bs.offcanvas', function () {
        document.body.classList.remove('chat-sidebar-open');
    });

    const state = {
        messages: [],
        renderedSignature: '',
        messagesRequestId: 0,
        auditRequestId: 0,
        previewRequestId: 0,
        pendingFiles: [],
        pendingSiteAttachments: [],
        pendingObjectUrls: [],
        dragDepth: 0,
        selectionMode: false,
        selectedIds: new Set(),
        confirmAction: null,
        moderationRequestPending: false,
        voiceRecorder: null,
        voiceStream: null,
        voiceChunks: [],
        voiceStartedAt: 0,
        voiceTimerId: null,
        discardVoiceRecording: false,
        voiceRequestId: 0,
        messagesLoadErrorShown: false,
        canModerate: String(chatApp.data('can-moderate')) === '1',
        canBulkDelete: String(chatApp.data('can-bulk-delete')) === '1',
        canClearChat: String(chatApp.data('can-clear-chat')) === '1',
        canViewAudit: String(chatApp.data('can-view-audit')) === '1',
        canDeleteAudit: String(chatApp.data('can-delete-audit')) === '1',
    };

    const escapeHtml = (text) => $('<div>').text(text || '').html();
    const escapeRegExp = (text) => String(text || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const activeContactId = () => Number(userIdInput.val()) || 0;
    const getStatusText = (isOnline) => (
        isOnline ? String(chatApp.data('online-text') || 'Online') : String(chatApp.data('offline-text') || 'Offline')
    );
    const getPreviewText = (value) => String(value || chatApp.data('empty-text') || '');
    const getGroupKey = (value) => String(value || 'clients');
    const currentSearchQuery = () => String(messageSearchInput.val() || '').trim();
    const canUseShowPicker = (input) => input && typeof input.showPicker === 'function';

    const showFlashAlert = (type, message, title) => {
        if (type !== 'error') {
            return;
        }

        const alertType = 'error';
        const alertMessage = String(message || '').trim();
        if (!alertMessage || !window.toastr || typeof window.toastr[alertType] !== 'function') {
            return;
        }

        window.toastr[alertType](alertMessage, title);
    };

    const resizeMessageInput = () => {
        const input = messageInput[0];
        if (!input) {
            return;
        }

        input.style.height = 'auto';
        const computedStyle = window.getComputedStyle(input);
        const minHeight = Number.parseFloat(computedStyle.minHeight) || 44;
        const maxHeight = Number.parseFloat(computedStyle.maxHeight) || 132;
        const nextHeight = Math.max(minHeight, Math.min(input.scrollHeight, maxHeight));
        input.style.height = `${nextHeight}px`;
        input.style.overflowY = input.scrollHeight > maxHeight ? 'auto' : 'hidden';

        if (!input.dataset.chatBaseHeight || String(input.value || '') === '') {
            input.dataset.chatBaseHeight = String(nextHeight);
        }

        const isMobile = typeof window.matchMedia === 'function'
            && window.matchMedia('(max-width: 767.98px)').matches;
        const baseHeight = Number(input.dataset.chatBaseHeight) || nextHeight;
        const composerGrowth = isMobile ? Math.max(0, nextHeight - baseHeight) : 0;
        chatApp[0].style.setProperty('--chat-composer-growth', `${composerGrowth}px`);
    };

    const openNativeFilePicker = (input) => {
        if (!input || input.disabled) {
            return;
        }

        input.value = '';

        if (canUseShowPicker(input)) {
            try {
                input.showPicker();
                return;
            } catch (error) {
                // Fall through to click() when showPicker is unavailable for the current browser state.
            }
        }

        input.click();
    };

    const formatBytes = (bytes) => {
        const size = Number(bytes) || 0;
        if (size >= 1024 * 1024) {
            return `${(size / 1024 / 1024).toFixed(1)} MB`;
        }
        if (size >= 1024) {
            return `${Math.ceil(size / 1024)} KB`;
        }
        return `${size} B`;
    };

    const formatVoiceTime = (seconds) => {
        const totalSeconds = Math.max(0, Math.floor(Number(seconds) || 0));
        const minutes = Math.floor(totalSeconds / 60);
        const remainingSeconds = String(totalSeconds % 60).padStart(2, '0');
        return `${minutes}:${remainingSeconds}`;
    };

    const voiceWaveHeights = [34, 58, 82, 48, 70, 94, 62, 42, 78, 56, 88, 66, 38, 74, 98, 54, 84, 46, 68, 92, 60, 36, 76, 52, 86, 64, 44, 80, 58, 90, 50, 72];
    const renderVoiceWaveBars = () => voiceWaveHeights
        .map((height) => `<span data-chat-voice-bar aria-hidden="true" style="--voice-bar-height: ${height}%"></span>`)
        .join('');

    const renderVoicePlayerMarkup = (sourceUrl, fileName, fileSize = 0, extraClass = 'mt-2') => {
        const url = escapeHtml(sourceUrl);
        const name = escapeHtml(fileName || chatApp.data('voice-message-text') || 'Voice message');
        const playText = escapeHtml(chatApp.data('voice-play-text') || 'Play voice message');
        const seekText = escapeHtml(chatApp.data('voice-seek-text') || 'Seek voice message');
        const voiceText = escapeHtml(chatApp.data('voice-message-text') || 'Voice message');
        const downloadText = escapeHtml(chatApp.data('download-file-text') || 'Download file');
        const voiceSize = Number(fileSize) > 0 ? ` · ${escapeHtml(formatBytes(fileSize))}` : '';

        return `
            <div class="chat-message-voice ${extraClass}" data-chat-voice-player>
                <audio class="chat-message-voice__audio" preload="metadata" src="${url}" data-chat-voice-audio></audio>
                <button class="chat-message-voice__toggle" type="button" data-chat-voice-toggle aria-label="${playText}" title="${playText}">
                    <i class="ci-play" aria-hidden="true"></i>
                </button>
                <div class="chat-message-voice__main">
                    <div class="chat-message-voice__wave">
                        ${renderVoiceWaveBars()}
                        <input
                            class="chat-message-voice__progress"
                            type="range"
                            min="0"
                            max="100"
                            step="0.1"
                            value="0"
                            aria-label="${seekText}"
                            data-chat-voice-progress
                        >
                    </div>
                    <div class="chat-message-voice__info">
                        <time data-chat-voice-time>0:00</time>
                        <span>${voiceText}${voiceSize}</span>
                    </div>
                </div>
                <a class="chat-message-voice__download" href="${url}" download="${name}" aria-label="${downloadText}" title="${downloadText}">
                    <i class="ci-download" aria-hidden="true"></i>
                </a>
            </div>
        `;
    };

    const setVoicePlayerState = (player, isPlaying) => {
        const button = player.find('[data-chat-voice-toggle]');
        const label = String(chatApp.data(isPlaying ? 'voice-pause-text' : 'voice-play-text') || (isPlaying ? 'Pause voice message' : 'Play voice message'));

        player.toggleClass('is-playing', isPlaying);
        button.attr({ 'aria-label': label, title: label });
        button.find('i').attr('class', isPlaying ? 'ci-pause' : 'ci-play');
    };

    const syncVoicePlayer = (playerElement) => {
        const player = $(playerElement);
        const audio = player.find('[data-chat-voice-audio]')[0];
        if (!audio) {
            return;
        }

        const duration = Number.isFinite(audio.duration) ? audio.duration : 0;
        const currentTime = Number.isFinite(audio.currentTime) ? audio.currentTime : 0;
        const progressValue = duration > 0 ? Math.max(0, Math.min(100, (currentTime / duration) * 100)) : 0;
        const currentLabel = formatVoiceTime(currentTime);
        const durationLabel = formatVoiceTime(duration);
        const progress = player.find('[data-chat-voice-progress]');
        const bars = player.find('[data-chat-voice-bar]');
        const activeBars = Math.round((progressValue / 100) * bars.length);

        progress
            .val(progressValue)
            .attr('aria-valuetext', duration > 0 ? `${currentLabel} / ${durationLabel}` : currentLabel);
        player.find('[data-chat-voice-time]').text(duration > 0 ? `${currentLabel} / ${durationLabel}` : currentLabel);
        bars.each(function (index) {
            $(this).toggleClass('is-active', index < activeBars);
        });
    };

    const initializeVoicePlayers = (scope = messagesBox) => {
        $(scope).find('[data-chat-voice-player]').each(function () {
            const playerElement = this;
            const player = $(playerElement);
            const audio = player.find('[data-chat-voice-audio]')[0];
            if (!audio || player.data('chat-voice-ready')) {
                return;
            }

            player.data('chat-voice-ready', true);
            ['loadedmetadata', 'durationchange', 'timeupdate'].forEach((eventName) => {
                audio.addEventListener(eventName, () => syncVoicePlayer(playerElement));
            });
            audio.addEventListener('play', () => {
                chatApp.find('[data-chat-voice-audio]').each(function () {
                    if (this !== audio && !this.paused) {
                        this.pause();
                    }
                });
                setVoicePlayerState(player, true);
            });
            audio.addEventListener('pause', () => setVoicePlayerState(player, false));
            audio.addEventListener('ended', () => {
                audio.currentTime = 0;
                setVoicePlayerState(player, false);
                syncVoicePlayer(playerElement);
            });
            audio.addEventListener('error', () => {
                player.addClass('is-error');
                setVoicePlayerState(player, false);
            });

            setVoicePlayerState(player, false);
            syncVoicePlayer(playerElement);
        });
    };

    const highlightText = (value, query) => {
        const text = String(value || '');
        const normalizedQuery = String(query || '').trim();
        if (normalizedQuery === '') {
            return escapeHtml(text);
        }

        const pattern = new RegExp(escapeRegExp(normalizedQuery), 'ig');
        let lastIndex = 0;
        let output = '';
        let match = null;

        while ((match = pattern.exec(text)) !== null) {
            output += escapeHtml(text.slice(lastIndex, match.index));
            output += `<mark class="chat-message-highlight">${escapeHtml(match[0])}</mark>`;
            lastIndex = match.index + match[0].length;
        }

        output += escapeHtml(text.slice(lastIndex));
        return output;
    };

    const buildMessagesSignature = (messages) => messages.map((item) => [
        Number(item.id) || 0,
        Number(item.sender_id) || 0,
        Number(item.is_read) || 0,
        item.created_at || '',
        item.message || '',
        item.attachment && item.attachment.url ? item.attachment.url : ''
    ].join(':')).join('|');

    const isNearBottom = (element, threshold = 24) => (
        (element.scrollHeight - (element.scrollTop + element.clientHeight)) <= threshold
    );

    const parseChatDate = (value) => {
        const normalized = String(value || '').trim();
        const match = normalized.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
        if (match) {
            return new Date(
                Number(match[1]),
                Number(match[2]) - 1,
                Number(match[3]),
                Number(match[4] || 0),
                Number(match[5] || 0),
                Number(match[6] || 0)
            );
        }

        const parsed = new Date(normalized);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    };

    const getMessageDayKey = (value) => {
        const parsed = parseChatDate(value);
        if (!parsed) {
            return String(value || '').slice(0, 10);
        }

        return [
            parsed.getFullYear(),
            String(parsed.getMonth() + 1).padStart(2, '0'),
            String(parsed.getDate()).padStart(2, '0'),
        ].join('-');
    };

    const formatMessageTime = (value) => {
        const parsed = parseChatDate(value);
        if (!parsed) {
            const match = String(value || '').match(/(\d{2}):(\d{2})/);
            return match ? `${match[1]}:${match[2]}` : String(value || '');
        }

        return new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
            hour: '2-digit',
            minute: '2-digit',
        }).format(parsed);
    };

    const formatMessageDate = (value) => {
        const parsed = parseChatDate(value);
        if (!parsed) {
            return String(value || '').slice(0, 10);
        }

        const now = new Date();
        return new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
            day: 'numeric',
            month: 'long',
            year: parsed.getFullYear() === now.getFullYear() ? undefined : 'numeric',
        }).format(parsed);
    };

    const canGroupMessages = (first, second) => {
        if (!first || !second || Number(first.sender_id) !== Number(second.sender_id)) {
            return false;
        }

        if (getMessageDayKey(first.created_at) !== getMessageDayKey(second.created_at)) {
            return false;
        }

        const firstDate = parseChatDate(first.created_at);
        const secondDate = parseChatDate(second.created_at);
        if (!firstDate || !secondDate) {
            return true;
        }

        return Math.abs(secondDate.getTime() - firstDate.getTime()) <= 5 * 60 * 1000;
    };

    const renderPresenceBadge = (isOnline) => `
        <span class="rounded-circle d-inline-block flex-shrink-0 ${isOnline ? 'bg-success' : 'bg-secondary'}" style="width: 8px; height: 8px;"></span>
        <span>${escapeHtml(getStatusText(isOnline))}</span>
    `;

    const updateCurrentContactPresence = (isOnline) => {
        currentStatus
            .toggleClass('text-success', isOnline)
            .toggleClass('text-body-secondary', !isOnline)
            .html(renderPresenceBadge(isOnline));
    };

    const renderVerifiedBadge = (role) => {
        if (!['creator', 'admin'].includes(String(role || '').trim())) {
            return '';
        }

        const title = escapeHtml(verifiedTitle);
        return `<i class="ci-check-circle text-success align-middle ms-1" data-public-verified-badge="1" data-bs-toggle="tooltip" data-bs-custom-class="tooltip-sm" title="${title}" aria-label="${title}"></i>`;
    };

    const initializeCurrentNameTooltip = () => {
        if (!bootstrapApi || !bootstrapApi.Tooltip) {
            return;
        }

        currentName.find('[data-public-verified-badge]').each(function () {
            bootstrapApi.Tooltip.getOrCreateInstance(this);
        });
    };

    const getAttachmentIcon = (kind, previewKind) => {
        if (previewKind === 'image') {
            return 'ci-image';
        }
        if (previewKind === 'audio') {
            return 'ci-music';
        }
        if (previewKind === 'video') {
            return 'ci-video';
        }
        if (previewKind === 'pdf' || kind === 'document') {
            return 'ci-file-text';
        }
        if (kind === 'archive') {
            return 'ci-archive';
        }
        return 'ci-file';
    };

    const getPendingFileKind = (file) => {
        const type = String(file.type || '');
        const extension = String((file.name || '').split('.').pop() || '').toLowerCase();

        if (type.startsWith('image/')) {
            return 'image';
        }
        if (type.startsWith('video/')) {
            return 'video';
        }
        if (type.startsWith('audio/')) {
            return 'audio';
        }
        if (extension === 'pdf') {
            return 'pdf';
        }
        if (['zip', 'rar', '7z'].includes(extension)) {
            return 'archive';
        }
        return 'file';
    };

    const normalizeSiteAttachmentPath = (value) => {
        const inputValue = String(value || '').trim();
        if (inputValue === '') {
            return '';
        }

        let path = inputValue;
        try {
            const parsed = new URL(inputValue, window.location.origin);
            path = String(parsed.pathname || '');
        } catch (error) {
        }

        path = path.replace(/\\/g, '/').replace(/\/{2,}/g, '/');
        if (path.startsWith('/uploads/')) {
            path = path.slice(1);
        }

        if (!path.startsWith('uploads/') || path.includes('..')) {
            return '';
        }

        return path;
    };

    const buildSiteAttachmentUrl = (path) => {
        if (path === '') {
            return '';
        }

        return path.startsWith('/') ? path : `/${path}`;
    };

    const getSiteAttachmentKind = (path) => {
        const extension = String(path.split('.').pop() || '').toLowerCase();
        if (['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'].includes(extension)) {
            return 'image';
        }
        if (['mp4', 'webm', 'mov', 'avi', 'mkv', 'mpeg', 'mpg'].includes(extension)) {
            return 'video';
        }
        if (['mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac'].includes(extension)) {
            return 'audio';
        }
        if (extension === 'pdf') {
            return 'pdf';
        }
        if (['zip', 'rar', '7z'].includes(extension)) {
            return 'archive';
        }

        return 'file';
    };

    const isExtensionAllowed = (extension, kind = 'file') => {
        if (blockedExtensions.includes(extension)) {
            return false;
        }

        if (!allowedExtensions.length) {
            return true;
        }

        if (allowedExtensions.includes(extension)) {
            return true;
        }

        if (kind === 'image' && allowedExtensions.includes('image/*')) {
            return true;
        }
        if (kind === 'video' && allowedExtensions.includes('video/*')) {
            return true;
        }
        if (kind === 'audio' && allowedExtensions.includes('audio/*')) {
            return true;
        }

        return false;
    };

    const resetPendingPreviewUrls = () => {
        state.pendingObjectUrls.forEach((url) => {
            if (url) {
                URL.revokeObjectURL(url);
            }
        });
        state.pendingObjectUrls = [];
    };

    const clearPendingAttachment = () => {
        resetPendingPreviewUrls();
        state.pendingFiles = [];
        state.pendingSiteAttachments = [];
        attachmentInput.val('');
        siteFileInput.val('');
        cameraInput.val('');
        galleryInput.val('');
        pendingAttachment.addClass('d-none');
        pendingList.empty();
        pendingMeta.text('');
        progressWrap.addClass('d-none');
        progressBar.css('width', '0%');
    };

    const renderPendingAttachment = () => {
        const totalItems = state.pendingFiles.length + state.pendingSiteAttachments.length;
        if (!totalItems) {
            clearPendingAttachment();
            return;
        }

        resetPendingPreviewUrls();
        let totalSize = 0;
        let html = '';

        state.pendingFiles.forEach((file) => {
            totalSize += Number(file.size) || 0;
            const kind = getPendingFileKind(file);
            const previewUrl = ['image', 'video', 'audio'].includes(kind) ? URL.createObjectURL(file) : '';
            if (previewUrl) {
                state.pendingObjectUrls.push(previewUrl);
            }

            let previewHtml = `<i class="${getAttachmentIcon(kind, kind)} fs-3 text-body-secondary"></i>`;
            if (kind === 'image') {
                previewHtml = `<img src="${escapeHtml(previewUrl)}" alt="${escapeHtml(file.name)}">`;
            } else if (kind === 'video') {
                previewHtml = `<video src="${escapeHtml(previewUrl)}" muted></video>`;
            } else if (kind === 'audio') {
                previewHtml = renderVoicePlayerMarkup(previewUrl, file.name, file.size, 'chat-message-voice--pending');
            }

            html += `
                <div class="chat-pending-attachment__item ${kind === 'audio' ? 'is-audio' : ''}">
                    <div class="chat-pending-attachment__preview ${kind === 'audio' ? 'chat-pending-attachment__preview--audio' : ''}">${previewHtml}</div>
                    <div class="min-w-0 flex-grow-1">
                        <div class="fw-medium text-truncate">${escapeHtml(file.name)}</div>
                        <div class="small text-body-secondary mt-1">${escapeHtml(formatBytes(file.size))}</div>
                    </div>
                </div>
            `;
        });

        state.pendingSiteAttachments.forEach((item) => {
            const kind = getSiteAttachmentKind(item.path);
            const previewUrl = buildSiteAttachmentUrl(item.path);

            let previewHtml = `<i class="${getAttachmentIcon(kind, kind)} fs-3 text-body-secondary"></i>`;
            if (kind === 'image') {
                previewHtml = `<img src="${escapeHtml(previewUrl)}" alt="${escapeHtml(item.name)}">`;
            } else if (kind === 'video') {
                previewHtml = `<video src="${escapeHtml(previewUrl)}" muted></video>`;
            } else if (kind === 'audio') {
                previewHtml = renderVoicePlayerMarkup(previewUrl, item.name, 0, 'chat-message-voice--pending');
            }

            html += `
                <div class="chat-pending-attachment__item ${kind === 'audio' ? 'is-audio' : ''}">
                    <div class="chat-pending-attachment__preview ${kind === 'audio' ? 'chat-pending-attachment__preview--audio' : ''}">${previewHtml}</div>
                    <div class="min-w-0 flex-grow-1">
                        <div class="fw-medium text-truncate">${escapeHtml(item.name)}</div>
                        <div class="small text-body-secondary mt-1">${escapeHtml(item.path)}</div>
                    </div>
                </div>
            `;
        });

        pendingList.html(html);
        initializeVoicePlayers(pendingList);
        const metaParts = [`${totalItems}`, `${chatApp.data('attachment-ready-text') || ''}`];
        if (totalSize > 0) {
            metaParts.splice(1, 0, formatBytes(totalSize));
        }
        pendingMeta.text(metaParts.filter(Boolean).join(' • '));
        pendingAttachment.removeClass('d-none');
        progressWrap.addClass('d-none');
        progressBar.css('width', '0%');
    };

    const validatePendingFile = (file) => {
        if (!file) {
            return false;
        }

        const extension = String((file.name || '').split('.').pop() || '').toLowerCase();
        if (file.size > maxFileSize) {
            showFlashAlert('error', chatApp.data('file-too-large-text') || 'File is too large.');
            return false;
        }

        const kind = getPendingFileKind(file);
        if (!isExtensionAllowed(extension, kind)) {
            showFlashAlert('error', chatApp.data('file-type-error-text') || 'Invalid file type.');
            return false;
        }

        return true;
    };

    const setPendingFiles = (files) => {
        const preparedFiles = Array.from(files || []).filter(Boolean).filter(validatePendingFile);
        if (!preparedFiles.length) {
            return;
        }

        state.pendingFiles = state.pendingFiles.concat(preparedFiles);
        renderPendingAttachment();
    };

    const formatRecordingTime = (milliseconds) => {
        const totalSeconds = Math.max(0, Math.floor(milliseconds / 1000));
        const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
        const seconds = String(totalSeconds % 60).padStart(2, '0');
        return `${minutes}:${seconds}`;
    };

    const stopVoiceTracks = () => {
        if (state.voiceStream) {
            state.voiceStream.getTracks().forEach((track) => track.stop());
        }
        state.voiceStream = null;
    };

    const resetVoiceRecorderUi = () => {
        if (state.voiceTimerId) {
            window.clearInterval(state.voiceTimerId);
        }
        state.voiceTimerId = null;
        state.voiceStartedAt = 0;
        voiceRecordingTimer.text('00:00');
        voiceRecorderPanel.addClass('d-none');
        const voiceSupported = Boolean(navigator.mediaDevices
            && typeof navigator.mediaDevices.getUserMedia === 'function'
            && typeof window.MediaRecorder !== 'undefined');
        recordVoiceButton.removeClass('is-recording').prop('disabled', !voiceSupported);
        sendButton.prop('disabled', false);
        stopVoiceTracks();
    };

    const finishVoiceRecording = () => {
        const recorder = state.voiceRecorder;
        const chunks = state.voiceChunks.slice();
        const shouldDiscard = state.discardVoiceRecording;
        const mimeType = String((recorder && recorder.mimeType) || (chunks[0] && chunks[0].type) || 'audio/webm');

        state.voiceRecorder = null;
        state.voiceChunks = [];
        state.discardVoiceRecording = false;
        resetVoiceRecorderUi();

        if (shouldDiscard || !chunks.length) {
            return;
        }

        const blob = new Blob(chunks, { type: mimeType });
        if (!blob.size) {
            return;
        }

        const extension = mimeType.includes('ogg') ? 'ogg' : (mimeType.includes('mp4') ? 'm4a' : 'webm');
        const stamp = new Date().toISOString().replace(/[:.]/g, '-');
        const voiceFile = new File([blob], `voice-${stamp}.${extension}`, {
            type: mimeType.startsWith('audio/') ? mimeType : `audio/${extension === 'm4a' ? 'mp4' : extension}`,
            lastModified: Date.now(),
        });

        setPendingFiles([voiceFile]);
    };

    const stopVoiceRecording = (keepRecording) => {
        state.voiceRequestId += 1;
        if (state.voiceTimerId) {
            window.clearInterval(state.voiceTimerId);
            state.voiceTimerId = null;
        }
        const recorder = state.voiceRecorder;
        if (!recorder) {
            resetVoiceRecorderUi();
            return;
        }

        state.discardVoiceRecording = !keepRecording;
        if (recorder.state !== 'inactive') {
            recorder.stop();
            return;
        }

        finishVoiceRecording();
    };

    const getSupportedVoiceMimeType = () => {
        if (typeof window.MediaRecorder === 'undefined') {
            return '';
        }

        const candidates = [
            'audio/webm;codecs=opus',
            'audio/ogg;codecs=opus',
            'audio/mp4',
            'audio/webm',
        ];

        return candidates.find((type) => (
            typeof window.MediaRecorder.isTypeSupported !== 'function'
            || window.MediaRecorder.isTypeSupported(type)
        )) || '';
    };

    const startVoiceRecording = async () => {
        if (state.voiceRecorder) {
            return;
        }

        if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function' || typeof window.MediaRecorder === 'undefined') {
            showFlashAlert('error', chatApp.data('voice-unsupported-text') || 'Voice recording is not supported.');
            return;
        }

        const requestId = ++state.voiceRequestId;
        const contactId = activeContactId();

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                },
                video: false,
            });
            if (requestId !== state.voiceRequestId || contactId !== activeContactId()) {
                stream.getTracks().forEach((track) => track.stop());
                return;
            }
            const mimeType = getSupportedVoiceMimeType();
            const options = mimeType ? { mimeType: mimeType, audioBitsPerSecond: 64000 } : { audioBitsPerSecond: 64000 };
            const recorder = new window.MediaRecorder(stream, options);

            state.voiceStream = stream;
            state.voiceRecorder = recorder;
            state.voiceChunks = [];
            state.discardVoiceRecording = false;
            recorder.addEventListener('dataavailable', (event) => {
                if (event.data && event.data.size > 0) {
                    state.voiceChunks.push(event.data);
                }
            });
            recorder.addEventListener('stop', finishVoiceRecording, { once: true });
            recorder.addEventListener('error', () => stopVoiceRecording(false), { once: true });
            recorder.start(250);

            state.voiceStartedAt = Date.now();
            voiceRecorderPanel.removeClass('d-none');
            recordVoiceButton.addClass('is-recording').prop('disabled', true);
            sendButton.prop('disabled', true);
            state.voiceTimerId = window.setInterval(() => {
                const elapsed = Date.now() - state.voiceStartedAt;
                voiceRecordingTimer.text(formatRecordingTime(elapsed));
                if (elapsed >= 5 * 60 * 1000) {
                    stopVoiceRecording(true);
                }
            }, 250);
        } catch (error) {
            stopVoiceTracks();
            resetVoiceRecorderUi();
            const permissionDenied = error && ['NotAllowedError', 'SecurityError'].includes(String(error.name || ''));
            showFlashAlert('error', permissionDenied
                ? (chatApp.data('voice-permission-error-text') || 'Microphone permission was denied.')
                : (chatApp.data('voice-unsupported-text') || 'Voice recording is not supported.'));
        }
    };

    const addPendingSiteAttachment = (value) => {
        const path = normalizeSiteAttachmentPath(value);
        if (path === '') {
            showFlashAlert('error', chatApp.data('file-type-error-text') || 'Invalid file type.');
            return;
        }

        const extension = String(path.split('.').pop() || '').toLowerCase();
        const kind = getSiteAttachmentKind(path);
        if (!isExtensionAllowed(extension, kind)) {
            showFlashAlert('error', chatApp.data('file-type-error-text') || 'Invalid file type.');
            return;
        }

        if (state.pendingSiteAttachments.some((item) => item.path === path)) {
            siteFileInput.val('');
            return;
        }

        state.pendingSiteAttachments.push({
            path: path,
            name: path.split('/').pop() || path,
        });
        siteFileInput.val('');
        renderPendingAttachment();
    };

    const applyContactFilter = () => {
        const query = String(contactSearchInput.val() || '').trim().toLowerCase();
        let visibleContacts = 0;

        contactGroups.each(function () {
            const group = $(this);
            let groupVisible = 0;

            group.find('[data-chat-contact]').each(function () {
                const button = $(this);
                const haystack = [
                    String(button.data('user-name') || ''),
                    String(button.data('last-message-preview') || ''),
                    String(button.data('user-role-label') || ''),
                    String(button.data('contact-group-label') || ''),
                ].join(' ').toLowerCase();
                const isVisible = query === '' || haystack.includes(query);

                button.toggleClass('d-none', !isVisible);
                if (isVisible) {
                    visibleContacts += 1;
                    groupVisible += 1;
                }
            });

            group.toggleClass('d-none', groupVisible === 0);
        });

        contactSearchEmpty.toggleClass('d-none', query === '' || visibleContacts > 0);
    };

    const updateContactUnreadBadges = (counts, totalUnread) => {
        counts = counts || {};

        getContactButtons().each(function () {
            const contact = $(this);
            const contactId = Number(contact.data('user-id'));
            const count = Number(counts[contactId] || 0);
            const badge = contact.find(`[data-chat-contact-unread="${contactId}"]`);

            badge.text(count);
            badge.toggleClass('d-none', count <= 0);
        });

        $(document).trigger('chat:unread-updated', [Number(totalUnread) || 0]);
    };

    const updateContactsPresence = (contactItems) => {
        if (!Array.isArray(contactItems)) {
            return;
        }

        contactItems.forEach((item) => {
            const contactId = Number(item.id) || 0;
            if (!contactId) {
                return;
            }

            const buttons = getContactButtons().filter(`[data-user-id="${contactId}"]`);
            if (!buttons.length) {
                return;
            }

            const isOnline = Number(item.is_online) === 1 || item.is_online === true;
            const groupKey = getGroupKey(item.chat_group || buttons.first().data('contact-group'));

            buttons.each(function () {
                const button = $(this);
                button.attr('data-contact-group', groupKey);
                button.data('user-online', isOnline ? 1 : 0);
                button.data('user-last-seen', String(item.last_seen_at || ''));
                button.data('last-message-preview', String(item.last_message_preview || ''));

                const status = button.find(`[data-chat-contact-status="${contactId}"]`);
                status
                    .toggleClass('text-success', isOnline)
                    .toggleClass('text-body-secondary', !isOnline)
                    .html(renderPresenceBadge(isOnline));

                button.find(`[data-chat-contact-preview="${contactId}"]`).text(getPreviewText(item.last_message_preview));
            });

            if (contactId === activeContactId()) {
                updateCurrentContactPresence(isOnline);
            }
        });

        contactGroupLists.each(function () {
            const list = $(this);
            const groupKey = getGroupKey(list.data('chat-contact-group-list'));
            contactItems.forEach((item) => {
                if (getGroupKey(item.chat_group) !== groupKey) {
                    return;
                }

                const localButton = list.find(`[data-user-id="${Number(item.id) || 0}"]`).first();
                if (localButton.length) {
                    list.append(localButton);
                }
            });
        });

        applyContactFilter();
    };

    const renderAttachment = (attachment, options = {}) => {
        if (!attachment || !attachment.url) {
            return '';
        }

        const url = escapeHtml(attachment.url);
        const name = escapeHtml(attachment.name || chatApp.data('attachment-label') || 'Attachment');
        const type = escapeHtml(attachment.type || '');
        const extension = escapeHtml(attachment.extension || '');
        const kind = escapeHtml(attachment.kind || 'file');
        const previewKind = escapeHtml(attachment.preview_kind || '');
        const size = attachment.size ? ` <span class="chat-message-meta-text">(${formatBytes(attachment.size)})</span>` : '';
        const icon = getAttachmentIcon(attachment.kind || 'file', attachment.preview_kind || '');
        const standaloneMedia = Boolean(options.standaloneMedia);

        if (attachment.is_image) {
            return `
                <div class="chat-message-media ${standaloneMedia ? 'chat-message-media--standalone' : 'mt-2'}">
                    <button
                        type="button"
                        class="btn p-0 border-0 bg-transparent chat-message-image-trigger"
                        data-chat-attachment-preview
                        data-preview-url="${url}"
                        data-preview-name="${name}"
                        data-preview-kind="${previewKind}"
                        data-preview-type="${type}"
                        data-preview-extension="${extension}"
                        style="cursor: zoom-in;"
                    >
                        <img class="chat-message-image" src="${url}" alt="${name}">
                    </button>
                </div>
            `;
        }

        if (attachment.kind === 'audio') {
            return renderVoicePlayerMarkup(
                attachment.url,
                attachment.name || chatApp.data('voice-message-text') || 'Voice message',
                attachment.size
            );
        }

        if (attachment.is_previewable) {
            return `
                <button
                    type="button"
                    class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 mt-2 text-start"
                    data-chat-attachment-preview
                    data-preview-url="${url}"
                    data-preview-name="${name}"
                    data-preview-kind="${previewKind}"
                    data-preview-type="${type}"
                    data-preview-extension="${extension}"
                >
                    <i class="${icon}"></i>
                    <span>${name}${size}</span>
                </button>
            `;
        }

        return `
            <a class="d-inline-flex align-items-center gap-2 mt-2 chat-message-attachment" href="${url}" target="_blank" rel="noopener noreferrer" download>
                <i class="${icon}"></i>
                <span>${name}${size}</span>
            </a>
        `;
    };

    const filterMessages = () => {
        const query = currentSearchQuery().toLowerCase();
        if (query === '') {
            return state.messages.slice();
        }

        return state.messages.filter((item) => {
            const attachmentName = item.attachment && item.attachment.name ? item.attachment.name : '';
            const haystack = [item.message || '', attachmentName].join(' ').toLowerCase();
            return haystack.includes(query);
        });
    };

    const syncSelectionControls = () => {
        const selectedCount = state.selectedIds.size;
        const label = String(chatApp.data('selection-count-text') || 'Selected: :count').replace(':count', selectedCount);

        chatApp.toggleClass('is-selection-mode', state.selectionMode);
        selectionCountBadge
            .toggleClass('d-none', !state.selectionMode)
            .attr('data-count', selectedCount)
            .text(label);
        selectionCancelButton.toggleClass('d-none', !state.selectionMode);
        deleteSelectedButton
            .toggleClass('d-none', !state.selectionMode)
            .prop('disabled', selectedCount === 0);
    };

    const renderMessages = (messages, currentUserId, options = {}) => {
        const box = messagesBox[0];
        const force = Boolean(options.force);
        const filteredMessages = filterMessages();
        const signature = `${activeContactId()}::${buildMessagesSignature(filteredMessages)}::${state.selectionMode ? 1 : 0}::${Array.from(state.selectedIds).sort().join(',')}::${currentSearchQuery()}`;
        const shouldStickToBottom = !box || Boolean(options.stickToBottom) || isNearBottom(box);
        const previousScrollTop = box ? box.scrollTop : 0;
        const query = currentSearchQuery();

        const resultText = query
            ? String(chatApp.data('message-search-results-text') || 'Found: :count').replace(':count', filteredMessages.length)
            : '';
        messageSearchResults.text(resultText).toggleClass('d-none', query === '');

        if (!force && signature === state.renderedSignature) {
            return;
        }

        if (!filteredMessages.length) {
            const emptyText = query
                ? (chatApp.data('message-search-empty-text') || chatApp.data('empty-text') || 'No messages found.')
                : (chatApp.data('empty-text') || 'No messages yet.');
            messagesBox.html(`
                <div class="chat-dialog-empty">
                    <span class="chat-dialog-empty__icon" aria-hidden="true"><i class="ci-chat"></i></span>
                    <p class="text-body-secondary mb-0">${escapeHtml(emptyText)}</p>
                </div>
            `);
            state.renderedSignature = signature;
            return;
        }

        let html = '';
        filteredMessages.forEach((item, index) => {
            const previousItem = filteredMessages[index - 1] || null;
            const nextItem = filteredMessages[index + 1] || null;
            const mine = Number(item.sender_id) === Number(currentUserId);
            const startsGroup = !canGroupMessages(previousItem, item);
            const endsGroup = !canGroupMessages(item, nextItem);
            const dayKey = getMessageDayKey(item.created_at);
            const previousDayKey = previousItem ? getMessageDayKey(previousItem.created_at) : '';
            const dayLabel = formatMessageDate(item.created_at);
            const readIcon = Number(item.is_read) === 1
                ? '<span class="chat-message-checks" aria-hidden="true"><i class="ci-check"></i><i class="ci-check"></i></span>'
                : '<span class="chat-message-checks" aria-hidden="true"><i class="ci-check"></i></span>';
            const checks = mine ? readIcon : '';
            const avatar = escapeHtml(getContactButtons().filter('.active').first().data('user-avatar') || currentAvatar.attr('src') || '');
            const messageValue = String(item.message || '').trim();
            const hasText = messageValue !== '';
            const mediaOnly = Boolean(item.attachment && item.attachment.is_image && !hasText);
            const messageText = hasText
                ? `<div class="chat-message-text">${highlightText(item.message, query)}</div>`
                : '';
            const attachment = renderAttachment(item.attachment, { standaloneMedia: mediaOnly });
            const isSelected = state.selectedIds.has(Number(item.id));
            const canShowActions = state.canModerate && !state.selectionMode;
            const canShowCheckbox = state.selectionMode && state.canBulkDelete;
            const actionDeleteText = escapeHtml(chatApp.data('action-delete-text') || 'Delete');
            const timestamp = escapeHtml(formatMessageTime(item.created_at));
            const dateTime = escapeHtml(String(item.created_at || '').replace(' ', 'T'));
            const avatarHtml = mine
                ? ''
                : (endsGroup
                    ? `<img src="${avatar}" alt="" class="chat-message-avatar rounded-circle border flex-shrink-0">`
                    : '<span class="chat-message-avatar-spacer" aria-hidden="true"></span>');

            if (dayKey !== previousDayKey && dayLabel !== '') {
                html += `<div class="chat-message-day"><span>${escapeHtml(dayLabel)}</span></div>`;
            }

            html += `
                <div class="chat-message-row ${mine ? 'chat-message-row--mine' : 'chat-message-row--theirs'} ${startsGroup ? 'is-group-start' : ''} ${endsGroup ? 'is-group-end' : ''} ${isSelected ? 'is-selected' : ''}" data-chat-message-row data-message-id="${Number(item.id) || 0}">
                    ${avatarHtml}
                    ${canShowCheckbox ? `<input type="checkbox" class="form-check-input chat-message-select" data-chat-message-select value="${Number(item.id) || 0}" ${isSelected ? 'checked' : ''}>` : ''}
                    <div class="chat-message-stack ${canShowActions ? 'has-actions' : ''}">
                        <div class="chat-message-bubble ${mine ? 'chat-message-bubble--mine' : 'chat-message-bubble--theirs'} ${mediaOnly ? 'chat-message-bubble--media-only' : ''}">
                            ${messageText}
                            ${attachment}
                            <div class="chat-message-meta-text">
                                <time datetime="${dateTime}" title="${escapeHtml(item.created_at || '')}">${timestamp}</time>
                                ${checks}
                            </div>
                        </div>
                        ${canShowActions ? `
                            <div class="chat-message-actions">
                                <button type="button" class="chat-message-delete-btn" data-chat-delete-message="${Number(item.id) || 0}" title="${actionDeleteText}" aria-label="${actionDeleteText}">
                                    <i class="ci-trash" aria-hidden="true"></i>
                                </button>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        });

        messagesBox.html(html);
        initializeVoicePlayers();
        state.renderedSignature = signature;

        if (!box) {
            return;
        }

        if (shouldStickToBottom) {
            messagesBox.scrollTop(box.scrollHeight);
            return;
        }

        messagesBox.scrollTop(previousScrollTop);
    };

    const setPreviewModalFallback = (text) => {
        previewModalBody.html(`
            <div class="text-center text-body-secondary py-5">
                <i class="ci-file fs-1 d-block mb-3"></i>
                <p class="mb-0">${escapeHtml(text)}</p>
            </div>
        `);
    };

    const renderPreviewContent = (attachment) => {
        const previewKind = String(attachment.kind || '');
        const url = String(attachment.url || '');
        const name = String(attachment.name || '');
        const requestId = ++state.previewRequestId;

        previewModalTitle.text(name || chatApp.data('preview-text') || 'Preview');
        previewModalOpen.attr('href', url);
        previewModalDownload.attr('href', url).attr('download', name || 'file');
        previewModalBody.html(`<p class="text-body-secondary mb-0">${escapeHtml(previewLoadingText)}</p>`);

        if (previewKind === 'image') {
            previewModalBody.html(`
                <img src="${escapeHtml(url)}" alt="${escapeHtml(name)}" class="chat-preview-modal__image">
            `);
            return;
        }

        if (previewKind === 'audio') {
            previewModalBody.html(`<audio class="chat-preview-modal__audio" controls preload="metadata" src="${escapeHtml(url)}"></audio>`);
            return;
        }

        if (previewKind === 'video') {
            previewModalBody.html(`<video class="chat-preview-modal__video" controls preload="metadata" src="${escapeHtml(url)}"></video>`);
            return;
        }

        if (previewKind === 'pdf') {
            previewModalBody.html(`<iframe class="chat-preview-modal__frame" src="${escapeHtml(url)}"></iframe>`);
            return;
        }

        if (previewKind === 'text') {
            fetch(url)
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.text();
                })
                .then((text) => {
                    if (requestId !== state.previewRequestId) {
                        return;
                    }

                    previewModalBody.html(`
                        <pre class="chat-preview-modal__text">${escapeHtml(text)}</pre>
                    `);
                })
                .catch(() => {
                    if (requestId !== state.previewRequestId) {
                        return;
                    }

                    setPreviewModalFallback(previewUnavailableText);
                });
            return;
        }

        setPreviewModalFallback(previewUnavailableText);
    };

    const openAttachmentPreview = (trigger) => {
        if (!previewModal) {
            return;
        }

        previewModal.show();
        renderPreviewContent({
            url: String(trigger.data('preview-url') || ''),
            name: String(trigger.data('preview-name') || ''),
            kind: String(trigger.data('preview-kind') || ''),
            type: String(trigger.data('preview-type') || ''),
            extension: String(trigger.data('preview-extension') || '')
        });
    };

    const applyPayload = (response, options = {}) => {
        if (!response || !response.status) {
            return;
        }

        state.messagesLoadErrorShown = false;
        state.messages = Array.isArray(response.messages) ? response.messages : [];
        if (response.permissions) {
            state.canModerate = Boolean(response.permissions.can_moderate);
            state.canBulkDelete = Boolean(response.permissions.can_bulk_delete);
            state.canClearChat = Boolean(response.permissions.can_clear_chat);
            state.canViewAudit = Boolean(response.permissions.can_view_audit);
            state.canDeleteAudit = Boolean(response.permissions.can_delete_audit);
        }

        renderMessages(state.messages, response.current_user_id || 0, options);
        updateContactUnreadBadges(response.contact_unread_counts || {}, response.unread_count || 0);
        updateContactsPresence(response.contacts || []);
        window.__chatAppState = {
            activeContactId: activeContactId(),
            currentUserId: Number(response.current_user_id) || 0,
        };

        if (response.contact) {
            updateCurrentContactPresence(Boolean(response.contact.is_online));
        }
    };

    const loadMessages = (options = {}) => {
        const contactId = activeContactId();
        if (!contactId) {
            return;
        }

        const requestId = ++state.messagesRequestId;

        $.ajax({
            url: fetchUrl,
            method: 'GET',
            dataType: 'json',
            data: { user_id: contactId },
            success: function (response) {
                if (requestId !== state.messagesRequestId || contactId !== activeContactId()) {
                    return;
                }
                applyPayload(response, options);
            },
            error: function (request) {
                if (requestId !== state.messagesRequestId || contactId !== activeContactId()) {
                    return;
                }

                const message = request.responseJSON && request.responseJSON.message
                    ? request.responseJSON.message
                    : (chatApp.data('load-error-text') || 'Could not load messages. Please try again.');

                if (!state.messagesLoadErrorShown) {
                    showFlashAlert('error', message);
                    state.messagesLoadErrorShown = true;
                }

                if (!state.messages.length) {
                    messagesBox.html(`
                        <div class="chat-dialog-empty">
                            <span class="chat-dialog-empty__icon text-danger" aria-hidden="true"><i class="ci-alert-circle"></i></span>
                            <p class="text-body-secondary mb-0">${escapeHtml(message)}</p>
                        </div>
                    `);
                }
            }
        });
    };

    const setActiveContact = (button) => {
        const contactId = Number(button.data('user-id')) || 0;
        getContactButtons().removeClass('active');
        getContactButtons().filter(`[data-user-id="${contactId}"]`).addClass('active');

        userIdInput.val(contactId);
        currentName.html(escapeHtml(button.data('user-name')) + renderVerifiedBadge(button.data('user-role')));
        currentRole.text(String(button.data('user-role-label') || ''));
        initializeCurrentNameTooltip();
        currentAvatar.attr('src', button.data('user-avatar')).attr('alt', button.data('user-name'));
        updateCurrentContactPresence(Number(button.data('user-online')) === 1);
        state.messagesRequestId += 1;
        state.auditRequestId += 1;
        state.renderedSignature = '';
        state.selectionMode = false;
        state.selectedIds.clear();
        syncSelectionControls();
        messageInput.val('');
        resizeMessageInput();
        messageSearchInput.val('');
        stopVoiceRecording(false);
        clearPendingAttachment();
        window.__chatAppState = { activeContactId: activeContactId() };
        $(document).trigger('chat:active-contact-changed', [activeContactId()]);

        if (offcanvas) {
            offcanvas.hide();
        }

        loadMessages({ force: true, stickToBottom: true });
    };

    const sendMessage = () => {
        if (sendButton.prop('disabled')) {
            return;
        }

        const contactId = activeContactId();
        const text = String(messageInput.val() || '').trim();
        if (text === '' && !state.pendingFiles.length && !state.pendingSiteAttachments.length) {
            showFlashAlert('error', chatApp.data('message-required-text') || 'Message is required.');
            return;
        }

        const payload = new FormData(form[0]);
        payload.delete('attachment[]');
        payload.delete('attachment');
        payload.delete('site_attachment_paths[]');
        payload.delete('site_attachment_paths');
        state.pendingFiles.forEach((file) => {
            payload.append('attachment[]', file, file.name);
        });
        state.pendingSiteAttachments.forEach((item) => {
            payload.append('site_attachment_paths[]', item.path);
        });

        $.ajax({
            url: sendUrl,
            method: 'POST',
            dataType: 'json',
            data: payload,
            processData: false,
            contentType: false,
            xhr: function () {
                const xhr = $.ajaxSettings.xhr();
                if (xhr.upload) {
                    xhr.upload.addEventListener('progress', function (event) {
                        if (!event.lengthComputable || !state.pendingFiles.length) {
                            return;
                        }

                        const progress = Math.max(0, Math.min(100, Math.round((event.loaded / event.total) * 100)));
                        progressWrap.removeClass('d-none');
                        progressBar.css('width', `${progress}%`);
                    });
                }

                return xhr;
            },
            beforeSend: function () {
                sendButton.prop('disabled', true);
                progressWrap.toggleClass('d-none', !state.pendingFiles.length);
            },
            success: function (response) {
                if (!response.status) {
                    showFlashAlert('error', response.message || 'Message could not be sent.');
                    return;
                }

                if (contactId !== activeContactId()) {
                    loadMessages({ force: true });
                    return;
                }

                messageInput.val('');
                resizeMessageInput();
                clearPendingAttachment();
                state.messagesRequestId += 1;
                applyPayload(response, { force: true, stickToBottom: true });
            },
            error: function (request) {
                const message = request.responseJSON && request.responseJSON.message
                    ? request.responseJSON.message
                    : 'Message send error.';
                showFlashAlert('error', message);
            },
            complete: function () {
                sendButton.prop('disabled', false);
                progressWrap.addClass('d-none');
                progressBar.css('width', '0%');
            }
        });
    };

    const runDeleteMessages = (messageIds, reason = '') => {
        if (!messageIds.length || state.moderationRequestPending) {
            return;
        }

        const contactId = activeContactId();

        $.ajax({
            url: deleteUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                needCSRFToken: form.find('input[name="needCSRFToken"]').val(),
                user_id: contactId,
                message_ids: messageIds,
                reason: String(reason || '').trim(),
            },
            beforeSend: function () {
                state.moderationRequestPending = true;
                confirmModalSubmit.prop('disabled', true);
            },
            success: function (response) {
                if (!response.status) {
                    showFlashAlert('error', response.message || '');
                    return;
                }

                if (contactId === activeContactId()) {
                    state.selectedIds.clear();
                    state.messagesRequestId += 1;
                    applyPayload(response, { force: true });
                    syncSelectionControls();
                } else {
                    loadMessages({ force: true });
                }
            },
            error: function (request) {
                showFlashAlert('error', request.responseJSON && request.responseJSON.message ? request.responseJSON.message : '');
            },
            complete: function () {
                state.moderationRequestPending = false;
                confirmModalSubmit.prop('disabled', false);
            },
        });
    };

    const runClearConversation = (reason = '') => {
        if (state.moderationRequestPending) {
            return;
        }

        const contactId = activeContactId();

        $.ajax({
            url: clearUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                needCSRFToken: form.find('input[name="needCSRFToken"]').val(),
                user_id: contactId,
                reason: String(reason || '').trim(),
            },
            beforeSend: function () {
                state.moderationRequestPending = true;
                confirmModalSubmit.prop('disabled', true);
            },
            success: function (response) {
                if (!response.status) {
                    showFlashAlert('error', response.message || '');
                    return;
                }

                if (contactId === activeContactId()) {
                    state.selectedIds.clear();
                    state.selectionMode = false;
                    syncSelectionControls();
                    state.messagesRequestId += 1;
                    applyPayload(response, { force: true });
                } else {
                    loadMessages({ force: true });
                }
            },
            error: function (request) {
                showFlashAlert('error', request.responseJSON && request.responseJSON.message ? request.responseJSON.message : '');
            },
            complete: function () {
                state.moderationRequestPending = false;
                confirmModalSubmit.prop('disabled', false);
            },
        });
    };

    const runClearAudit = () => {
        if (!state.canDeleteAudit || !auditClearUrl || state.moderationRequestPending) {
            return;
        }

        const contactId = activeContactId();

        $.ajax({
            url: auditClearUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                needCSRFToken: form.find('input[name="needCSRFToken"]').val(),
                user_id: contactId,
            },
            beforeSend: function () {
                state.moderationRequestPending = true;
                confirmModalSubmit.prop('disabled', true);
                auditClearButton.prop('disabled', true);
            },
            success: function (response) {
                if (!response.status) {
                    showFlashAlert('error', response.message || chatApp.data('audit-clear-error-text') || '');
                    return;
                }

                state.auditRequestId += 1;
                if (contactId === activeContactId()) {
                    renderAuditItems([]);
                }
            },
            error: function (request) {
                const message = request.responseJSON && request.responseJSON.message
                    ? request.responseJSON.message
                    : (chatApp.data('audit-clear-error-text') || '');
                showFlashAlert('error', message);
            },
            complete: function () {
                state.moderationRequestPending = false;
                confirmModalSubmit.prop('disabled', false);
                auditClearButton.prop('disabled', false);
            },
        });
    };

    const showConfirm = (message, callback, options = {}) => {
        const collectReason = Boolean(options.collectReason);
        if (!confirmModal) {
            callback('');
            return;
        }

        state.confirmAction = callback;
        confirmModalMessage.text(message);
        confirmModalReasonWrap.toggleClass('d-none', !collectReason);
        confirmModalReason
            .val('')
            .attr('placeholder', chatApp.data('confirm-reason-placeholder') || '')
            .attr('aria-label', chatApp.data('confirm-reason-label') || '');
        confirmModal.show();
    };

    const renderAuditItems = (items) => {
        if (!Array.isArray(items) || !items.length) {
            auditList.html(`<p class="text-body-secondary mb-0">${escapeHtml(chatApp.data('audit-empty-text') || '')}</p>`);
            return;
        }

        const actionLabels = {
            delete_message: String(chatApp.data('audit-delete-text') || ''),
            bulk_delete: String(chatApp.data('audit-bulk-delete-text') || ''),
            clear_conversation: String(chatApp.data('audit-clear-text') || ''),
        };

        let html = '<div class="d-flex flex-column gap-3">';
        items.forEach((item) => {
            const details = item.details || {};
            const actionLabel = actionLabels[item.action] || item.action;
            const metaRows = [];

            if (details.reason) {
                metaRows.push(`<div><span class="text-body-secondary">${escapeHtml(chatApp.data('audit-reason-text') || '')}:</span> ${escapeHtml(details.reason)}</div>`);
            }
            if (details.attachment && details.attachment.name) {
                metaRows.push(`<div><span class="text-body-secondary">${escapeHtml(chatApp.data('audit-attachment-text') || '')}:</span> ${escapeHtml(details.attachment.name)}</div>`);
            }
            if (Object.prototype.hasOwnProperty.call(details, 'deleted_count')) {
                metaRows.push(`<div><span class="text-body-secondary">${escapeHtml(chatApp.data('audit-deleted-count-text') || '')}:</span> ${Number(details.deleted_count) || 0}</div>`);
            }
            if (Object.prototype.hasOwnProperty.call(details, 'attachment_count')) {
                metaRows.push(`<div><span class="text-body-secondary">${escapeHtml(chatApp.data('audit-attachment-count-text') || '')}:</span> ${Number(details.attachment_count) || 0}</div>`);
            }
            if (item.ip_address) {
                metaRows.push(`<div><span class="text-body-secondary">${escapeHtml(chatApp.data('audit-ip-text') || '')}:</span> ${escapeHtml(item.ip_address)}</div>`);
            }
            if (item.user_agent) {
                metaRows.push(`<div><span class="text-body-secondary">${escapeHtml(chatApp.data('audit-device-text') || '')}:</span> ${escapeHtml(item.user_agent)}</div>`);
            }

            const isClearAction = item.action === 'clear_conversation';
            html += `
                <div class="chat-audit-entry ${isClearAction ? 'chat-audit-entry--clear' : ''} border rounded-4 p-3">
                    <div class="d-flex align-items-start justify-content-between gap-3 mb-2">
                        <div class="d-flex align-items-start gap-2 min-w-0">
                            <span class="chat-audit-entry__icon" aria-hidden="true"><i class="${isClearAction ? 'ci-trash' : 'ci-file-text'}"></i></span>
                            <div class="min-w-0">
                                <div class="fw-semibold">${escapeHtml(actionLabel)}</div>
                                <div class="small text-body-secondary">${escapeHtml(item.actor_name || '')} • ${escapeHtml(item.created_at || '')}</div>
                            </div>
                        </div>
                        <span class="badge rounded-pill text-body-emphasis bg-body-tertiary">${escapeHtml(item.actor_role || '')}</span>
                    </div>
                    ${metaRows.length ? `<div class="small d-flex flex-column gap-1">${metaRows.join('')}</div>` : ''}
                </div>
            `;
        });
        html += '</div>';

        auditList.html(html);
    };

    const openAudit = () => {
        if (!auditModal) {
            return;
        }

        const contactId = activeContactId();
        const requestId = ++state.auditRequestId;
        auditModal.show();
        auditList.html(`<p class="text-body-secondary mb-0">${escapeHtml(chatApp.data('preview-loading-text') || '')}</p>`);

        $.ajax({
            url: auditUrl,
            method: 'GET',
            dataType: 'json',
            data: { user_id: contactId },
            success: function (response) {
                if (requestId !== state.auditRequestId || contactId !== activeContactId()) {
                    return;
                }
                if (!response.status) {
                    showFlashAlert('error', response.message || '');
                    auditList.html(`<p class="text-danger mb-0">${escapeHtml(response.message || chatApp.data('audit-load-error-text') || '')}</p>`);
                    return;
                }

                renderAuditItems(response.items || []);
            },
            error: function (request) {
                const message = request.responseJSON && request.responseJSON.message
                    ? request.responseJSON.message
                    : (chatApp.data('audit-load-error-text') || '');
                showFlashAlert('error', message);
                auditList.html(`<p class="text-danger mb-0">${escapeHtml(message)}</p>`);
            }
        });
    };

    chatApp.on('click', '[data-chat-contact]', function () {
        setActiveContact($(this));
    });

    chatApp.on('click', '[data-chat-delete-message]', function () {
        const messageId = Number($(this).data('chat-delete-message')) || 0;
        if (!messageId) {
            return;
        }

        showConfirm(chatApp.data('confirm-delete-message-text') || '', function (reason) {
            runDeleteMessages([messageId], reason);
        }, { collectReason: true });
    });

    chatApp.on('change', '[data-chat-message-select]', function () {
        const messageId = Number($(this).val()) || 0;
        if (!messageId) {
            return;
        }

        if (this.checked) {
            state.selectedIds.add(messageId);
        } else {
            state.selectedIds.delete(messageId);
        }

        state.renderedSignature = '';
        syncSelectionControls();
        renderMessages(state.messages, Number(window.__chatAppState && window.__chatAppState.currentUserId || 0), { force: true });
    });

    chatApp.on('click', '[data-chat-attachment-preview]', function () {
        openAttachmentPreview($(this));
    });

    chatApp.find('[data-chat-pick-camera]').on('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (this.disabled || !cameraInput.length) {
            return;
        }

        openNativeFilePicker(cameraInput[0]);
    });

    chatApp.find('[data-chat-pick-gallery]').on('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (this.disabled || !galleryInput.length) {
            return;
        }

        openNativeFilePicker(galleryInput[0]);
    });

    attachmentInput.on('change', function () {
        setPendingFiles(this.files || []);
    });

    siteFileInput.on('change', function () {
        addPendingSiteAttachment($(this).val());
    });

    cameraInput.on('change', function () {
        setPendingFiles(this.files || []);
    });

    galleryInput.on('change', function () {
        setPendingFiles(this.files || []);
    });

    recordVoiceButton.on('click', function () {
        startVoiceRecording();
    });

    voiceStopButton.on('click', function () {
        stopVoiceRecording(true);
    });

    voiceCancelButton.on('click', function () {
        stopVoiceRecording(false);
    });

    form.find('[data-chat-remove-attachment]').on('click', function () {
        clearPendingAttachment();
    });

    dropzone.on('dragenter dragover', function (event) {
        event.preventDefault();
        event.stopPropagation();
        state.dragDepth += 1;
        dropzoneOverlay.removeClass('d-none');
    });

    dropzone.on('dragleave', function (event) {
        event.preventDefault();
        event.stopPropagation();
        state.dragDepth = Math.max(0, state.dragDepth - 1);
        if (state.dragDepth === 0) {
            dropzoneOverlay.addClass('d-none');
        }
    });

    dropzone.on('drop', function (event) {
        event.preventDefault();
        event.stopPropagation();
        state.dragDepth = 0;
        dropzoneOverlay.addClass('d-none');

        const files = event.originalEvent && event.originalEvent.dataTransfer
            ? event.originalEvent.dataTransfer.files
            : null;
        if (files && files.length) {
            setPendingFiles(files);
        }
    });

    form.on('submit', function (event) {
        event.preventDefault();
        sendMessage();
    });

    messageInput.on('input', function () {
        resizeMessageInput();
    });

    window.addEventListener('resize', resizeMessageInput);

    messageInput.on('keydown', function (event) {
        const originalEvent = event.originalEvent || event;
        if (event.key !== 'Enter' || event.shiftKey || originalEvent.isComposing) {
            return;
        }

        event.preventDefault();
        sendMessage();
    });

    chatApp.on('click', '[data-chat-voice-toggle]', function () {
        const player = $(this).closest('[data-chat-voice-player]');
        const audio = player.find('[data-chat-voice-audio]')[0];
        if (!audio) {
            return;
        }

        if (audio.paused) {
            const playRequest = audio.play();
            if (playRequest && typeof playRequest.catch === 'function') {
                playRequest.catch(() => setVoicePlayerState(player, false));
            }
            return;
        }

        audio.pause();
    });

    chatApp.on('input change', '[data-chat-voice-progress]', function () {
        const player = $(this).closest('[data-chat-voice-player]');
        const audio = player.find('[data-chat-voice-audio]')[0];
        if (!audio || !Number.isFinite(audio.duration) || audio.duration <= 0) {
            return;
        }

        const progressValue = Math.max(0, Math.min(100, Number($(this).val()) || 0));
        audio.currentTime = (progressValue / 100) * audio.duration;
        syncVoicePlayer(player[0]);
    });

    contactSearchInput.on('input', function () {
        const value = $(this).val();
        contactSearchInput.val(value);
        applyContactFilter();
    });

    messageSearchInput.on('input', function () {
        state.renderedSignature = '';
        renderMessages(state.messages, Number(window.__chatAppState && window.__chatAppState.currentUserId || 0), { force: true });
    });

    previewModalElement.on('hidden.bs.modal', function () {
        state.previewRequestId += 1;
        previewModalTitle.text(chatApp.data('preview-text') || 'Preview');
        previewModalBody.html(`<p class="text-body-secondary mb-0">${escapeHtml(previewLoadingText)}</p>`);
        previewModalOpen.attr('href', '');
        previewModalDownload.attr('href', '').removeAttr('download');
    });

    confirmModalSubmit.on('click', function () {
        if (typeof state.confirmAction === 'function') {
            const action = state.confirmAction;
            const reason = String(confirmModalReason.val() || '').trim();
            state.confirmAction = null;
            confirmModal.hide();
            action(reason);
        }
    });

    confirmModalElement.on('hidden.bs.modal', function () {
        state.confirmAction = null;
        confirmModalReason.val('');
        confirmModalReasonWrap.addClass('d-none');
    });

    selectionToggleButton.on('click', function () {
        state.selectionMode = true;
        state.selectedIds.clear();
        state.renderedSignature = '';
        syncSelectionControls();
        renderMessages(state.messages, Number(window.__chatAppState && window.__chatAppState.currentUserId || 0), { force: true });
    });

    selectionCancelButton.on('click', function () {
        state.selectionMode = false;
        state.selectedIds.clear();
        state.renderedSignature = '';
        syncSelectionControls();
        renderMessages(state.messages, Number(window.__chatAppState && window.__chatAppState.currentUserId || 0), { force: true });
    });

    deleteSelectedButton.on('click', function () {
        const messageIds = Array.from(state.selectedIds);
        if (!messageIds.length) {
            return;
        }

        showConfirm(chatApp.data('confirm-delete-messages-text') || '', function (reason) {
            runDeleteMessages(messageIds, reason);
        }, { collectReason: true });
    });

    chatApp.find('[data-chat-clear-conversation]').on('click', function () {
        showConfirm(chatApp.data('confirm-clear-chat-text') || '', function (reason) {
            runClearConversation(reason);
        }, { collectReason: true });
    });

    chatApp.find('[data-chat-open-audit]').on('click', function () {
        openAudit();
    });

    auditClearButton.on('click', function () {
        const openConfirmation = () => {
            showConfirm(chatApp.data('confirm-clear-audit-text') || '', function () {
                runClearAudit();
            });
        };

        if (auditModal && auditModalElement.hasClass('show')) {
            auditModalElement.one('hidden.bs.modal', openConfirmation);
            auditModal.hide();
            return;
        }

        openConfirmation();
    });

    window.__chatAppState = { activeContactId: activeContactId(), currentUserId: 0 };
    $(document).trigger('chat:active-contact-changed', [activeContactId()]);

    loadMessages({ stickToBottom: true });
    applyContactFilter();
    syncSelectionControls();
    resizeMessageInput();
    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function' || typeof window.MediaRecorder === 'undefined') {
        recordVoiceButton.prop('disabled', true).attr('title', chatApp.data('voice-unsupported-text') || 'Voice recording is not supported.');
    }
    window.addEventListener('pagehide', function () {
        stopVoiceRecording(false);
    });
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            loadMessages();
        }
    });
    setInterval(function () {
        if (document.visibilityState === 'visible') {
            loadMessages();
        }
    }, 4000);
});
