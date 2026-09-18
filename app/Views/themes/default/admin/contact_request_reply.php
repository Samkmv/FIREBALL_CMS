<?php
$contactRequest = (array)($contact_request ?? []);
$conversation = array_values(array_filter(
    (array)($conversation ?? []),
    'is_array'
));
if ($conversation === [] && $contactRequest !== []) {
    $conversation[] = [
        'sender_type' => 'requester',
        'sender_user_id' => null,
        'sender_name' => (string)($contactRequest['name'] ?? ''),
        'sender_email' => (string)($contactRequest['email'] ?? ''),
        'recipient_email' => '',
        'subject' => (string)($contactRequest['subject'] ?? ''),
        'message' => (string)($contactRequest['message'] ?? ''),
        'created_at' => (string)($contactRequest['created_at'] ?? ''),
    ];
}
$formData = (array)($form_data ?? []);
$replyToken = (string)($reply_token ?? '');
$subject = (string)($formData['subject'] ?? ('Re: ' . (string)($contactRequest['subject'] ?? '')));
$message = (string)($formData['message'] ?? '');
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_support_reply_heading'),
    'subtitle' => return_translation('admin_support_reply_subtitle'),
    'actions' => '',
]) ?>

    <?= view()->renderPartial('admin/support_tabs', ['active' => 'requests']) ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <section class="border rounded-5 p-3 p-md-4 h-100">
                <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                    <h2 class="h5 mb-0"><?= print_translation('admin_support_history_title') ?></h2>
                    <span class="badge text-bg-secondary rounded-pill"><?= count($conversation) ?></span>
                </div>

                <dl class="row small mb-4">
                    <dt class="col-4"><?= print_translation('admin_contacts_col_name') ?></dt>
                    <dd class="col-8"><?= htmlSC((string)($contactRequest['name'] ?? '')) ?></dd>

                    <dt class="col-4"><?= print_translation('admin_contacts_col_email') ?></dt>
                    <dd class="col-8 text-break">
                        <a href="mailto:<?= htmlSC((string)($contactRequest['email'] ?? '')) ?>">
                            <?= htmlSC((string)($contactRequest['email'] ?? '')) ?>
                        </a>
                    </dd>

                    <dt class="col-4"><?= print_translation('admin_contacts_col_subject') ?></dt>
                    <dd class="col-8"><?= htmlSC((string)($contactRequest['subject'] ?? '')) ?></dd>
                </dl>

                <?php if ($conversation !== []): ?>
                    <div class="vstack gap-3">
                        <?php foreach ($conversation as $historyItem): ?>
                            <?php
                            if (!is_array($historyItem)) {
                                continue;
                            }
                            $isAdminMessage = (string)($historyItem['sender_type'] ?? '') === 'admin';
                            $historySender = trim((string)($historyItem['sender_name'] ?? ''));
                            if ($historySender === '') {
                                $historySender = $isAdminMessage
                                    ? return_translation('admin_support_history_support')
                                    : return_translation('admin_support_history_requester');
                            }
                            $historyTime = strtotime((string)($historyItem['created_at'] ?? '')) ?: time();
                            $historySubject = trim((string)($historyItem['subject'] ?? ''));
                            ?>
                            <article class="border rounded-4 p-3 <?= $isAdminMessage ? 'ms-md-4 bg-body-secondary' : 'me-md-4 bg-body-tertiary' ?>">
                                <div class="d-flex align-items-start justify-content-between gap-3 mb-2">
                                    <div class="d-flex align-items-center gap-2 min-w-0">
                                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-body border flex-shrink-0" style="width: 2rem; height: 2rem;">
                                            <i class="<?= $isAdminMessage ? 'ci-send' : 'ci-user' ?>" aria-hidden="true"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <strong class="d-block text-truncate"><?= htmlSC($historySender) ?></strong>
                                            <span class="small text-body-secondary">
                                                <?= htmlSC($isAdminMessage
                                                    ? return_translation('admin_support_history_support')
                                                    : return_translation('admin_support_history_requester')) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <time class="small text-body-secondary text-nowrap" datetime="<?= htmlSC((string)($historyItem['created_at'] ?? '')) ?>">
                                        <?= htmlSC(date('d.m.Y H:i', $historyTime)) ?>
                                    </time>
                                </div>

                                <?php if ($historySubject !== ''): ?>
                                    <div class="small fw-semibold mb-2"><?= htmlSC($historySubject) ?></div>
                                <?php endif; ?>

                                <div class="small lh-base text-break"><?= nl2br(htmlSC((string)($historyItem['message'] ?? ''))) ?></div>

                                <?php if ($isAdminMessage && trim((string)($historyItem['recipient_email'] ?? '')) !== ''): ?>
                                    <div class="small text-body-secondary mt-2">
                                        <i class="ci-mail me-1" aria-hidden="true"></i>
                                        <?= htmlSC((string)$historyItem['recipient_email']) ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-body-tertiary rounded-4 p-3 text-body-secondary">
                        <?= print_translation('admin_support_history_empty') ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <div class="col-lg-7">
            <form class="border rounded-5 p-3 p-md-4" method="post" action="<?= base_href('/admin/support/requests/reply/' . (int)($contactRequest['id'] ?? 0)) ?>" data-support-reply-form>
                <?= get_csrf_field() ?>
                <input type="hidden" name="reply_token" value="<?= htmlSC($replyToken) ?>">
                <div class="mb-3">
                    <label class="form-label"><?= print_translation('admin_support_reply_recipient') ?></label>
                    <input class="form-control" value="<?= htmlSC((string)($contactRequest['email'] ?? '')) ?>" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= print_translation('admin_support_reply_subject') ?></label>
                    <input class="form-control <?= get_validation_class('subject') ?>" name="subject" value="<?= htmlSC($subject) ?>" maxlength="190" required>
                    <?= get_errors('subject') ?>
                </div>
                <div class="mb-4">
                    <label class="form-label"><?= print_translation('admin_support_reply_message') ?></label>
                    <textarea class="form-control <?= get_validation_class('message') ?>" name="message" rows="12" maxlength="20000" required><?= htmlSC($message) ?></textarea>
                    <?= get_errors('message') ?>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-dark rounded-pill" type="submit" data-support-reply-submit><i class="ci-send me-2"></i><?= print_translation('admin_support_reply_send') ?></button>
                    <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/admin/support/requests') ?>"><?= print_translation('admin_btn_cancel') ?></a>
                </div>
            </form>
        </div>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
