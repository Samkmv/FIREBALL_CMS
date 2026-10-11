<?php

namespace App\Widgets;

/** Implementations are trusted PHP code; settings are untrusted input. */
interface WidgetInterface
{
    public function defaults(): array;

    /** Fields: text, textarea, html, number, checkbox, select, categories. */
    public function fields(WidgetContext $context): array;

    /** Return normalized settings or throw InvalidArgumentException. */
    public function validate(array $settings, WidgetContext $context): array;

    public function render(array $settings, WidgetContext $context): string;
}
