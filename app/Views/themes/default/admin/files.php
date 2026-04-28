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
        }

        [data-file-manager-toolbar] {
            position: sticky;
            top: 0;
            z-index: 5;
            background: rgba(255, 255, 255, .8);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid rgba(28, 37, 38, .08);
        }

        [data-file-manager-table] tbody tr {
            transition: background-color .15s ease, box-shadow .15s ease;
        }

        [data-file-manager-table] tbody tr:hover {
            background: var(--fm-row-hover);
        }

        [data-file-manager-table] tbody tr.is-selected {
            background: var(--fm-row-active);
            box-shadow: inset 0 0 0 1px rgba(31, 92, 79, .14);
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

        [data-file-manager-selection-badge] {
            background: var(--fm-accent-soft);
            color: var(--fm-accent);
        }

        [data-file-manager-sidebar] .list-group-item {
            border: 0;
            background: transparent;
            border-radius: 18px;
            margin-bottom: .2rem;
        }

        [data-file-manager-sidebar] .list-group-item.active {
            background: rgba(31, 92, 79, .12);
            color: #163b34;
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
            <?= view()->renderPartial('admin/_file_manager_browser', [
                'manager' => $manager ?? [],
                'picker_mode' => $picker_mode ?? false,
                'picker_field' => $picker_field ?? '',
            ]) ?>
        </div>
    </div>
</section>
