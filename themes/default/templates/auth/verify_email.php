<?php
// Retain normal theme override resolution; the shared form is also the fallback.
echo view()->renderPartial('auth/verify_email', [
    'token' => $token ?? '',
    'verification_email' => $verification_email ?? '',
    'verification_code_ready' => $verification_code_ready ?? false,
    'verification_required' => $verification_required ?? true,
]);
