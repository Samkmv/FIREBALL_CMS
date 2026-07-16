<?php

namespace App\Search;

/**
 * Keeps relevance rules independent from persistence and controllers.
 */
final class SearchScorer
{
    public function score(
        array $row,
        string $query,
        string $normalizedQuery,
        array $queryTokens,
        bool $partialMatching,
        int $minimumPartialLength,
        array $weights
    ): int {
        $titleTokens = SearchNormalizer::tokens((string)($row['normalized_title'] ?? ''), 1);
        $keywordTokens = SearchNormalizer::tokens((string)($row['normalized_keywords'] ?? ''), 1);
        $contentTokens = SearchNormalizer::tokens((string)($row['normalized_text'] ?? ''), 1);
        $allTokens = array_values(array_unique(array_merge($titleTokens, $keywordTokens, $contentTokens)));

        if (!$this->allTokensMatch($queryTokens, $allTokens, $partialMatching, $minimumPartialLength)) {
            return 0;
        }

        $score = 100;
        $rawTitle = trim((string)($row['title'] ?? ''));
        $normalizedTitle = (string)($row['normalized_title'] ?? '');

        if ($rawTitle === trim($query)) {
            $score += 1000;
        }
        if ($normalizedTitle === $normalizedQuery) {
            $score += 900;
        }
        if ($normalizedQuery !== '' && str_starts_with($normalizedTitle, $normalizedQuery)) {
            $score += 700;
        }

        if ($this->matchesInOrder($queryTokens, $titleTokens, $partialMatching, $minimumPartialLength)) {
            $score += 550;
        }
        if ($this->allTokensMatch($queryTokens, $titleTokens, false, $minimumPartialLength)) {
            $score += 450;
        } elseif ($this->allTokensMatch($queryTokens, $titleTokens, $partialMatching, $minimumPartialLength)) {
            $score += 350;
        }

        $score += $this->fieldScore($queryTokens, $titleTokens, (int)($weights['title'] ?? 40), $partialMatching, $minimumPartialLength);
        $score += $this->fieldScore($queryTokens, $keywordTokens, (int)($weights['keywords'] ?? 25), $partialMatching, $minimumPartialLength);
        $score += $this->fieldScore($queryTokens, $contentTokens, (int)($weights['content'] ?? 10), $partialMatching, $minimumPartialLength);
        $score += max(0, min(1000, (int)($row['priority'] ?? 0)));

        return $score;
    }

    private function fieldScore(
        array $queryTokens,
        array $documentTokens,
        int $weight,
        bool $partialMatching,
        int $minimumPartialLength
    ): int {
        $score = 0;
        foreach ($queryTokens as $queryToken) {
            foreach ($documentTokens as $documentToken) {
                if ($queryToken === $documentToken) {
                    $score += $weight * 2;
                    break;
                }
                if ($this->tokenMatches($queryToken, $documentToken, $partialMatching, $minimumPartialLength)) {
                    $score += $weight;
                    break;
                }
            }
        }

        return $score;
    }

    private function allTokensMatch(
        array $queryTokens,
        array $documentTokens,
        bool $partialMatching,
        int $minimumPartialLength
    ): bool {
        if ($queryTokens === []) {
            return false;
        }

        foreach ($queryTokens as $queryToken) {
            $matched = false;
            foreach ($documentTokens as $documentToken) {
                if ($this->tokenMatches($queryToken, $documentToken, $partialMatching, $minimumPartialLength)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    private function matchesInOrder(
        array $queryTokens,
        array $documentTokens,
        bool $partialMatching,
        int $minimumPartialLength
    ): bool {
        if ($queryTokens === []) {
            return false;
        }

        $queryIndex = 0;
        foreach ($documentTokens as $documentToken) {
            if ($this->tokenMatches($queryTokens[$queryIndex], $documentToken, $partialMatching, $minimumPartialLength)) {
                $queryIndex++;
                if ($queryIndex === count($queryTokens)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function tokenMatches(
        string $queryToken,
        string $documentToken,
        bool $partialMatching,
        int $minimumPartialLength
    ): bool {
        if ($queryToken === $documentToken) {
            return true;
        }

        return $partialMatching
            && mb_strlen($queryToken, 'UTF-8') >= $minimumPartialLength
            && str_starts_with($documentToken, $queryToken);
    }
}
