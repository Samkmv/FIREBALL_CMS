<?php $formErrors=(array)session()->get('form_errors',[]); ?>
<?php if($formErrors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0 ps-3"><?php foreach($formErrors as $errors): foreach((array)$errors as $error): ?><li><?= htmlSC((string)$error) ?></li><?php endforeach; endforeach; ?></ul></div><?php endif; ?>
