(function () {
    'use strict';

    const STORAGE = {
        sidebar: 'fireball.admin.sidebar.collapsed',
        nav: 'fireball.admin.nav.open',
        recent: 'fireball.admin.commands.recent'
    };

    const admin = document.querySelector('[data-fb-admin]');
    if (!admin || admin.dataset.fbReady === 'true') {
        return;
    }
    admin.dataset.fbReady = 'true';

    const storage = {
        get(key, fallback = null) {
            try {
                const value = window.localStorage.getItem(key);
                return value === null ? fallback : value;
            } catch (error) {
                return fallback;
            }
        },
        set(key, value) {
            try {
                window.localStorage.setItem(key, value);
            } catch (error) {
                // UI preferences are optional when storage is unavailable.
            }
        },
        json(key, fallback) {
            try {
                const value = JSON.parse(this.get(key, ''));
                return value && typeof value === 'object' ? value : fallback;
            } catch (error) {
                return fallback;
            }
        }
    };

    const sidebar = (() => {
        const toggles = Array.from(admin.querySelectorAll('[data-fb-sidebar-toggle]'));
        const desktopSidebar = admin.querySelector('[data-fb-sidebar]');
        const scrollElement = desktopSidebar?.querySelector('[data-fb-sidebar-scroll]');
        let tooltipInstances = [];
        let resizeFallback = 0;
        let scrollbarInstance = null;

        function isCollapsed() {
            return admin.classList.contains('is-sidebar-collapsed');
        }

        function recalculateScrollbar() {
            if (!scrollElement || !window.SimpleBar) {
                return;
            }

            const wrapper = scrollElement.querySelector('.simplebar-content-wrapper');
            const scrollTop = wrapper?.scrollTop || 0;
            scrollbarInstance = window.SimpleBar.instances?.get(scrollElement)
                || scrollbarInstance
                || new window.SimpleBar(scrollElement, { autoHide: false });

            scrollbarInstance.options.autoHide = false;
            scrollbarInstance.options.forceVisible = 'y';
            scrollbarInstance.options.scrollbarMinSize = 44;
            scrollbarInstance.options.scrollbarMaxSize = 180;
            scrollbarInstance.recalculate();
            if (wrapper) {
                wrapper.scrollTop = scrollTop;
            }
        }

        function queueScrollbarRecalculation() {
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(recalculateScrollbar);
            });
        }

        function finishResize() {
            window.clearTimeout(resizeFallback);
            admin.classList.remove('is-sidebar-resizing');
            recalculateScrollbar();
            syncTooltips();
            window.dispatchEvent(new CustomEvent('fireball:admin:resize'));
        }

        function destroyTooltips() {
            tooltipInstances.forEach((instance) => instance.dispose());
            tooltipInstances = [];
        }

        function syncTooltips() {
            destroyTooltips();
            if (!isCollapsed() || !window.bootstrap?.Tooltip) {
                return;
            }

            admin.querySelectorAll('[data-fb-sidebar] .fb-nav-link[title], [data-fb-sidebar] .fb-sidebar-footer-link[title], [data-fb-sidebar] .fb-sidebar-user[title]')
                .forEach((element) => {
                    tooltipInstances.push(window.bootstrap.Tooltip.getOrCreateInstance(element, {
                        placement: 'right',
                        trigger: 'hover focus',
                        container: 'body'
                    }));
                });
        }

        function apply(collapsed, persist = false) {
            if (persist) {
                admin.classList.add('is-sidebar-resizing');
            }
            admin.classList.toggle('is-sidebar-collapsed', collapsed);
            document.documentElement.dataset.fbSidebarCollapsed = collapsed ? 'true' : 'false';
            toggles.forEach((toggle) => {
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                const label = collapsed ? toggle.dataset.labelExpand : toggle.dataset.labelCollapse;
                if (label) {
                    toggle.setAttribute('aria-label', label);
                    toggle.setAttribute('title', label);
                }
            });
            if (persist) {
                storage.set(STORAGE.sidebar, collapsed ? '1' : '0');
            }
            window.requestAnimationFrame(() => {
                recalculateScrollbar();
                if (!persist) {
                    syncTooltips();
                }
            });

            if (persist) {
                window.clearTimeout(resizeFallback);
                resizeFallback = window.setTimeout(finishResize, 320);
            }
        }

        const initial = document.documentElement.dataset.fbSidebarCollapsed === 'true'
            || storage.get(STORAGE.sidebar, '0') === '1';
        apply(initial);
        window.requestAnimationFrame(() => {
            document.documentElement.dataset.fbAdminHydrated = 'true';
        });

        toggles.forEach((toggle) => {
            toggle.addEventListener('click', () => apply(!isCollapsed(), true));
        });

        desktopSidebar?.addEventListener('transitionend', (event) => {
            if (event.target === desktopSidebar && event.propertyName === 'width') {
                finishResize();
            }
        });

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', queueScrollbarRecalculation, { once: true });
        } else {
            queueScrollbarRecalculation();
        }
        window.addEventListener('load', queueScrollbarRecalculation, { once: true });

        return { apply, isCollapsed };
    })();

    const navigation = (() => {
        const saved = storage.json(STORAGE.nav, {});
        const parents = Array.from(admin.querySelectorAll('[data-fb-nav-parent]'));

        function keyFor(parent) {
            const label = parent.querySelector('[data-fb-nav-toggle] .fb-nav-label')?.textContent || '';
            return label.trim().toLocaleLowerCase();
        }

        function setOpen(parent, open, persist = false) {
            parent.classList.toggle('is-open', open);
            parent.querySelector('[data-fb-nav-toggle]')?.setAttribute('aria-expanded', open ? 'true' : 'false');

            if (persist) {
                saved[keyFor(parent)] = open;
                storage.set(STORAGE.nav, JSON.stringify(saved));

                parents
                    .filter((item) => item !== parent && keyFor(item) === keyFor(parent))
                    .forEach((item) => setOpen(item, open, false));
            }
        }

        parents.forEach((parent) => {
            const key = keyFor(parent);
            if (Object.prototype.hasOwnProperty.call(saved, key)) {
                setOpen(parent, Boolean(saved[key]));
            }
            parent.querySelector('[data-fb-nav-toggle]')?.addEventListener('click', () => {
                setOpen(parent, !parent.classList.contains('is-open'), true);
            });
        });

        admin.querySelectorAll('.fb-sidebar-drawer .fb-nav-link[href]').forEach((link) => {
            link.addEventListener('click', () => {
                const drawer = document.getElementById('adminSidebar');
                if (drawer && window.bootstrap?.Offcanvas) {
                    window.bootstrap.Offcanvas.getOrCreateInstance(drawer).hide();
                }
            });
        });

        return { parents };
    })();

    const commandPalette = (() => {
        const root = admin.querySelector('[data-fb-command-palette]');
        if (!root) {
            return null;
        }

        const dialog = root.querySelector('.fb-command-dialog');
        const input = root.querySelector('[data-fb-command-input]');
        const results = root.querySelector('[data-fb-command-results]');
        const openers = Array.from(admin.querySelectorAll('[data-fb-command-open]'));
        const emptyLabel = root.dataset.emptyLabel || 'No results';
        const actionsLabel = root.dataset.actionsLabel || 'Actions';
        let commands = [];
        let visibleCommands = [];
        let activeIndex = 0;
        let returnFocus = null;

        function clean(value) {
            return String(value || '')
                .toLocaleLowerCase()
                .normalize('NFKD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/\s+/g, ' ')
                .trim();
        }

        function collect() {
            const map = new Map();
            document.querySelectorAll('a[data-fb-command][href], a[data-fb-quick-action][href]').forEach((element) => {
                const href = element.href;
                if (!href || map.has(href)) {
                    return;
                }

                const label = element.dataset.fbCommandLabel
                    || element.dataset.fbQuickActionLabel
                    || element.textContent.trim();
                if (!label) {
                    return;
                }

                const command = {
                    href,
                    label,
                    category: element.dataset.fbCommandCategory
                        || element.dataset.fbQuickActionCategory
                        || actionsLabel,
                    icon: element.dataset.fbCommandIcon
                        || element.dataset.fbQuickActionIcon
                        || element.querySelector('i')?.className
                        || 'ci-arrow-right',
                    search: ''
                };
                command.search = clean(`${command.label} ${command.category} ${element.dataset.fbCommandKeywords || ''}`);
                map.set(href, command);
            });
            commands = Array.from(map.values());
        }

        function recentRank(href) {
            const recent = storage.json(STORAGE.recent, []);
            const index = Array.isArray(recent) ? recent.indexOf(href) : -1;
            return index === -1 ? Number.MAX_SAFE_INTEGER : index;
        }

        function remember(href) {
            const recent = storage.json(STORAGE.recent, []);
            const items = Array.isArray(recent) ? recent.filter((item) => item !== href) : [];
            items.unshift(href);
            storage.set(STORAGE.recent, JSON.stringify(items.slice(0, 8)));
        }

        function render() {
            const query = clean(input.value);
            visibleCommands = commands
                .filter((command) => query === '' || command.search.includes(query))
                .sort((left, right) => {
                    if (query === '') {
                        return recentRank(left.href) - recentRank(right.href);
                    }
                    const leftStart = clean(left.label).startsWith(query) ? 0 : 1;
                    const rightStart = clean(right.label).startsWith(query) ? 0 : 1;
                    return leftStart - rightStart || left.label.localeCompare(right.label);
                });

            activeIndex = Math.min(activeIndex, Math.max(visibleCommands.length - 1, 0));
            results.innerHTML = '';

            if (visibleCommands.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'fb-command-empty';
                empty.textContent = emptyLabel;
                results.appendChild(empty);
                return;
            }

            let lastCategory = '';
            visibleCommands.forEach((command, index) => {
                if (command.category !== lastCategory) {
                    const heading = document.createElement('div');
                    heading.className = 'fb-command-group-label';
                    heading.textContent = command.category;
                    results.appendChild(heading);
                    lastCategory = command.category;
                }

                const button = document.createElement('button');
                button.type = 'button';
                button.className = `fb-command-item${index === activeIndex ? ' is-active' : ''}`;
                button.setAttribute('role', 'option');
                button.setAttribute('aria-selected', index === activeIndex ? 'true' : 'false');
                button.dataset.commandIndex = String(index);

                const icon = document.createElement('span');
                icon.className = 'fb-command-item-icon';
                const iconGlyph = document.createElement('i');
                String(command.icon).split(/\s+/).filter(Boolean).forEach((className) => iconGlyph.classList.add(className));
                iconGlyph.setAttribute('aria-hidden', 'true');
                icon.appendChild(iconGlyph);

                const label = document.createElement('span');
                label.className = 'fb-command-item-label';
                label.textContent = command.label;

                const category = document.createElement('span');
                category.className = 'fb-command-item-category';
                category.textContent = command.category;

                button.append(icon, label, category);
                button.addEventListener('mouseenter', () => {
                    activeIndex = index;
                    syncActive();
                });
                button.addEventListener('click', () => activate(index));
                results.appendChild(button);
            });
        }

        function syncActive() {
            results.querySelectorAll('[data-command-index]').forEach((button) => {
                const active = Number(button.dataset.commandIndex) === activeIndex;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
                if (active) {
                    button.scrollIntoView({ block: 'nearest' });
                }
            });
        }

        function activate(index = activeIndex) {
            const command = visibleCommands[index];
            if (!command) {
                return;
            }
            remember(command.href);
            window.location.assign(command.href);
        }

        function focusables() {
            return Array.from(dialog.querySelectorAll('input, button:not([disabled]), [href], [tabindex]:not([tabindex="-1"])'))
                .filter((element) => !element.hidden && element.offsetParent !== null);
        }

        function open(trigger) {
            collect();
            returnFocus = trigger instanceof HTMLElement ? trigger : document.activeElement;
            root.hidden = false;
            root.setAttribute('aria-hidden', 'false');
            document.body.classList.add('fb-command-open');
            input.value = '';
            activeIndex = 0;
            render();
            window.requestAnimationFrame(() => input.focus());
        }

        function close() {
            if (root.hidden) {
                return;
            }
            root.hidden = true;
            root.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('fb-command-open');
            if (returnFocus instanceof HTMLElement && document.contains(returnFocus)) {
                returnFocus.focus();
            }
        }

        openers.forEach((opener) => opener.addEventListener('click', () => open(opener)));
        root.querySelectorAll('[data-fb-command-close]').forEach((closer) => closer.addEventListener('click', close));
        input.addEventListener('input', () => {
            activeIndex = 0;
            render();
        });

        root.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                close();
                return;
            }
            if (event.key === 'ArrowDown' && visibleCommands.length > 0) {
                event.preventDefault();
                activeIndex = (activeIndex + 1) % visibleCommands.length;
                syncActive();
                return;
            }
            if (event.key === 'ArrowUp' && visibleCommands.length > 0) {
                event.preventDefault();
                activeIndex = (activeIndex - 1 + visibleCommands.length) % visibleCommands.length;
                syncActive();
                return;
            }
            if (event.key === 'Enter' && document.activeElement === input) {
                event.preventDefault();
                activate();
                return;
            }
            if (event.key === 'Tab') {
                const items = focusables();
                if (items.length === 0) {
                    event.preventDefault();
                    return;
                }
                const first = items[0];
                const last = items[items.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });

        document.addEventListener('keydown', (event) => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLocaleLowerCase() === 'k') {
                event.preventDefault();
                root.hidden ? open(document.activeElement) : close();
            }
        });

        return { open, close, collect };
    })();

    admin.querySelectorAll('[data-fb-notifications-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const toggle = admin.querySelector('[data-fb-notifications-toggle]');
            if (!toggle) {
                return;
            }
            if (window.bootstrap?.Dropdown) {
                window.bootstrap.Dropdown.getOrCreateInstance(toggle).show();
            } else {
                toggle.click();
            }
        });
    });

    (() => {
        const floatingMenus = new WeakMap();
        const openDropdowns = new Set();

        function dropdownFromEvent(event) {
            const dropdown = event.target?.closest?.('.dropdown');
            if (!dropdown || !dropdown.closest('.table-responsive, .admin-table-component__scroll')) {
                return null;
            }
            if (dropdown.matches('[data-admin-post-actions-dropdown], [data-file-manager-actions-menu]')) {
                return null;
            }
            return dropdown;
        }

        function place(dropdown) {
            const state = floatingMenus.get(dropdown);
            if (!state) {
                return;
            }
            const rect = state.toggle.getBoundingClientRect();
            const menuRect = state.menu.getBoundingClientRect();
            const gap = 6;
            const edge = 10;
            const below = window.innerHeight - rect.bottom - edge;
            const above = rect.top - edge;
            const openAbove = menuRect.height > below && above > below;
            const top = openAbove
                ? Math.max(edge, rect.top - menuRect.height - gap)
                : Math.min(rect.bottom + gap, window.innerHeight - menuRect.height - edge);
            const left = Math.min(
                Math.max(edge, rect.right - menuRect.width),
                window.innerWidth - menuRect.width - edge
            );

            state.menu.style.top = `${Math.max(edge, top)}px`;
            state.menu.style.left = `${Math.max(edge, left)}px`;
        }

        document.addEventListener('show.bs.dropdown', (event) => {
            const dropdown = dropdownFromEvent(event);
            if (!dropdown || floatingMenus.has(dropdown)) {
                return;
            }
            const menu = dropdown.querySelector('.dropdown-menu');
            const toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
            if (!menu || !toggle) {
                return;
            }

            const placeholder = document.createComment('fb-table-dropdown');
            menu.parentNode.insertBefore(placeholder, menu);
            floatingMenus.set(dropdown, { menu, toggle, placeholder });
            openDropdowns.add(dropdown);
            document.body.appendChild(menu);
            menu.classList.add('fb-table-dropdown-floating');
        });

        document.addEventListener('shown.bs.dropdown', (event) => {
            const dropdown = event.target?.closest?.('.dropdown');
            if (dropdown && floatingMenus.has(dropdown)) {
                place(dropdown);
            }
        });

        document.addEventListener('hidden.bs.dropdown', (event) => {
            const dropdown = event.target?.closest?.('.dropdown');
            const state = dropdown ? floatingMenus.get(dropdown) : null;
            if (!state) {
                return;
            }
            state.menu.classList.remove('fb-table-dropdown-floating');
            state.menu.removeAttribute('style');
            state.placeholder.parentNode?.insertBefore(state.menu, state.placeholder);
            state.placeholder.remove();
            floatingMenus.delete(dropdown);
            openDropdowns.delete(dropdown);
        });

        window.addEventListener('resize', () => {
            openDropdowns.forEach(place);
        });
        document.addEventListener('scroll', () => {
            openDropdowns.forEach(place);
        }, true);
    })();

    const currentPath = window.location.pathname.replace(/\/+$/, '') || '/';
    admin.querySelectorAll('.fb-mobile-nav a[href]').forEach((link) => {
        const linkPath = new URL(link.href, window.location.href).pathname.replace(/\/+$/, '') || '/';
        const isDashboard = linkPath.endsWith('/admin') && currentPath === linkPath;
        const isProfile = linkPath.endsWith('/profile') && currentPath === linkPath;
        link.classList.toggle('active', isDashboard || isProfile);
        if (isDashboard || isProfile) {
            link.setAttribute('aria-current', 'page');
        }
    });

    window.FireballAdmin = Object.freeze({
        sidebar,
        navigation,
        commandPalette,
        refreshCommands: () => commandPalette?.collect()
    });

    document.dispatchEvent(new CustomEvent('fireball:admin:ready', {
        detail: window.FireballAdmin
    }));
})();
