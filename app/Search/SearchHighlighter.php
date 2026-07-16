<?php

namespace App\Search;

final class SearchHighlighter
{
    /**
     * Returns escaped HTML; only generated <mark> tags are left unescaped.
     */
    public static function highlight(string $value, array $tokens): string
    {
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $patterns = [];
        foreach ($tokens as $token) {
            $token = SearchNormalizer::normalize((string)$token);
            if ($token === '') {
                continue;
            }
            $quoted = preg_quote($token, '/');
            $patterns[] = str_replace('е', '[её]', $quoted);
        }

        if ($patterns === []) {
            return $escaped;
        }

        $pattern = '/(?<![\p{L}\p{N}])(?:' . implode('|', $patterns) . ')[\p{L}\p{N}]*/iu';
        $highlighted = preg_replace_callback(
            $pattern,
            static fn(array $match): string => '<mark>' . $match[0] . '</mark>',
            $escaped
        );

        return $highlighted ?? $escaped;
    }
}
