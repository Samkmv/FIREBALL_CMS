<style>
        [data-file-manager-page] {
            --fm-bg: var(--fb-color-surface);
            --fm-panel: var(--fb-color-surface);
            --fm-border: rgba(28, 37, 38, .1);
            --fm-shadow: none;
            --fm-accent: var(--fb-color-primary);
            --fm-accent-soft: var(--fb-color-primary-soft);
            --fm-row-hover: color-mix(in srgb, var(--fb-color-primary) 4%, transparent);
            --fm-row-active: color-mix(in srgb, var(--fb-color-primary) 9%, transparent);
            --fm-sidebar: var(--fb-color-surface-secondary);
            --fm-toolbar: color-mix(in srgb, var(--fb-color-surface) 96%, transparent);
            --fm-toolbar-border: rgba(28, 37, 38, .08);
            --fm-sidebar-active-text: #111827;
            --fm-row-active-border: color-mix(in srgb, var(--fb-color-primary) 28%, transparent);
            display: flex;
            flex-direction: column;
        }

        [data-bs-theme="dark"] [data-file-manager-page] {
            --fm-bg: var(--fb-color-surface);
            --fm-panel: var(--fb-color-surface);
            --fm-border: rgba(255, 255, 255, .08);
            --fm-shadow: none;
            --fm-accent: var(--fb-color-primary);
            --fm-accent-soft: var(--fb-color-primary-soft);
            --fm-row-hover: rgba(255, 255, 255, .035);
            --fm-row-active: var(--fb-color-primary-soft);
            --fm-sidebar: #101721;
            --fm-toolbar: rgba(22, 30, 42, .96);
            --fm-toolbar-border: rgba(255, 255, 255, .06);
            --fm-sidebar-active-text: #f8fafc;
            --fm-row-active-border: rgba(255, 90, 60, .28);
        }

        [data-file-manager-shell] {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            height: 100%;
            background: var(--fm-bg);
            border: 0;
            border-radius: 0;
            box-shadow: var(--fm-shadow);
            overflow: hidden;
        }

        [data-file-manager-browser] {
            position: relative;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            height: 100%;
            min-height: 0;
            transition: opacity .18s ease, transform .18s ease;
        }

        [data-file-manager-frame] {
            min-height: 0;
            flex: 1 1 auto;
        }

        [data-file-manager-browser].is-loading {
            opacity: .62;
            transform: translateY(2px);
            pointer-events: none;
        }

        [data-file-manager-workspace] {
            display: grid;
            grid-template-columns: minmax(250px, 280px) minmax(0, 1fr);
            width: 100%;
            max-width: 100%;
            min-width: 0;
            height: 100%;
            min-height: 0;
        }

        [data-file-manager-sidebar] {
            display: flex;
            min-height: 0;
            flex-direction: column;
            overflow: hidden;
            background: var(--fm-sidebar);
            border-right: 1px solid var(--fm-border);
        }

        [data-file-manager-sidebar-body] {
            min-height: 0;
            overflow: auto;
            scrollbar-width: thin;
        }

        [data-file-manager-sidebar-icon] {
            width: 2.5rem;
            height: 2.5rem;
            place-items: center;
            background: var(--fm-accent-soft);
            color: var(--fm-accent);
        }

        [data-file-manager-section-label] {
            letter-spacing: .08em;
            font-size: .67rem;
        }

        [data-file-manager-folder-tree] .list-group-item {
            padding-left: calc(.75rem + min(var(--fm-folder-depth, 0), 4) * .8rem);
        }

        [data-file-manager-sidebar-footer] {
            background: color-mix(in srgb, var(--fm-sidebar) 92%, var(--fb-color-surface));
        }

        [data-file-manager-content] {
            position: relative;
            display: flex;
            width: 100%;
            max-width: 100%;
            height: 100%;
            min-height: 0;
            flex-direction: column;
            background: var(--fm-panel);
            overflow: hidden;
            container-type: inline-size;
        }

        [data-file-manager-page] .min-w-0 {
            min-width: 0 !important;
        }

        [data-file-manager-page] .text-truncate {
            min-width: 0;
            max-width: 100%;
        }

        [data-file-manager-toolbar] {
            position: relative;
            z-index: 20;
            flex: 0 0 auto;
            background: var(--fm-toolbar);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid var(--fm-toolbar-border);
            min-width: 0;
            overflow: visible;
        }

        [data-file-manager-toolbar] .btn:not(.btn-icon),
        [data-file-manager-toolbar] .form-control {
            height: 44px;
            min-height: 44px;
            border-radius: 12px;
        }

        [data-file-manager-toolbar] .btn-icon {
            width: 44px;
            height: 44px;
            min-width: 44px;
            border-radius: 11px;
        }

        [data-file-manager-toolbar-actions],
        [data-file-manager-controls],
        [data-file-manager-control-actions] {
            min-width: 0;
        }

        [data-file-manager-control-actions] {
            flex: 0 0 auto;
            flex-wrap: nowrap !important;
        }

        [data-file-manager-control-actions] .btn,
        [data-file-manager-toolbar-actions] .btn {
            white-space: nowrap;
        }

        [data-file-manager-selection-badge] {
            display: inline-flex;
            min-height: 44px;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        [data-file-manager-results] {
            min-height: 0;
            flex: 1 1 auto;
            overflow: auto;
            scrollbar-width: thin;
        }

        [data-file-manager-table] tbody tr {
            cursor: default;
            transition: background-color .15s ease, box-shadow .15s ease;
        }

        [data-file-manager-table] tbody tr:hover {
            background: var(--fm-row-hover);
        }

        [data-file-manager-table] tbody tr.is-selected {
            background: var(--fm-row-active);
            box-shadow: inset 0 0 0 1px var(--fm-row-active-border);
        }

        [data-file-manager-row][draggable="true"] {
            cursor: grab;
        }

        [data-file-manager-row].is-dragging {
            opacity: .55;
        }

        [data-file-manager-row].is-drop-target,
        [data-fm-drop-dir].is-drop-target {
            background: var(--fm-row-active) !important;
            box-shadow: inset 0 0 0 2px rgba(31, 92, 79, .28);
        }

        [data-file-manager-table] td,
        [data-file-manager-table] th {
            vertical-align: middle;
        }

        [data-file-manager-table] {
            width: 100%;
            min-width: 100% !important;
            max-width: 100%;
            table-layout: fixed !important;
        }

        [data-file-manager-table] col:nth-child(1) {
            width: 3.25rem;
        }

        [data-file-manager-table] col:nth-child(3) {
            width: 7rem;
        }

        [data-file-manager-table] col:nth-child(4) {
            width: 5.5rem;
        }

        [data-file-manager-table] col:nth-child(5) {
            width: 10.5rem;
        }

        [data-file-manager-table] col:nth-child(6) {
            width: 7rem;
        }

        [data-file-manager-shell] [data-file-manager-table] th,
        [data-file-manager-shell] [data-file-manager-table] td {
            min-width: 0 !important;
        }

        [data-file-manager-shell] [data-file-manager-table] th:first-child,
        [data-file-manager-shell] [data-file-manager-table] td:first-child {
            width: 3.25rem !important;
        }

        [data-file-manager-shell] [data-file-manager-table] th:nth-child(3),
        [data-file-manager-shell] [data-file-manager-table] td:nth-child(3) {
            width: 7rem !important;
        }

        [data-file-manager-shell] [data-file-manager-table] th:nth-child(4),
        [data-file-manager-shell] [data-file-manager-table] td:nth-child(4) {
            width: 5.5rem !important;
        }

        [data-file-manager-shell] [data-file-manager-table] th:nth-child(5),
        [data-file-manager-shell] [data-file-manager-table] td:nth-child(5) {
            width: 10.5rem !important;
        }

        [data-file-manager-shell] [data-file-manager-table] th:last-child,
        [data-file-manager-shell] [data-file-manager-table] td:last-child {
            width: 7rem !important;
            min-width: 7rem !important;
            padding-right: 1rem !important;
            text-align: right !important;
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

        [data-file-manager-table-wrap] {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            overflow-x: hidden;
            overflow-y: visible !important;
            position: relative;
        }

        [data-file-manager-actions-menu] {
            position: relative;
        }

        [data-file-manager-actions-menu] > .btn {
            width: 2.25rem;
            height: 2.25rem;
            padding: 0;
        }

        [data-file-manager-actions-menu] .dropdown-menu {
            z-index: 1045;
            margin-right: .35rem !important;
        }

        .fm-dropdown-floating {
            position: fixed !important;
            inset: auto !important;
            transform: none !important;
            margin: 0 !important;
            z-index: 1045 !important;
            box-shadow: 0 18px 48px rgba(17, 24, 39, .18);
        }

        [data-file-manager-selection-badge] {
            background: var(--fm-accent-soft);
            color: var(--fm-accent);
        }

        [data-file-manager-breadcrumbs] {
            scrollbar-width: thin;
        }

        [data-file-manager-search-form] {
            max-width: none;
            width: auto;
            min-width: 16rem;
            flex: 1 1 22rem !important;
        }

        [data-file-manager-feedback-wrap]:empty {
            display: none;
        }

        [data-file-manager-feedback-wrap]:not(:empty) {
            position: absolute;
            z-index: 1040;
            top: .5rem;
            left: 50%;
            width: min(640px, calc(100% - 2rem));
            padding: 0 !important;
            transform: translateX(-50%);
        }

        [data-file-manager-delete-selected].d-none {
            display: none !important;
        }

        [data-fm-pagination] {
            max-width: 100%;
        }

        [data-fm-pagination] .pagination {
            margin-bottom: 0;
        }

        [data-file-preview-modal] .modal-dialog {
            max-width: min(1120px, calc(100vw - 1.5rem));
        }

        [data-file-preview-modal] .modal-content {
            max-height: calc(100dvh - var(--fb-modal-mobile-top, .75rem) - var(--fb-modal-mobile-bottom, .75rem));
        }

        [data-file-preview-modal] .modal-body {
            min-width: 0;
            overflow: auto;
        }

        [data-file-preview-stage] {
            min-height: clamp(220px, 42dvh, 360px);
            max-height: min(72dvh, 760px);
            min-width: 0;
            overflow: hidden;
        }

        [data-file-preview-image] {
            display: block;
            width: auto;
            height: auto;
            max-width: 100%;
            max-height: min(68dvh, 720px);
            object-fit: contain;
        }

        [data-file-manager-sidebar] .list-group-item {
            border: 0;
            background: transparent;
            border-radius: 10px;
            margin-bottom: .2rem;
            padding-top: .7rem;
            padding-bottom: .7rem;
            min-width: 0;
            color: var(--fb-color-text-secondary);
        }

        [data-file-manager-sidebar] .list-group-item:hover {
            background: var(--fm-row-hover);
        }

        [data-file-manager-sidebar] .list-group-item.active {
            background: var(--fm-row-active);
            color: var(--fm-sidebar-active-text);
            box-shadow: inset 3px 0 0 var(--fm-accent);
        }

        [data-file-manager-sidebar] .list-group-item i {
            width: 1.1rem;
            color: var(--fb-color-text-tertiary);
            text-align: center;
        }

        [data-file-manager-sidebar] .list-group-item.active i,
        [data-file-manager-folder-tree] .list-group-item i {
            color: var(--fm-accent);
        }

        [data-file-manager-name-cell] img,
        [data-file-manager-name-cell] .rounded-4.border {
            width: 46px !important;
            height: 46px !important;
            border-radius: 12px !important;
        }

        [data-file-manager-upload-drop] {
            display: block;
            min-height: 124px;
            padding: 1.25rem;
            border: 1px dashed var(--fb-color-border-strong);
            border-radius: 16px;
            background: color-mix(in srgb, var(--fb-color-surface-secondary) 70%, transparent);
            color: var(--fb-color-text);
            text-align: center;
            transition: border-color .15s ease, background .15s ease, transform .15s ease;
        }

        [data-file-manager-upload-drop]:hover,
        [data-file-manager-upload-drop]:focus-visible,
        [data-file-manager-upload-drop].is-dragover {
            border-color: var(--fm-accent);
            background: var(--fm-accent-soft);
        }

        [data-file-manager-upload-drop].is-dragover {
            transform: translateY(-2px);
        }

        [data-file-manager-upload-drop-icon] {
            width: 2.5rem;
            height: 2.5rem;
            background: var(--fm-accent-soft);
            color: var(--fm-accent);
        }

        [data-file-manager-view][aria-pressed="true"] {
            border-color: var(--fm-accent) !important;
            background: var(--fm-accent-soft) !important;
            color: var(--fm-accent) !important;
        }

        @container (max-width: 60rem) {
            [data-file-manager-controls] {
                flex-direction: column !important;
                align-items: stretch !important;
            }

            [data-file-manager-search-form] {
                width: 100%;
                min-width: 0;
                flex-basis: auto !important;
            }

            [data-file-manager-control-actions] {
                width: 100%;
                flex-wrap: wrap !important;
            }
        }

        @media (min-width: 768px) {
            [data-file-manager-page][data-fm-view-mode="grid"] [data-file-manager-table-wrap] {
                display: none !important;
            }

            [data-file-manager-page][data-fm-view-mode="grid"] [data-admin-mobile-table-cards] {
                display: grid !important;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: .875rem;
            }

            [data-file-manager-page][data-fm-view-mode="grid"] .admin-mobile-table-card {
                min-width: 0;
                border-radius: 16px !important;
                box-shadow: none !important;
                transition: border-color .15s ease, transform .15s ease;
            }

            [data-file-manager-page][data-fm-view-mode="grid"] .admin-mobile-table-card:hover {
                border-color: var(--fb-color-border-strong);
                transform: translateY(-1px);
            }
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

        [data-bs-theme="dark"] [data-file-manager-page] .modal-content,
        [data-bs-theme="dark"] [data-file-manager-page] .input-group-text {
            background-color: #18212a;
            color: var(--bs-body-color);
        }

        [data-bs-theme="dark"] [data-file-manager-page] .modal-header,
        [data-bs-theme="dark"] [data-file-manager-page] .modal-footer,
        [data-bs-theme="dark"] [data-file-manager-page] .input-group-text {
            border-color: rgba(255, 255, 255, .08);
        }

        @media (max-width: 1199.98px) {
            [data-file-manager-workspace] {
                grid-template-columns: 1fr;
                height: auto;
            }

            [data-file-manager-sidebar] {
                display: block;
                min-height: 0;
                overflow: visible;
                border-right: 0;
                border-bottom: 1px solid var(--fm-border);
            }

            [data-file-manager-sidebar-body] {
                overflow: visible;
            }

            [data-file-manager-quick-links],
            [data-file-manager-folder-tree] {
                display: flex;
                gap: .35rem;
                margin-bottom: .75rem !important;
                overflow-x: auto;
                scrollbar-width: thin;
            }

            [data-file-manager-quick-links] .list-group-item,
            [data-file-manager-folder-tree] .list-group-item {
                width: auto;
                flex: 0 0 auto;
                margin: 0;
                padding: .65rem .8rem !important;
            }

            [data-file-manager-sidebar-footer] {
                display: none;
            }
        }

        @media (max-width: 767.98px) {
            [data-file-manager-page] {
                overflow-x: clip;
            }

            [data-file-manager-shell] {
                width: 100%;
                max-width: 100%;
                height: auto;
                border-radius: 0;
            }

            [data-file-manager-browser],
            [data-file-manager-workspace] {
                height: auto;
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
                display: flex;
                flex-wrap: nowrap;
                min-width: 0;
                position: relative;
                z-index: 1021;
            }

            [data-file-manager-toolbar-actions] .btn-group {
                display: none;
            }

            [data-file-manager-toolbar-actions] > .btn {
                min-width: 0;
                flex: 1 1 0;
                justify-content: center;
            }

            [data-file-manager-control-actions] {
                display: grid !important;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                width: 100%;
            }

            [data-file-manager-control-actions] > .dropdown,
            [data-file-manager-control-actions] > .dropdown > .btn {
                width: 100%;
            }

            [data-file-manager-control-actions] > [data-file-manager-selection-badge] {
                display: inline-flex;
                min-height: 42px;
                align-items: center;
                justify-content: center;
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

            [data-file-manager-search-form] {
                max-width: none;
                min-width: 0;
            }

            [data-file-manager-table-wrap] {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                overflow-x: auto;
                overflow-y: visible !important;
                -webkit-overflow-scrolling: touch;
            }

            [data-file-manager-table] {
                min-width: 0;
            }

            [data-file-manager-table] th,
            [data-file-manager-table] td {
                padding: .65rem .55rem;
                font-size: .86rem;
            }

            [data-file-manager-table] .btn {
                font-size: .8rem;
            }

            [data-file-manager-page] .dropdown-menu {
                max-width: calc(100vw - 1.5rem);
            }

            [data-file-manager-page] .dropdown-item {
                white-space: normal;
            }

            .fm-dropdown-floating {
                width: min(14.5rem, calc(100vw - 1.5rem)) !important;
                max-width: calc(100vw - 1.5rem) !important;
                min-width: 0 !important;
                max-height: calc(100dvh - 1.5rem);
                overflow-y: auto;
                border-radius: 1.1rem !important;
            }

            [data-file-manager-actions-menu] > button {
                min-width: 2.5rem;
                padding-inline: .7rem;
            }

            [data-file-select] {
                min-height: 2.5rem;
                white-space: nowrap;
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
                position: static !important;
                width: auto !important;
                padding: 1rem 1rem 0 !important;
                transform: none !important;
            }

            [data-file-manager-sidebar] {
                display: block;
            }

            [data-file-manager-sidebar-head] {
                margin-bottom: 1rem !important;
            }

            [data-file-manager-content] {
                height: auto;
                min-height: 0;
                overflow: visible;
            }

            [data-file-manager-results] {
                overflow: visible;
            }

            [data-file-manager-delete-selected] {
                width: 100%;
                justify-content: center;
            }

            [data-fm-pagination] {
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                padding-bottom: .15rem;
            }

            [data-fm-pagination] .pagination {
                flex-wrap: nowrap;
                justify-content: flex-start;
            }

            [data-fm-pagination] .page-link {
                min-width: 2.5rem;
                text-align: center;
            }

            [data-file-preview-modal] .modal-dialog {
                margin-inline: .75rem;
                max-width: calc(100vw - 1.5rem);
            }

            [data-file-preview-stage] {
                min-height: 220px;
                max-height: calc(100dvh - 12rem);
                padding: .75rem !important;
            }

            [data-file-preview-image] {
                max-height: calc(100dvh - 13.5rem);
            }
        }
    </style>

<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_files_heading'),
    'subtitle' => return_translation('admin_files_subtitle'),
    'actions' => '',
    'content_class' => 'fb-content--edge-workspace',
    'show_header' => false,
]) ?>

    <div
        class="position-relative"
        data-file-manager-page
        data-file-manager-shell
        data-fm-view-mode="list"
        data-admin-table
        data-fm-selection-required="<?= htmlSC(return_translation('admin_files_selection_required')) ?>"
        data-fm-rename-single="<?= htmlSC(return_translation('admin_files_rename_single_required')) ?>"
        data-fm-open-single="<?= htmlSC(return_translation('admin_files_open_single_required')) ?>"
        data-fm-download-single="<?= htmlSC(return_translation('admin_files_download_single_required')) ?>"
        data-fm-download-unavailable="<?= htmlSC(return_translation('admin_files_download_unavailable')) ?>"
        data-fm-upload-error="<?= htmlSC(return_translation('admin_files_upload_error')) ?>"
        data-fm-delete-confirm="<?= htmlSC(return_translation('admin_files_delete_selected_confirm')) ?>"
        data-fm-delete-protected="<?= htmlSC(return_translation('admin_files_folder_delete_protected')) ?>"
        data-fm-rename-protected="<?= htmlSC(return_translation('admin_files_folder_rename_protected')) ?>"
        data-fm-copy-title="<?= htmlSC(return_translation('admin_files_transfer_title_copy')) ?>"
        data-fm-move-title="<?= htmlSC(return_translation('admin_files_transfer_title_move')) ?>"
        data-fm-copy-submit="<?= htmlSC(return_translation('admin_files_transfer_submit_copy')) ?>"
        data-fm-move-submit="<?= htmlSC(return_translation('admin_files_transfer_submit_move')) ?>"
        data-fm-transfer-protected="<?= htmlSC(return_translation('admin_files_folder_move_protected')) ?>"
    >
    <?php if (!empty($picker_mode)): ?>
        <div class="small text-body-secondary border-bottom px-3 py-2"><?= print_translation('admin_files_picker_hint') ?></div>
    <?php endif; ?>

    <div class="position-relative" data-file-manager-frame>
        <div data-file-manager-browser>
            <?= view()->renderPartial('admin/file_manager_browser', [
                'manager' => $manager ?? [],
                'picker_mode' => $picker_mode ?? false,
                'picker_field' => $picker_field ?? '',
            ]) ?>
        </div>
    </div>
    </div>
<?= view()->renderPartial('admin/shell_close') ?>
