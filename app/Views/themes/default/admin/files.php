<section
    class="container py-5 my-2 my-md-4 my-lg-5"
    data-file-manager-page
    data-fm-selection-required="<?= htmlSC(return_translation('admin_files_selection_required')) ?>"
    data-fm-rename-single="<?= htmlSC(return_translation('admin_files_rename_single_required')) ?>"
    data-fm-open-single="<?= htmlSC(return_translation('admin_files_open_single_required')) ?>"
    data-fm-delete-confirm="<?= htmlSC(return_translation('admin_files_delete_selected_confirm')) ?>"
>
    <style>
        [data-file-manager-page] {
            --fm-bg: linear-gradient(180deg, rgba(246, 244, 238, .95), rgba(255, 255, 255, .98));
            --fm-panel: rgba(255, 255, 255, .84);
            --fm-border: rgba(28, 37, 38, .1);
            --fm-shadow: 0 18px 60px rgba(27, 39, 45, .12);
            --fm-accent: #1f5c4f;
            --fm-accent-soft: rgba(31, 92, 79, .1);
            --fm-row-hover: rgba(31, 92, 79, .06);
            --fm-row-active: rgba(31, 92, 79, .12);
            --fm-sidebar: linear-gradient(180deg, rgba(245, 241, 232, .95), rgba(251, 250, 246, .88));
            --fm-toolbar: rgba(255, 255, 255, .8);
            --fm-toolbar-border: rgba(28, 37, 38, .08);
            --fm-sidebar-active-text: #163b34;
            --fm-row-active-border: rgba(31, 92, 79, .14);
        }

        [data-bs-theme="dark"] [data-file-manager-page] {
            --fm-bg: linear-gradient(180deg, rgba(16, 22, 27, .98), rgba(12, 16, 21, .98));
            --fm-panel: rgba(21, 28, 35, .92);
            --fm-border: rgba(255, 255, 255, .08);
            --fm-shadow: 0 24px 72px rgba(0, 0, 0, .38);
            --fm-accent: #58c3ac;
            --fm-accent-soft: rgba(88, 195, 172, .14);
            --fm-row-hover: rgba(88, 195, 172, .08);
            --fm-row-active: rgba(88, 195, 172, .14);
            --fm-sidebar: linear-gradient(180deg, rgba(17, 24, 31, .98), rgba(13, 19, 25, .94));
            --fm-toolbar: rgba(18, 24, 30, .88);
            --fm-toolbar-border: rgba(255, 255, 255, .06);
            --fm-sidebar-active-text: #dff8f1;
            --fm-row-active-border: rgba(88, 195, 172, .24);
        }

        [data-file-manager-shell] {
            background: var(--fm-bg);
            border: 1px solid var(--fm-border);
            border-radius: 32px;
            box-shadow: var(--fm-shadow);
            overflow: hidden;
        }

        [data-file-manager-browser] {
            position: relative;
            min-height: 540px;
            transition: opacity .18s ease, transform .18s ease;
        }

        [data-file-manager-browser].is-loading {
            opacity: .62;
            transform: translateY(2px);
            pointer-events: none;
        }

        [data-file-manager-workspace] {
            display: grid;
            grid-template-columns: minmax(220px, 260px) minmax(0, 1fr);
            min-height: 540px;
        }

        [data-file-manager-sidebar] {
            background: var(--fm-sidebar);
            border-right: 1px solid var(--fm-border);
        }

        [data-file-manager-content] {
            background: var(--fm-panel);
            backdrop-filter: blur(16px);
            min-width: 0;
        }

        [data-file-manager-page] .min-w-0 {
            min-width: 0 !important;
        }

        [data-file-manager-page] .text-truncate {
            min-width: 0;
            max-width: 100%;
        }

        [data-file-manager-toolbar] {
            position: sticky;
            top: 0;
            z-index: 5;
            background: var(--fm-toolbar);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid var(--fm-toolbar-border);
            min-width: 0;
        }

        [data-file-manager-table] tbody tr {
            transition: background-color .15s ease, box-shadow .15s ease;
        }

        [data-file-manager-table] tbody tr:hover {
            background: var(--fm-row-hover);
        }

        [data-file-manager-table] tbody tr.is-selected {
            background: var(--fm-row-active);
            box-shadow: inset 0 0 0 1px var(--fm-row-active-border);
        }

        [data-file-manager-table] td,
        [data-file-manager-table] th {
            vertical-align: middle;
        }

        [data-file-manager-item-link] {
            color: inherit;
        }

        [data-file-manager-item-link]:hover {
            color: inherit;
        }

        [data-file-manager-name-cell] > .d-flex,
        [data-file-manager-name-cell] .min-w-0,
        [data-file-manager-name-cell] a,
        [data-file-manager-name-cell] .fw-medium,
        [data-file-manager-name-cell] .small {
            min-width: 0;
        }

        [data-file-manager-name-cell] a,
        [data-file-manager-name-cell] .fw-medium,
        [data-file-manager-name-cell] .small {
            display: block;
        }

        [data-file-manager-actions-menu] .dropdown-toggle {
            white-space: nowrap;
        }

        [data-file-manager-selection-badge] {
            background: var(--fm-accent-soft);
            color: var(--fm-accent);
        }

        [data-file-manager-breadcrumbs] {
            scrollbar-width: thin;
        }

        [data-file-manager-search-form] {
            max-width: 320px;
            width: 100%;
        }

        [data-file-manager-feedback-wrap]:empty {
            display: none;
        }

        [data-file-manager-feedback-wrap]:not(:empty) {
            padding-bottom: 0 !important;
        }

        [data-file-manager-sidebar] .list-group-item {
            border: 0;
            background: transparent;
            border-radius: 18px;
            margin-bottom: .2rem;
            min-width: 0;
        }

        [data-file-manager-sidebar] .list-group-item:hover {
            background: var(--fm-row-hover);
        }

        [data-file-manager-sidebar] .list-group-item.active {
            background: var(--fm-row-active);
            color: var(--fm-sidebar-active-text);
        }

        [data-file-manager-sidebar] .list-group-item.active .text-body-secondary,
        [data-bs-theme="dark"] [data-file-manager-page] [data-file-manager-table] thead .btn,
        [data-bs-theme="dark"] [data-file-manager-page] [data-file-manager-table] thead th {
            color: inherit !important;
        }

        [data-bs-theme="dark"] [data-file-manager-page] [data-file-manager-table] thead {
            background: rgba(18, 24, 30, .96);
        }

        [data-bs-theme="dark"] [data-file-manager-page] .table > :not(caption) > * > * {
            border-bottom-color: rgba(255, 255, 255, .08);
        }

        [data-bs-theme="dark"] [data-file-manager-page] .dropdown-menu,
        [data-bs-theme="dark"] [data-file-manager-page] .modal-content,
        [data-bs-theme="dark"] [data-file-manager-page] .input-group-text {
            background-color: #18212a;
            color: var(--bs-body-color);
        }

        [data-bs-theme="dark"] [data-file-manager-page] .dropdown-divider,
        [data-bs-theme="dark"] [data-file-manager-page] .modal-header,
        [data-bs-theme="dark"] [data-file-manager-page] .modal-footer,
        [data-bs-theme="dark"] [data-file-manager-page] .input-group-text {
            border-color: rgba(255, 255, 255, .08);
        }

        @media (max-width: 991.98px) {
            [data-file-manager-workspace] {
                grid-template-columns: 1fr;
            }

            [data-file-manager-sidebar] {
                border-right: 0;
                border-bottom: 1px solid var(--fm-border);
            }
        }

        @media (max-width: 767.98px) {
            [data-file-manager-page] {
                overflow-x: clip;
            }

            [data-file-manager-shell] {
                width: 100%;
                max-width: 100%;
                border-radius: 24px;
            }

            [data-file-manager-browser],
            [data-file-manager-workspace] {
                min-height: auto;
                width: 100%;
                max-width: 100%;
                min-width: 0;
            }

            [data-file-manager-toolbar] {
                position: static;
                width: 100%;
            }

            [data-file-manager-toolbar-actions] {
                width: 100%;
                display: grid;
                grid-template-columns: 1fr 1fr;
                min-width: 0;
            }

            [data-file-manager-toolbar-actions] .dropdown,
            [data-file-manager-toolbar-actions] .dropdown > button {
                width: 100%;
            }

            [data-file-manager-breadcrumbs] {
                flex-wrap: nowrap !important;
                overflow-x: auto;
                overflow-y: hidden;
                padding-bottom: .15rem;
            }

            [data-file-manager-breadcrumbs] > * {
                flex: 0 0 auto;
            }

            [data-file-manager-status] {
                align-items: stretch !important;
                min-width: 0;
            }

            [data-file-manager-status] > * {
                min-width: 0;
            }

            [data-file-manager-search-form] {
                max-width: none;
                min-width: 0;
            }

            [data-file-manager-table-wrap] {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                overflow-x: auto;
                overflow-y: hidden;
                -webkit-overflow-scrolling: touch;
            }

            [data-file-manager-table] {
                min-width: 620px;
            }

            [data-file-manager-table] th,
            [data-file-manager-table] td {
                padding: .65rem .55rem;
                font-size: .86rem;
            }

            [data-file-manager-table] .btn {
                font-size: .8rem;
            }

            [data-file-manager-actions-menu] > button {
                min-width: 2.5rem;
                padding-inline: .7rem;
            }

            [data-file-manager-name-cell] img,
            [data-file-manager-name-cell] .rounded-4.border.bg-body-tertiary {
                width: 44px !important;
                height: 44px !important;
            }

            [data-file-manager-name-cell] .fw-medium {
                font-size: .88rem;
            }

            [data-file-manager-name-cell] .small {
                font-size: .74rem;
            }

            [data-file-manager-feedback-wrap] {
                padding-inline: 1rem !important;
                padding-top: 1rem !important;
            }
        }
    </style>

    <div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= print_translation('admin_files_heading') ?></h1>
            <p class="text-body-secondary mb-0"><?= print_translation('admin_files_subtitle') ?></p>
        </div>
    </div>

    <?= view()->renderPartial('admin/nav') ?>

    <?php if (!empty($picker_mode)): ?>
        <div class="mb-3 small text-body-secondary"><?= print_translation('admin_files_picker_hint') ?></div>
    <?php endif; ?>

    <div class="position-relative" data-file-manager-shell>
        <div data-file-manager-browser>
            <?= view()->renderPartial('admin/file_manager_browser', [
                'manager' => $manager ?? [],
                'picker_mode' => $picker_mode ?? false,
                'picker_field' => $picker_field ?? '',
            ]) ?>
        </div>
    </div>
</section>
