<?php
$contactRequest = (array)($contact_request ?? []);
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
                <h2 class="h5 mb-3"><?= print_translation('admin_support_reply_original') ?></h2>
                <dl class="row small mb-3">
                    <dt class="col-4"><?= print_translation('admin_contacts_col_name') ?></dt><dd class="col-8"><?= htmlSC((string)($contactRequest['name'] ?? '')) ?></dd>
                    <dt class="col-4"><?= print_translation('admin_contacts_col_email') ?></dt><dd class="col-8 text-break"><?= htmlSC((string)($contactRequest['email'] ?? '')) ?></dd>
                    <dt class="col-4"><?= print_translation('admin_contacts_col_subject') ?></dt><dd class="col-8"><?= htmlSC((string)($contactRequest['subject'] ?? '')) ?></dd>
                </dl>
                <div class="bg-body-tertiary rounded-4 p-3 text-break"><?= nl2br(htmlSC((string)($contactRequest['message'] ?? ''))) ?></div>
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
