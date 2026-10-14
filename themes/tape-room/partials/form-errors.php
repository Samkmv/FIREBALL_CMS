<?php $formErrors = (array)session()->get('form_errors', []); ?>
<?php if ($formErrors): ?>
<ul class="tr-errors" role="alert">
    <?php foreach ($formErrors as $errors): foreach ((array)$errors as $error): ?>
        <li><?= htmlSC((string)$error) ?></li>
    <?php endforeach; endforeach; ?>
</ul>
<?php endif; ?>
