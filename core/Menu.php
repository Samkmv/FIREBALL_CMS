<?php

namespace FBL;

class Menu
{
    protected static array $adminItems = [];

    public static function register(array $item): void
    {
        self::$adminItems[] = $item;
    }

    public static function adminItems(): array
    {
        return self::$adminItems;
    }

    public static function clear(): void
    {
        self::$adminItems = [];
    }
}
