(function (window) {
    'use strict';

    const previous = window.FireballEditor2 || {};
    const blockTypes = new Map();
    const extensions = new Map();
    const commands = new Map();
    const icons = new Map();
    const translations = new Map();
    const hooks = new Map();
    const editors = new Set();

    function normalizeName(value) {
        return String(value || '').trim().replace(/[^a-zA-Z0-9_-]/g, '');
    }

    function registerBlockType(name, definition) {
        if (name && typeof name === 'object' && !definition) {
            definition = name;
            name = definition.machine_name || definition.type;
        }
        const machineName = normalizeName(name);
        if (!machineName || !definition || typeof definition !== 'object') {
            throw new TypeError('A block type requires a valid name and definition.');
        }

        const normalized = Object.assign({
            machine_name: machineName,
            title: machineName,
            icon: 'ci-square',
            default_content: {},
            supports: {}
        }, definition, { machine_name: machineName });

        blockTypes.set(machineName, normalized);
        editors.forEach(function (editor) {
            if (editor && typeof editor.refreshRegistry === 'function') {
                editor.refreshRegistry();
            }
        });

        return normalized;
    }

    function unregisterBlockType(name) {
        blockTypes.delete(normalizeName(name));
    }

    function getBlockType(name) {
        return blockTypes.get(normalizeName(name)) || null;
    }

    function getBlockTypes() {
        return Array.from(blockTypes.values());
    }

    function registerExtension(name, extension) {
        const extensionName = normalizeName(name);
        if (!extensionName || !extension || typeof extension !== 'object') {
            throw new TypeError('An editor extension requires a valid name and definition.');
        }
        extensions.set(extensionName, extension);
        if (typeof extension.setup === 'function') {
            editors.forEach(function (editor) {
                extension.setup(editor);
            });
        }
        return extension;
    }

    function registerCommand(name, definition) {
        if (name && typeof name === 'object' && !definition) {
            definition = name;
            name = definition.id || definition.name;
        }
        const commandName = normalizeName(name);
        if (!commandName || !definition || typeof definition.run !== 'function') {
            throw new TypeError('An editor command requires a valid name and run callback.');
        }
        const normalized = Object.assign({
            id: commandName,
            label: commandName,
            icon: 'ci-command',
            keywords: ''
        }, definition, { id: commandName });
        commands.set(commandName, normalized);
        return normalized;
    }

    function unregisterCommand(name) {
        commands.delete(normalizeName(name));
    }

    function getCommands() {
        return Array.from(commands.values());
    }

    function registerIcon(name, className) {
        const iconName = normalizeName(name);
        const value = String(className || '').trim();
        if (!iconName || !/^ci-[a-z0-9-]+$/i.test(value)) {
            throw new TypeError('An editor icon requires a valid name and Cartzilla icon class.');
        }
        icons.set(iconName, value);
        return value;
    }

    function getIcon(name, fallback) {
        return icons.get(normalizeName(name)) || fallback || 'ci-square';
    }

    function registerTranslations(locale, values) {
        const language = String(locale || '').trim().toLowerCase();
        if (!language || !values || typeof values !== 'object') {
            throw new TypeError('Editor translations require a locale and values.');
        }
        translations.set(language, Object.assign({}, translations.get(language) || {}, values));
        return translations.get(language);
    }

    function translate(locale, key, fallback) {
        const language = String(locale || '').trim().toLowerCase();
        const values = translations.get(language) || {};
        return String(values[key] || fallback || key);
    }

    function addHook(name, callback, priority) {
        const hookName = String(name || '').trim();
        if (!hookName || typeof callback !== 'function') {
            return function () {};
        }
        const entry = { callback: callback, priority: Number(priority || 10) };
        const callbacks = hooks.get(hookName) || [];
        callbacks.push(entry);
        callbacks.sort(function (left, right) {
            return left.priority - right.priority;
        });
        hooks.set(hookName, callbacks);

        return function () {
            hooks.set(hookName, (hooks.get(hookName) || []).filter(function (item) {
                return item !== entry;
            }));
        };
    }

    function applyFilters(name, value) {
        const args = Array.prototype.slice.call(arguments, 2);
        return (hooks.get(String(name || '')) || []).reduce(function (current, item) {
            const result = item.callback.apply(null, [current].concat(args));
            return typeof result === 'undefined' ? current : result;
        }, value);
    }

    function doAction(name) {
        const args = Array.prototype.slice.call(arguments, 1);
        (hooks.get(String(name || '')) || []).forEach(function (item) {
            item.callback.apply(null, args);
        });
    }

    function attachEditor(editor) {
        editors.add(editor);
        extensions.forEach(function (extension) {
            if (typeof extension.setup === 'function') {
                extension.setup(editor);
            }
        });
        doAction('editor:ready', editor);
    }

    function detachEditor(editor) {
        editors.delete(editor);
    }

    window.FireballEditor2 = Object.assign(previous, {
        version: '2.0.0',
        internalMime: 'application/x-fireball-blocks+json',
        registerBlockType: registerBlockType,
        unregisterBlockType: unregisterBlockType,
        getBlockType: getBlockType,
        getBlockTypes: getBlockTypes,
        registerExtension: registerExtension,
        registerCommand: registerCommand,
        unregisterCommand: unregisterCommand,
        getCommands: getCommands,
        registerIcon: registerIcon,
        getIcon: getIcon,
        registerTranslations: registerTranslations,
        translate: translate,
        addHook: addHook,
        addAction: addHook,
        addFilter: addHook,
        applyFilters: applyFilters,
        doAction: doAction,
        attachEditor: attachEditor,
        detachEditor: detachEditor,
        getEditors: function () { return Array.from(editors); },
        editors: editors
    });
})(window);
