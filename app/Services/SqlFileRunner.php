<?php

namespace App\Services;

use PDO;

final class SqlFileRunner
{
    public function executePdo(PDO $pdo, string $sql, array $params = []): void
    {
        foreach ($this->split($sql) as $statement) {
            $query = $pdo->prepare($statement);
            foreach ($params as $key => $value) {
                $placeholder = ':' . ltrim((string)$key, ':');
                if (str_contains($statement, $placeholder)) {
                    $query->bindValue($placeholder, $value);
                }
            }
            $query->execute();
        }
    }

    public function executeDatabase(string $sql): void
    {
        foreach ($this->split($sql) as $statement) {
            db()->query($statement);
        }
    }

    public function split(string $sql): array
    {
        $sql = $this->stripComments($sql);
        $statements = [];
        $buffer = '';
        $delimiter = ';';
        $quote = null;
        $escaped = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            if ($quote === null && ($i === 0 || $sql[$i - 1] === "\n")) {
                $lineEnd = strpos($sql, "\n", $i);
                $lineEnd = $lineEnd === false ? $length : $lineEnd;
                $line = trim(substr($sql, $i, $lineEnd - $i));
                if (preg_match('/^DELIMITER\s+(\S+)$/i', $line, $matches) === 1) {
                    $delimiter = $matches[1];
                    $i = $lineEnd;
                    continue;
                }
            }

            $char = $sql[$i];
            if ($quote !== null) {
                $buffer .= $char;
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\' && $quote !== '`') {
                    $escaped = true;
                    continue;
                }
                if ($char === $quote) {
                    if ($i + 1 < $length && $sql[$i + 1] === $quote && $quote !== '`') {
                        $buffer .= $sql[++$i];
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($delimiter !== '' && substr($sql, $i, strlen($delimiter)) === $delimiter) {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                $i += strlen($delimiter) - 1;
                continue;
            }

            $buffer .= $char;
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }

    private function stripComments(string $sql): string
    {
        $result = '';
        $quote = null;
        $escaped = false;
        $blockComment = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($blockComment) {
                if ($char === '*' && $next === '/') {
                    $blockComment = false;
                    $i++;
                } elseif ($char === "\n") {
                    $result .= "\n";
                }
                continue;
            }

            if ($quote !== null) {
                $result .= $char;
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\' && $quote !== '`') {
                    $escaped = true;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $result .= $char;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $blockComment = true;
                $i++;
                continue;
            }
            if (($char === '#' || ($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))))) {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                $result .= "\n";
                continue;
            }

            $result .= $char;
        }

        return $result;
    }
}
