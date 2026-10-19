<?php
// Retain normal theme override resolution; the shared form is also the fallback.
echo view()->renderPartial('auth/verify_email', ['token' => $token ?? '']);
