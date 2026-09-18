<?php

namespace App\Services;

/**
 * Adaptive, session-bound CAPTCHA for public forms.
 *
 * Low-risk visitors pass invisibly. Suspicious submissions receive a
 * one-time interactive challenge stored only in the server-side session.
 */
final class FireCaptchaService
{
    private const SESSION_ROOT = 'firecaptcha';
    private const RISK_THRESHOLD = 40;
    private const PAGE_TTL = 7200;
    private const CHALLENGE_TTL = 180;

    public function prepare(string $scope): array
    {
        $scope = $this->normalizeScope($scope);
        $challenge = $this->getChallenge($scope);

        if (is_array($challenge) && (int)($challenge['expires_at'] ?? 0) < time()) {
            $challenge = $this->issueChallenge($scope);
        }

        $page = [
            'nonce' => bin2hex(random_bytes(18)),
            'issued_at' => time(),
        ];
        session()->set($this->key($scope, 'page'), $page);

        return [
            'nonce' => $page['nonce'],
            'required' => is_array($challenge),
            'challenge' => is_array($challenge) ? $this->publicChallenge($challenge) : null,
        ];
    }

    public function verify(string $scope, array $payload): array
    {
        $scope = $this->normalizeScope($scope);
        $challenge = $this->getChallenge($scope);

        if (is_array($challenge)) {
            if ((int)($challenge['expires_at'] ?? 0) < time()) {
                $this->issueChallenge($scope);
                $this->consumePageNonce($scope);

                return [
                    'passed' => false,
                    'required' => true,
                    'reason' => 'expired',
                ];
            }

            $challengeId = trim((string)($payload['firecaptcha_challenge'] ?? ''));
            $answer = trim((string)($payload['firecaptcha_answer'] ?? ''));
            $expectedId = (string)($challenge['id'] ?? '');
            $expectedAnswer = (string)($challenge['correct_token'] ?? '');

            if (
                $challengeId !== ''
                && $answer !== ''
                && $expectedId !== ''
                && $expectedAnswer !== ''
                && hash_equals($expectedId, $challengeId)
                && hash_equals($expectedAnswer, $answer)
            ) {
                session()->remove($this->key($scope, 'challenge'));
                $this->consumePageNonce($scope);

                return [
                    'passed' => true,
                    'required' => false,
                    'reason' => 'challenge_passed',
                ];
            }

            // FIRECAPTCHA_SHUFFLE_PATCH
            // Every failed attempt creates a completely new challenge:
            // new ID, new one-time tokens and a new random icon order.
            $this->issueChallenge($scope);

            $this->consumePageNonce($scope);

            return [
                'passed' => false,
                'required' => true,
                'reason' => 'challenge_failed',
            ];
        }

        $riskScore = $this->calculateRiskScore($scope, $payload);
        $this->consumePageNonce($scope);

        if ($riskScore >= self::RISK_THRESHOLD) {
            $this->issueChallenge($scope);

            return [
                'passed' => false,
                'required' => true,
                'reason' => 'risk',
            ];
        }

        return [
            'passed' => true,
            'required' => false,
            'reason' => 'low_risk',
        ];
    }

    private function calculateRiskScore(string $scope, array $payload): int
    {
        if (trim((string)($payload['firecaptcha_website'] ?? '')) !== '') {
            return 100;
        }

        $score = 0;
        $page = session()->get($this->key($scope, 'page'), []);
        $submittedNonce = trim((string)($payload['firecaptcha_nonce'] ?? ''));

        if (
            !is_array($page)
            || !isset($page['nonce'], $page['issued_at'])
            || $submittedNonce === ''
            || !hash_equals((string)$page['nonce'], $submittedNonce)
        ) {
            $score += 60;
        } else {
            $elapsed = time() - (int)$page['issued_at'];
            if ($elapsed <= 1) {
                $score += 35;
            } elseif ($elapsed > self::PAGE_TTL) {
                $score += 25;
            }
        }

        if ((string)($payload['firecaptcha_js'] ?? '') !== '1') {
            $score += 25;
        }

        $interactions = (int)($payload['firecaptcha_interactions'] ?? 0);
        if ($interactions <= 0) {
            $score += 20;
        } elseif ($interactions > 5000) {
            $score += 10;
        }

        $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (strlen($userAgent) < 8) {
            $score += 25;
        }

        return min(100, max(0, $score));
    }

    private function issueChallenge(string $scope): array
    {
        $items = [
            ['symbol' => '🔥', 'correct' => true],
            ['symbol' => '💧', 'correct' => false],
            ['symbol' => '🪨', 'correct' => false],
            ['symbol' => '❄️', 'correct' => false],
            ['symbol' => '🍃', 'correct' => false],
        ];

        $publicItems = [];
        $correctToken = '';

        foreach ($items as $item) {
            $token = bin2hex(random_bytes(12));
            $publicItems[] = [
                'token' => $token,
                'symbol' => $item['symbol'],
            ];

            if ($item['correct']) {
                $correctToken = $token;
            }
        }

        shuffle($publicItems);

        $challenge = [
            'id' => bin2hex(random_bytes(16)),
            'created_at' => time(),
            'expires_at' => time() + self::CHALLENGE_TTL,
            'correct_token' => $correctToken,
            'items' => $publicItems,
            'attempts' => 0,
        ];

        session()->set($this->key($scope, 'challenge'), $challenge);

        return $challenge;
    }

    private function getChallenge(string $scope): ?array
    {
        $challenge = session()->get($this->key($scope, 'challenge'));

        return is_array($challenge) ? $challenge : null;
    }

    private function publicChallenge(array $challenge): array
    {
        return [
            'id' => (string)($challenge['id'] ?? ''),
            'items' => array_values((array)($challenge['items'] ?? [])),
            'expires_at' => (int)($challenge['expires_at'] ?? 0),
        ];
    }

    private function consumePageNonce(string $scope): void
    {
        session()->remove($this->key($scope, 'page'));
    }

    private function key(string $scope, string $part): string
    {
        return self::SESSION_ROOT . '.' . $scope . '.' . $part;
    }

    private function normalizeScope(string $scope): string
    {
        $scope = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower(trim($scope))) ?? '';
        $scope = trim($scope, '-_');

        return $scope !== '' ? $scope : 'default';
    }
}
