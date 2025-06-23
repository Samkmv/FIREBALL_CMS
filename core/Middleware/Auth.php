<?php

namespace FBL\Middleware;

class Auth
{

    public function handle(): void
    {
        if (!check_auth()) {

            session()->setFlash('error', 'You must be logged in to access this page.');

            abort('You must be logged in to access this page.', 403);

            response()->redirect('/login');
        }
    }

}