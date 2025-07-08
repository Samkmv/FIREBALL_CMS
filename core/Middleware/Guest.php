<?php

namespace FBL\Middleware;

class Guest
{

    public function handle(): void
    {
        if (check_auth()) {
            response()->redirect('/dashboard');
        }
    }

}