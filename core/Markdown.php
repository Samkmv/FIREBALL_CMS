<?php

namespace FBL;

/**
 * Small Markdown renderer for bundled admin documentation.
 */
class Markdown
{
    public static function render(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $lines = explode("\n", $markdown);
        $html = [];
        $paragraph = [];
        $listType = null;
        $codeBlock = false;
        $codeLines = [];

        $flushParagraph = static function () use (&$html, &$paragraph): void {
            if (!$paragraph) {
                return;
            }

            $html[] = '<p>' . self::inline(implode(' ', $paragraph)) . '</p>';
            $paragraph = [];
        };

        $flushList = static function () use (&$html, &$listType): void {
            if ($listType !== null) {
                $html[] = '</' . $listType . '>';
                $listType = null;
            }
        };

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '```')) {
                if ($codeBlock) {
                    $html[] = '<pre class="bg-body-tertiary border rounded-4 p-3 overflow-auto"><code>' . htmlSC(implode("\n", $codeLines)) . '</code></pre>';
                    $codeLines = [];
                    $codeBlock = false;
                    continue;
                }

                $flushParagraph();
                $flushList();
                $codeBlock = true;
                continue;
            }

            if ($codeBlock) {
                $codeLines[] = $line;
                continue;
            }

            $trimmed = trim($line);
            if ($trimmed === '') {
                $flushParagraph();
                $flushList();
                continue;
            }

            if (preg_match('/^(#{1,4})\s+(.+)$/', $trimmed, $matches)) {
                $flushParagraph();
                $flushList();
                $level = strlen($matches[1]);
                $text = trim($matches[2]);
                $id = self::slug($text);
                $class = $level === 1 ? 'display-6 fw-bold mb-4' : 'h' . min($level + 1, 6) . ' mt-5 mb-3';
                $html[] = '<h' . $level . ' id="' . htmlSC($id) . '" class="' . $class . '">' . self::inline($text) . '</h' . $level . '>';
                continue;
            }

            if (preg_match('/^-\s+(.+)$/', $trimmed, $matches)) {
                $flushParagraph();
                if ($listType !== 'ul') {
                    $flushList();
                    $listType = 'ul';
                    $html[] = '<ul>';
                }
                $html[] = '<li>' . self::inline($matches[1]) . '</li>';
                continue;
            }

            if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $matches)) {
                $flushParagraph();
                if ($listType !== 'ol') {
                    $flushList();
                    $listType = 'ol';
                    $html[] = '<ol>';
                }
                $html[] = '<li>' . self::inline($matches[1]) . '</li>';
                continue;
            }

            $paragraph[] = $trimmed;
        }

        if ($codeBlock) {
            $html[] = '<pre class="bg-body-tertiary border rounded-4 p-3 overflow-auto"><code>' . htmlSC(implode("\n", $codeLines)) . '</code></pre>';
        }

        $flushParagraph();
        $flushList();

        return implode("\n", $html);
    }

    protected static function inline(string $text): string
    {
        $escaped = htmlSC($text);
        $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2">$1</a>', $escaped) ?? $escaped;

        return $escaped;
    }

    protected static function slug(string $text): string
    {
        $slug = mb_strtolower(strip_tags($text));
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'section';
    }
}
