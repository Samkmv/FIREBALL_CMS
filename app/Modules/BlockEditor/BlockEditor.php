<?php

namespace App\Modules\BlockEditor;

final class BlockEditor
{
    public static function render(array $options): string
    {
        return (new BlockEditorService())->render($options);
    }

    public static function styles(): array
    {
        return BlockEditorService::styleAssets();
    }

    public static function scripts(): array
    {
        return BlockEditorService::scriptAssets();
    }
}
