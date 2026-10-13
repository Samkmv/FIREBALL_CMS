<?php
use Fireball\ReelPlayer\Controllers\PreferencesController;

/** These routes intentionally require a member account, not an administrator. */
$router->get('/music/api/preferences',[PreferencesController::class,'get'])->middleware(['auth']);
$router->post('/music/api/preferences',[PreferencesController::class,'save'])->middleware(['auth']);
