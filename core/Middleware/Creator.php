<?php

namespace FBL\Middleware;

/**
 * Restricts security-sensitive administration operations to the site creator.
 */
final class Creator
{
    public function handle(): void
    {
        if (!check_creator()) {
            abort(return_translation('error_403_message'), 403);
        }
    }
}
