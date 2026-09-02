(() => {
    'use strict';

    const root = document.querySelector('[data-calendar-app]');
    if (!root) return;

    const configNode = root.querySelector('[data-calendar-config]');
    let config = {};
    try {
        config = JSON.parse(configNode?.textContent || '{}');
    } catch (error) {
        return;
    }

    const labels = config.labels || {};
    const locale = String(config.locale || document.documentElement.lang || 'ru').replace('_', '-');
    const canManage = Boolean(config.canManage);
    const stage = root.querySelector('[data-calendar-stage]');
    const loading = root.querySelector('[data-calendar-loading]');
    const period = root.querySelector('[data-calendar-period]');
    const upcoming = root.querySelector('[data-calendar-upcoming]');
    const upcomingCount = root.querySelector('[data-calendar-upcoming-count]');
    const form = root.querySelector('[data-calendar-form]');
    const modalElement = document.getElementById('calendarEventModal');
    const modalTitle = root.querySelector('[data-calendar-modal-title]');
    const moreActions = root.querySelector('[data-calendar-more-actions]');
    const saveButton = root.querySelector('[data-calendar-save]');
    const reminderList = root.querySelector('[data-calendar-reminders]');
    const reminderTemplate = root.querySelector('[data-calendar-reminder-template]');
    const query = new URLSearchParams(window.location.search);
    const requestedOccurrence = query.get('occurrence');
    const requestedEventId = Number(query.get('event') || 0);
    let requestedEventOpened = false;

    const state = {
        view: readView(),
        current: requestedOccurrence ? parseDateTime(requestedOccurrence) : new Date(),
        events: [],
        range: null,
    };
    if (Number.isNaN(state.current.getTime())) state.current = new Date();

    function readView() {
        try {
            const saved = window.localStorage.getItem('fireball.calendar.view');
            if (['month', 'week', 'day', 'list'].includes(saved)) return saved;
        } catch (error) {}
        return window.innerWidth < 768 ? 'list' : 'month';
    }

    function saveView(view) {
        try { window.localStorage.setItem('fireball.calendar.view', view); } catch (error) {}
    }

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function dateKey(date) {
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    }

    function timeKey(date) {
        return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }

    function toQueryDate(date) {
        return `${dateKey(date)}T${timeKey(date)}:${pad(date.getSeconds())}`;
    }

    function parseDateTime(value) {
        if (value instanceof Date) return new Date(value.getTime());
        return new Date(String(value || '').replace(' ', 'T'));
    }

    function addDays(date, amount) {
        const next = new Date(date.getTime());
        next.setDate(next.getDate() + amount);
        return next;
    }

    function startOfDay(date) {
        return new Date(date.getFullYear(), date.getMonth(), date.getDate());
    }

    function startOfWeek(date) {
        const start = startOfDay(date);
        const offset = (start.getDay() + 6) % 7;
        start.setDate(start.getDate() - offset);
        return start;
    }

    function sameDay(a, b) {
        return dateKey(a) === dateKey(b);
    }

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = String(value ?? '');
        return element.innerHTML;
    }

    function label(key, replacements = {}) {
        let value = String(labels[key] || key);
        Object.entries(replacements).forEach(([name, replacement]) => {
            value = value.replace(`:${name}`, String(replacement));
        });
        return value;
    }

    function eventModal() {
        if (!modalElement || !window.bootstrap?.Modal) return null;
        return window.bootstrap.Modal.getOrCreateInstance(modalElement);
    }

    function pickerLocale() {
        const language = locale.toLowerCase().split('-')[0];
        const locales = {
            ru: {
                firstDayOfWeek: 1,
                weekdays: {
                    shorthand: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
                    longhand: ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'],
                },
                months: {
                    shorthand: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'],
                    longhand: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
                },
                rangeSeparator: ' — ',
                weekAbbreviation: 'Нед.',
                scrollTitle: 'Прокрутите для изменения',
                toggleTitle: 'Нажмите для переключения',
                time_24hr: true,
            },
            de: {
                firstDayOfWeek: 1,
                weekdays: {
                    shorthand: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
                    longhand: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
                },
                months: {
                    shorthand: ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'],
                    longhand: ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
                },
                rangeSeparator: ' bis ',
                weekAbbreviation: 'KW',
                time_24hr: true,
            },
            zh: {
                firstDayOfWeek: 1,
                weekdays: {
                    shorthand: ['日', '一', '二', '三', '四', '五', '六'],
                    longhand: ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'],
                },
                months: {
                    shorthand: ['一月', '二月', '三月', '四月', '五月', '六月', '七月', '八月', '九月', '十月', '十一月', '十二月'],
                    longhand: ['一月', '二月', '三月', '四月', '五月', '六月', '七月', '八月', '九月', '十月', '十一月', '十二月'],
                },
                rangeSeparator: ' 至 ',
                time_24hr: true,
            },
        };

        return locales[language] || { firstDayOfWeek: 1, time_24hr: true };
    }

    function pickerDisplayFormat() {
        const language = locale.toLowerCase().split('-')[0];
        if (language === 'zh') return 'Y年m月d日';
        if (language === 'en') return 'F j, Y';
        return 'd.m.Y';
    }

    function connectPickerLabel(input, picker) {
        if (!input.id || !picker.altInput) return;
        const labelElement = form.querySelector(`label[for="${input.id}"]`);
        picker.altInput.id = `${input.id}-display`;
        picker.altInput.setAttribute('aria-label', labelElement?.textContent?.trim() || input.name);
        if (labelElement) labelElement.htmlFor = picker.altInput.id;
    }

    function initDateTimePickers() {
        if (typeof window.flatpickr !== 'function') return;
        const common = {
            allowInput: true,
            disableMobile: true,
            locale: pickerLocale(),
        };
        form.querySelectorAll('[data-calendar-date-picker]').forEach((input) => {
            const picker = window.flatpickr(input, {
                ...common,
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: pickerDisplayFormat(),
                altInputClass: 'form-control form-icon-end',
            });
            connectPickerLabel(input, picker);
        });
        form.querySelectorAll('[data-calendar-time-picker]').forEach((input) => {
            window.flatpickr(input, {
                ...common,
                enableTime: true,
                noCalendar: true,
                dateFormat: 'H:i',
                time_24hr: true,
                minuteIncrement: 5,
            });
        });
    }

    function setPickerValue(name, value) {
        const input = form.elements[name];
        if (!input) return;
        if (input._flatpickr) {
            input._flatpickr.setDate(value || null, false, input._flatpickr.config.dateFormat);
            return;
        }
        input.value = value;
    }

    function eventColor(event) {
        return /^#[0-9a-f]{6}$/i.test(String(event.color || '')) ? event.color : '#6f5ef9';
    }

    function eventByOccurrence(id) {
        return state.events.find((event) => String(event.occurrence_id) === String(id));
    }

    function eventsForDay(date) {
        const dayStart = startOfDay(date);
        const dayEnd = addDays(dayStart, 1);
        return state.events.filter((event) => {
            const eventStart = parseDateTime(event.starts_at);
            const eventEnd = parseDateTime(event.ends_at);
            return eventStart < dayEnd && eventEnd > dayStart;
        });
    }

    function rangeForView() {
        if (state.view === 'month') {
            const first = new Date(state.current.getFullYear(), state.current.getMonth(), 1);
            const start = startOfWeek(first);
            return { start, end: addDays(start, 42) };
        }
        if (state.view === 'week') {
            const start = startOfWeek(state.current);
            return { start, end: addDays(start, 7) };
        }
        if (state.view === 'day') {
            const start = startOfDay(state.current);
            return { start, end: addDays(start, 1) };
        }
        const start = startOfDay(state.current);
        return { start, end: addDays(start, 30) };
    }

    async function loadEvents() {
        const range = rangeForView();
        state.range = range;
        loading.hidden = false;
        updateToolbar();
        try {
            const url = new URL(config.eventsUrl, window.location.origin);
            url.searchParams.set('start', toQueryDate(range.start));
            url.searchParams.set('end', toQueryDate(range.end));
            const response = await fetch(url.toString(), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            if (!response.ok || !data.status) throw new Error(data.message || label('error_load'));
            state.events = Array.isArray(data.items) ? data.items : [];
            render();
            renderUpcoming();
            openRequestedEvent();
        } catch (error) {
            renderError(error.message || label('error_load'));
        } finally {
            loading.hidden = true;
        }
    }

    function updateToolbar() {
        root.querySelectorAll('[data-calendar-view]').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.calendarView === state.view);
        });
        const monthFormatter = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' });
        if (state.view === 'month') {
            period.textContent = monthFormatter.format(state.current);
        } else if (state.view === 'week') {
            const start = startOfWeek(state.current);
            const end = addDays(start, 6);
            const left = new Intl.DateTimeFormat(locale, { day: 'numeric', month: start.getMonth() === end.getMonth() ? undefined : 'short' }).format(start);
            const right = new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'short', year: 'numeric' }).format(end);
            period.textContent = `${left} — ${right}`;
        } else if (state.view === 'day') {
            period.textContent = new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'long', year: 'numeric' }).format(state.current);
        } else {
            period.textContent = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(state.current);
        }
    }

    function render() {
        updateToolbar();
        if (state.view === 'month') renderMonth();
        if (state.view === 'week') renderWeek();
        if (state.view === 'day') renderDay();
        if (state.view === 'list') renderList();
        bindStageActions();
    }

    function weekdayLabels() {
        return ['day_mon', 'day_tue', 'day_wed', 'day_thu', 'day_fri', 'day_sat', 'day_sun'].map((key) => label(key));
    }

    function renderMonth() {
        const start = state.range.start;
        const today = new Date();
        const cells = [];
        for (let index = 0; index < 42; index++) {
            const date = addDays(start, index);
            const events = eventsForDay(date);
            const visible = events.slice(0, 3);
            const classes = [
                'fb-calendar-day-cell',
                date.getMonth() !== state.current.getMonth() ? 'is-outside' : '',
                sameDay(date, today) ? 'is-today' : '',
            ].filter(Boolean).join(' ');
            const eventHtml = visible.map(eventChip).join('');
            const more = events.length > visible.length
                ? `<button class="fb-calendar-more-button" type="button" data-calendar-day="${dateKey(date)}">${escapeHtml(label('more', { count: events.length - visible.length }))}</button>`
                : '';
            cells.push(`<div class="${classes}" data-calendar-new-date="${dateKey(date)}">
                <button class="fb-calendar-day-cell__number" type="button" data-calendar-day="${dateKey(date)}">${date.getDate()}</button>
                <div class="fb-calendar-cell-events">${eventHtml}${more}</div>
            </div>`);
        }
        stage.innerHTML = `<div class="fb-calendar-weekdays">${weekdayLabels().map((day) => `<div class="fb-calendar-weekday">${escapeHtml(day)}</div>`).join('')}</div>
            <div class="fb-calendar-month-grid">${cells.join('')}</div>`;
    }

    function eventChip(event) {
        const start = parseDateTime(event.starts_at);
        const prefix = Number(event.all_day) ? '' : `${timeKey(start)} `;
        const classes = ['fb-calendar-event-chip', `is-${event.status || 'scheduled'}`].join(' ');
        return `<button class="${classes}" type="button" style="--event-color:${eventColor(event)}" data-calendar-event="${escapeHtml(event.occurrence_id)}" data-short-title="${escapeHtml(event.title)}" title="${escapeHtml(`${prefix}${event.title}`)}">${escapeHtml(prefix + event.title)}</button>`;
    }

    function renderWeek() {
        const start = state.range.start;
        const today = new Date();
        const columns = [];
        for (let index = 0; index < 7; index++) {
            const date = addDays(start, index);
            const events = eventsForDay(date);
            const dayName = new Intl.DateTimeFormat(locale, { weekday: 'short' }).format(date);
            const cards = events.map(eventCard).join('') || `<div class="small text-body-tertiary py-2">${escapeHtml(label('empty_day'))}</div>`;
            columns.push(`<div class="fb-calendar-week-column ${sameDay(date, today) ? 'is-today' : ''}" data-calendar-new-date="${dateKey(date)}">
                <div class="fb-calendar-week-column__head"><span class="small text-body-secondary text-capitalize">${escapeHtml(dayName)}</span><strong>${date.getDate()}</strong></div>
                <div class="fb-calendar-card-list">${cards}</div>
            </div>`);
        }
        stage.innerHTML = `<div class="fb-calendar-week-grid">${columns.join('')}</div>`;
    }

    function eventCard(event) {
        const start = parseDateTime(event.starts_at);
        const end = parseDateTime(event.ends_at);
        const time = Number(event.all_day) ? label('day') : `${timeKey(start)}–${timeKey(end)}`;
        return `<button class="fb-calendar-event-card" type="button" style="--event-color:${eventColor(event)}" data-calendar-event="${escapeHtml(event.occurrence_id)}">
            <span class="fb-calendar-event-card__time">${escapeHtml(time)}</span>
            <span class="fb-calendar-event-card__title">${escapeHtml(event.title)}</span>
        </button>`;
    }

    function renderDay() {
        const date = startOfDay(state.current);
        const events = eventsForDay(date);
        const weekday = new Intl.DateTimeFormat(locale, { weekday: 'long' }).format(date);
        const month = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(date);
        const cards = events.map((event) => {
            const start = parseDateTime(event.starts_at);
            const end = parseDateTime(event.ends_at);
            const time = Number(event.all_day) ? label('day') : `${timeKey(start)} — ${timeKey(end)}`;
            return `<button class="fb-calendar-list-item" type="button" style="--event-color:${eventColor(event)}" data-calendar-event="${escapeHtml(event.occurrence_id)}">
                <span class="fb-calendar-list-item__bar"></span>
                <strong>${escapeHtml(time)}</strong>
                <span><strong class="d-block">${escapeHtml(event.title)}</strong>${event.description ? `<small class="text-body-secondary">${escapeHtml(event.description)}</small>` : ''}</span>
                <span class="fb-calendar-list-item__badges">${eventBadges(event)}</span>
            </button>`;
        }).join('');
        stage.innerHTML = `<div class="fb-calendar-day-view" data-calendar-new-date="${dateKey(date)}">
            <div class="fb-calendar-day-view__head"><div class="fb-calendar-day-view__date"><span class="fb-calendar-day-view__number">${date.getDate()}</span><div><strong class="d-block text-capitalize">${escapeHtml(weekday)}</strong><span class="small text-body-secondary text-capitalize">${escapeHtml(month)}</span></div></div><span class="badge rounded-pill text-bg-light">${escapeHtml(label('event_count', { count: events.length }))}</span></div>
            <div class="fb-calendar-card-list">${cards || emptyMarkup()}</div>
        </div>`;
    }

    function renderList() {
        const groups = new Map();
        state.events.forEach((event) => {
            const key = dateKey(parseDateTime(event.starts_at));
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(event);
        });
        const html = Array.from(groups.entries()).map(([key, events]) => {
            const date = parseDateTime(`${key}T00:00:00`);
            const heading = new Intl.DateTimeFormat(locale, { weekday: 'long', day: 'numeric', month: 'long' }).format(date);
            const rows = events.map((event) => {
                const start = parseDateTime(event.starts_at);
                const time = Number(event.all_day) ? label('day') : timeKey(start);
                return `<button class="fb-calendar-list-item" type="button" style="--event-color:${eventColor(event)}" data-calendar-event="${escapeHtml(event.occurrence_id)}">
                    <span class="fb-calendar-list-item__bar"></span>
                    <strong>${escapeHtml(time)}</strong>
                    <span><strong class="d-block">${escapeHtml(event.title)}</strong>${event.description ? `<small class="text-body-secondary text-truncate d-block">${escapeHtml(event.description)}</small>` : ''}</span>
                    <span class="fb-calendar-list-item__badges">${eventBadges(event)}</span>
                </button>`;
            }).join('');
            return `<section class="fb-calendar-list-group"><div class="fb-calendar-list-group__date">${escapeHtml(heading)}</div>${rows}</section>`;
        }).join('');
        stage.innerHTML = `<div class="fb-calendar-list-view">${html || emptyMarkup()}</div>`;
    }

    function eventBadges(event) {
        const badges = [];
        if (event.recurrence && event.recurrence !== 'none') badges.push(label('repeating_badge'));
        if (event.visibility && event.visibility !== 'personal') badges.push(label('shared_badge'));
        if (event.status && event.status !== 'scheduled') badges.push(label(`status_${event.status}`));
        return badges.map((badge) => `<span class="fb-calendar-list-item__badge">${escapeHtml(badge)}</span>`).join('');
    }

    function emptyMarkup(message = label('empty_day')) {
        return `<div class="fb-calendar-empty"><i class="ci-calendar"></i><span>${escapeHtml(message)}</span></div>`;
    }

    function renderUpcoming() {
        const now = startOfDay(new Date());
        const items = state.events
            .filter((event) => parseDateTime(event.ends_at) >= now && event.status === 'scheduled')
            .sort((a, b) => parseDateTime(a.starts_at) - parseDateTime(b.starts_at))
            .slice(0, 6);
        upcomingCount.textContent = label('event_count', { count: items.length });
        if (!items.length) {
            upcoming.innerHTML = `<div class="small text-body-secondary py-3">${escapeHtml(label('no_upcoming'))}</div>`;
            return;
        }
        const month = new Intl.DateTimeFormat(locale, { month: 'short' });
        upcoming.innerHTML = items.map((event) => {
            const start = parseDateTime(event.starts_at);
            const time = Number(event.all_day) ? label('day') : timeKey(start);
            return `<button class="fb-calendar-upcoming-item" type="button" style="--event-color:${eventColor(event)}" data-calendar-event="${escapeHtml(event.occurrence_id)}">
                <span class="fb-calendar-upcoming-item__date"><strong>${start.getDate()}</strong><small>${escapeHtml(month.format(start))}</small></span>
                <span class="min-w-0"><strong class="d-block text-truncate">${escapeHtml(event.title)}</strong><small class="text-body-secondary">${escapeHtml(time)}</small></span>
            </button>`;
        }).join('');
        upcoming.querySelectorAll('[data-calendar-event]').forEach((button) => button.addEventListener('click', () => openEvent(eventByOccurrence(button.dataset.calendarEvent))));
    }

    function renderError(message) {
        stage.innerHTML = `<div class="fb-calendar-empty"><i class="ci-alert-triangle"></i><span class="mb-3">${escapeHtml(message)}</span><button class="btn btn-outline-secondary rounded-pill" type="button" data-calendar-retry>${escapeHtml(label('retry'))}</button></div>`;
        stage.querySelector('[data-calendar-retry]')?.addEventListener('click', loadEvents);
        upcoming.innerHTML = '';
        upcomingCount.textContent = '';
    }

    function bindStageActions() {
        stage.querySelectorAll('[data-calendar-event]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                openEvent(eventByOccurrence(button.dataset.calendarEvent));
            });
        });
        stage.querySelectorAll('[data-calendar-day]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                state.current = parseDateTime(`${button.dataset.calendarDay}T09:00:00`);
                state.view = 'day';
                saveView(state.view);
                loadEvents();
            });
        });
        stage.querySelectorAll('[data-calendar-new-date]').forEach((cell) => {
            cell.addEventListener('dblclick', (event) => {
                if (!canManage) return;
                if (event.target.closest('[data-calendar-event], [data-calendar-day]')) return;
                openCreate(parseDateTime(`${cell.dataset.calendarNewDate}T09:00:00`));
            });
        });
    }

    function openRequestedEvent() {
        if (requestedEventOpened || requestedEventId <= 0) return;
        const event = state.events.find((item) => Number(item.event_id || item.id) === requestedEventId);
        if (event) {
            requestedEventOpened = true;
            openEvent(event);
        }
    }

    function resetForm() {
        form.reset();
        setPickerValue('start_date', '');
        setPickerValue('start_time', '09:00');
        setPickerValue('end_date', '');
        setPickerValue('end_time', '10:00');
        setPickerValue('recurrence_until', '');
        form.elements.id.value = '';
        form.elements.color.value = '#6f5ef9';
        form.elements.status.value = 'scheduled';
        form.elements.recurrence.value = 'none';
        if (form.elements.visibility) form.elements.visibility.value = 'personal';
        reminderList.innerHTML = '';
        moreActions.hidden = true;
        saveButton.hidden = false;
        form.querySelectorAll('input, textarea, select, button').forEach((element) => { element.disabled = false; });
        form.querySelectorAll('[data-bs-dismiss="modal"]').forEach((element) => { element.disabled = false; });
        updateAllDay();
        updateRecurrence();
        updateAudience();
    }

    function openCreate(date = new Date()) {
        resetForm();
        const start = new Date(date.getTime());
        start.setMinutes(0, 0, 0);
        if (start < new Date()) start.setHours(start.getHours() + 1);
        const end = new Date(start.getTime() + 60 * 60 * 1000);
        setPickerValue('start_date', dateKey(start));
        setPickerValue('start_time', timeKey(start));
        setPickerValue('end_date', dateKey(end));
        setPickerValue('end_time', timeKey(end));
        modalTitle.textContent = label('event_create');
        addReminder({ value: 1, unit: 'days', time: '09:00', site: true, push: true });
        eventModal()?.show();
        window.setTimeout(() => form.elements.title.focus(), 250);
    }

    function openEvent(event) {
        if (!event) return;
        resetForm();
        const start = parseDateTime(event.series_starts_at || event.starts_at);
        const end = parseDateTime(event.series_ends_at || event.ends_at);
        form.elements.id.value = String(event.event_id || event.id);
        form.elements.title.value = event.title || '';
        form.elements.description.value = event.description || '';
        form.elements.color.value = eventColor(event);
        form.elements.all_day.checked = Number(event.all_day) === 1;
        setPickerValue('start_date', dateKey(start));
        setPickerValue('start_time', timeKey(start));
        setPickerValue('end_date', dateKey(end));
        setPickerValue('end_time', timeKey(end));
        form.elements.recurrence.value = event.recurrence || 'none';
        setPickerValue('recurrence_until', event.recurrence_until ? dateKey(parseDateTime(event.recurrence_until)) : '');
        form.elements.status.value = event.status || 'scheduled';
        if (form.elements.visibility) form.elements.visibility.value = event.visibility || 'personal';
        if (form.elements.audience_role) form.elements.audience_role.value = event.audience_role || '';
        if (form.elements['audience_user_ids[]']) {
            const selected = (event.audience_user_ids || []).map(Number);
            Array.from(form.elements['audience_user_ids[]'].options).forEach((option) => { option.selected = selected.includes(Number(option.value)); });
        }
        (event.reminders || []).forEach(addReminder);
        updateAllDay();
        updateRecurrence();
        updateAudience();
        modalTitle.textContent = label('event_edit');
        const editable = canManage && Boolean(event.editable);
        moreActions.hidden = !editable;
        const statusAction = root.querySelector('[data-calendar-toggle-status] span');
        if (statusAction) statusAction.textContent = event.status === 'completed' ? label('restore_scheduled') : label('mark_complete');
        if (!editable) {
            form.querySelectorAll('input, textarea, select, button').forEach((element) => { element.disabled = true; });
            form.querySelectorAll('[data-bs-dismiss="modal"]').forEach((element) => { element.disabled = false; });
            saveButton.hidden = true;
        }
        eventModal()?.show();
    }

    function addReminder(data = {}) {
        const fragment = reminderTemplate.content.cloneNode(true);
        const row = fragment.querySelector('[data-calendar-reminder-row]');
        row.querySelector('[data-reminder-id]').value = String(data.id || 0);
        row.querySelector('[data-reminder-value]').value = String(data.value ?? data.offset_value ?? 1);
        row.querySelector('[data-reminder-unit]').value = data.unit || data.offset_unit || 'days';
        row.querySelector('[data-reminder-time]').value = data.time || data.remind_time || '09:00';
        row.querySelector('[data-reminder-site]').checked = data.site !== undefined ? Boolean(data.site) : Number(data.site_notification ?? 1) === 1;
        row.querySelector('[data-reminder-push]').checked = data.push !== undefined ? Boolean(data.push) : Number(data.push_notification ?? 1) === 1;
        row.querySelector('[data-calendar-remove-reminder]').addEventListener('click', () => row.remove());
        row.querySelector('[data-reminder-unit]').addEventListener('change', () => updateReminderRow(row));
        reminderList.appendChild(fragment);
        updateReminderRow(row);
    }

    function updateReminderRow(row) {
        const unit = row.querySelector('[data-reminder-unit]').value;
        const value = row.querySelector('[data-reminder-value]');
        value.max = unit === 'days' ? '10' : (unit === 'hours' ? '168' : '1440');
        const timeField = row.querySelector('.fb-calendar-reminder-row__time');
        timeField.hidden = unit !== 'days';
    }

    function updateAllDay() {
        const allDay = Boolean(form.elements.all_day.checked);
        form.querySelectorAll('[data-calendar-time-field]').forEach((field) => { field.hidden = allDay; });
        form.elements.start_time.required = !allDay;
        form.elements.end_time.required = !allDay;
    }

    function updateRecurrence() {
        root.querySelector('[data-calendar-recurrence-until]').hidden = form.elements.recurrence.value === 'none';
    }

    function updateAudience() {
        if (!form.elements.visibility) return;
        const visibility = form.elements.visibility.value;
        const roleField = root.querySelector('[data-calendar-audience-role]');
        const usersField = root.querySelector('[data-calendar-audience-users]');
        if (roleField) roleField.hidden = visibility !== 'role';
        if (usersField) usersField.hidden = visibility !== 'users';
    }

    function serializeForm() {
        const allDay = Boolean(form.elements.all_day.checked);
        const startDate = form.elements.start_date.value;
        const endDate = form.elements.end_date.value;
        const payload = {
            title: form.elements.title.value.trim(),
            description: form.elements.description.value.trim(),
            color: form.elements.color.value,
            all_day: allDay,
            starts_at: `${startDate}T${allDay ? '00:00:00' : `${form.elements.start_time.value}:00`}`,
            ends_at: `${endDate}T${allDay ? '23:59:59' : `${form.elements.end_time.value}:00`}`,
            recurrence: form.elements.recurrence.value,
            recurrence_until: form.elements.recurrence_until.value,
            status: form.elements.status.value,
            visibility: form.elements.visibility?.value || 'personal',
            audience_role: form.elements.audience_role?.value || '',
            audience_user_ids: form.elements['audience_user_ids[]']
                ? Array.from(form.elements['audience_user_ids[]'].selectedOptions).map((option) => Number(option.value))
                : [],
            reminders: Array.from(reminderList.querySelectorAll('[data-calendar-reminder-row]')).map((row) => ({
                id: Number(row.querySelector('[data-reminder-id]').value || 0),
                value: Number(row.querySelector('[data-reminder-value]').value || 1),
                unit: row.querySelector('[data-reminder-unit]').value,
                time: row.querySelector('[data-reminder-time]').value,
                site: row.querySelector('[data-reminder-site]').checked,
                push: row.querySelector('[data-reminder-push]').checked,
            })),
        };
        return payload;
    }

    async function post(url, payload = {}) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': config.csrf || '',
            },
            body: JSON.stringify({ ...payload, needCSRFToken: config.csrf || '' }),
        });
        let data = {};
        try { data = await response.json(); } catch (error) {}
        if (!response.ok || !data.status) throw new Error(data.message || label('error_generic'));
        return data;
    }

    function endpoint(id, action) {
        return `${String(config.eventsUrl).replace(/\/$/, '')}/${Number(id)}/${action}`;
    }

    function notify(message, type = 'success') {
        if (window.toastr && typeof window.toastr[type] === 'function') {
            window.toastr[type](message);
            return;
        }
        if (type === 'error') window.alert(message);
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form.reportValidity()) return;
        const id = Number(form.elements.id.value || 0);
        saveButton.disabled = true;
        try {
            const data = await post(id > 0 ? endpoint(id, 'update') : config.eventsUrl, serializeForm());
            eventModal()?.hide();
            notify(data.message || label('saved'));
            await loadEvents();
        } catch (error) {
            notify(error.message || label('error_generic'), 'error');
        } finally {
            saveButton.disabled = false;
        }
    });

    root.querySelector('[data-calendar-delete]')?.addEventListener('click', async () => {
        const id = Number(form.elements.id.value || 0);
        if (!id || !window.confirm(label('delete_confirm'))) return;
        try {
            const data = await post(endpoint(id, 'delete'));
            eventModal()?.hide();
            notify(data.message || label('deleted'));
            await loadEvents();
        } catch (error) { notify(error.message || label('error_generic'), 'error'); }
    });

    root.querySelector('[data-calendar-duplicate]')?.addEventListener('click', async () => {
        const id = Number(form.elements.id.value || 0);
        if (!id) return;
        try {
            const data = await post(endpoint(id, 'duplicate'));
            eventModal()?.hide();
            notify(data.message || label('duplicated'));
            await loadEvents();
        } catch (error) { notify(error.message || label('error_generic'), 'error'); }
    });

    root.querySelector('[data-calendar-toggle-status]')?.addEventListener('click', async () => {
        const id = Number(form.elements.id.value || 0);
        if (!id) return;
        const status = form.elements.status.value === 'completed' ? 'scheduled' : 'completed';
        try {
            const data = await post(endpoint(id, 'status'), { status });
            eventModal()?.hide();
            notify(data.message || label('saved'));
            await loadEvents();
        } catch (error) { notify(error.message || label('error_generic'), 'error'); }
    });

    root.querySelector('[data-calendar-add-reminder]')?.addEventListener('click', () => addReminder());
    root.querySelectorAll('[data-calendar-quick-reminder]').forEach((button) => {
        button.addEventListener('click', () => {
            const [value, unit, time] = button.dataset.calendarQuickReminder.split(':');
            addReminder({ value: Number(value), unit, time, site: true, push: true });
        });
    });
    form.elements.all_day.addEventListener('change', updateAllDay);
    form.elements.recurrence.addEventListener('change', updateRecurrence);
    form.elements.visibility?.addEventListener('change', updateAudience);

    root.querySelector('[data-calendar-create]')?.addEventListener('click', () => openCreate(state.current));
    root.querySelector('[data-calendar-today]')?.addEventListener('click', () => { state.current = new Date(); loadEvents(); });
    root.querySelector('[data-calendar-prev]')?.addEventListener('click', () => move(-1));
    root.querySelector('[data-calendar-next]')?.addEventListener('click', () => move(1));
    root.querySelectorAll('[data-calendar-view]').forEach((button) => {
        button.addEventListener('click', () => {
            state.view = button.dataset.calendarView;
            saveView(state.view);
            loadEvents();
        });
    });

    function move(direction) {
        const next = new Date(state.current.getTime());
        if (state.view === 'month') next.setMonth(next.getMonth() + direction);
        else if (state.view === 'week') next.setDate(next.getDate() + 7 * direction);
        else if (state.view === 'day') next.setDate(next.getDate() + direction);
        else next.setDate(next.getDate() + 30 * direction);
        state.current = next;
        loadEvents();
    }

    initDateTimePickers();
    loadEvents();
})();
