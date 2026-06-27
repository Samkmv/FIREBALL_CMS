<?php

namespace FBL\Plugins;

interface PluginInterface
{
    public function install(): void;

    public function uninstall(): void;

    public function activate(): void;

    public function deactivate(): void;

    public function boot(): void;
}
