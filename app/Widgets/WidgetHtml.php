<?php

namespace App\Widgets;

final class WidgetHtml
{
    public static function sanitize(string $html): string
    {
        $html = sanitize_content_html($html);
        if ($html === '' || !class_exists(\DOMDocument::class)) return $html;
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><div id="fbl-widget-html-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            foreach (iterator_to_array((new \DOMXPath($document))->query('//processing-instruction()')) as $instruction) {
                $instruction->parentNode?->removeChild($instruction);
            }
            $allowed = ['div', 'section', 'p', 'span', 'h2', 'h3', 'h4', 'h5', 'h6', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 's', 'small', 'ul', 'ol', 'li', 'blockquote', 'a', 'img', 'figure', 'figcaption', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'pre', 'code'];
            foreach (array_reverse(iterator_to_array($document->getElementsByTagName('*'))) as $node) {
                if ($node->getAttribute('id') === 'fbl-widget-html-root' && $node->parentNode === $document) continue;
                if (!in_array(strtolower($node->tagName), $allowed, true)) {
                    $node->parentNode?->removeChild($node);
                    continue;
                }
                foreach (iterator_to_array($node->attributes) as $attribute) {
                    if (!in_array(strtolower($attribute->name), ['class', 'title', 'href', 'src', 'alt', 'width', 'height', 'target', 'rel', 'colspan', 'rowspan'], true)) $node->removeAttributeNode($attribute);
                }
                if ($node->tagName === 'a') {
                    $node->setAttribute('rel', 'noopener noreferrer');
                    if ($node->getAttribute('target') !== '_blank') $node->removeAttribute('target');
                }
                if ($node->tagName === 'img') {
                    $node->setAttribute('loading', 'lazy');
                    $node->setAttribute('decoding', 'async');
                    $node->setAttribute('class', trim($node->getAttribute('class') . ' img-fluid'));
                }
            }
            $root = $document->getElementById('fbl-widget-html-root');
            $result = '';
            foreach ($root?->childNodes ?? [] as $child) $result .= $document->saveHTML($child);
            return trim($result);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
