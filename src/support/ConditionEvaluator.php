<?php

declare(strict_types=1);

namespace youvision\store\admin\support;

/**
 * 条件表达式解析器
 *
 * 支持的语法：
 * - 比较运算符：>、>=、<、<=、=、==、!=、<>
 * - and / or 组合，and 优先级高于 or
 * - 值支持数字、单引号或双引号字符串、true、false、null
 * - 仅写 {字段} 表示该字段有值即通过
 *
 * 引号内的 and / or 不会被当作逻辑运算符。解析失败时一律判定为不通过。
 */
final class ConditionEvaluator
{
    /**
     * @var array<int, array<int, mixed>>
     */
    private array $tokens = [];

    private int $pos = 0;

    /**
     * @param array<string, mixed> $user
     */
    public function evaluate(string $condition, array $user): bool
    {
        $tokens = $this->tokenize($condition);

        if ($tokens === null || $tokens === []) {
            return false;
        }

        $this->tokens = $tokens;
        $this->pos = 0;

        $result = $this->parseOr($user);

        return $result === true && $this->pos === count($tokens);
    }

    /**
     * 提取条件表达式引用的字段名，用于生成持久缓存签名
     *
     * @return array<int, string>
     */
    public function fields(string $condition): array
    {
        if (trim($condition) === '') {
            return [];
        }

        preg_match_all('/\{([A-Za-z_\x{4e00}-\x{9fa5}][A-Za-z0-9_\x{4e00}-\x{9fa5}]*)\}/u', $condition, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * @return array<int, array<int, mixed>>|null
     */
    private function tokenize(string $condition): ?array
    {
        $tokens = [];
        $length = strlen($condition);
        $i = 0;

        while ($i < $length) {
            $char = $condition[$i];

            if (ctype_space($char)) {
                $i++;
                continue;
            }

            if ($char === '{') {
                $end = strpos($condition, '}', $i + 1);

                if ($end === false) {
                    return null;
                }

                $field = trim(substr($condition, $i + 1, $end - $i - 1));

                if (!preg_match('/^[A-Za-z_\x{4e00}-\x{9fa5}][A-Za-z0-9_\x{4e00}-\x{9fa5}]*$/u', $field)) {
                    return null;
                }

                $tokens[] = ['field', $field];
                $i = $end + 1;
                continue;
            }

            if ($char === "'" || $char === '"') {
                $quoted = $this->readQuoted($condition, $i);

                if ($quoted === null) {
                    return null;
                }

                $tokens[] = ['value', $quoted[0], true];
                $i = $quoted[1];
                continue;
            }

            $operator = $this->readOperator($condition, $i);

            if ($operator !== null) {
                $tokens[] = ['op', $operator];
                $i += strlen($operator);
                continue;
            }

            if (!preg_match('/^[^\s]+/', substr($condition, $i), $matches)) {
                return null;
            }

            $word = $matches[0];

            if (strcasecmp($word, 'and') === 0) {
                $tokens[] = ['and'];
            } elseif (strcasecmp($word, 'or') === 0) {
                $tokens[] = ['or'];
            } else {
                $tokens[] = ['value', $word, false];
            }

            $i += strlen($word);
        }

        return $tokens;
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private function readQuoted(string $condition, int $start): ?array
    {
        $quote = $condition[$start];
        $length = strlen($condition);
        $value = '';
        $i = $start + 1;

        while ($i < $length) {
            $char = $condition[$i];

            if ($char === '\\' && $i + 1 < $length) {
                $value .= $condition[$i + 1];
                $i += 2;
                continue;
            }

            if ($char === $quote) {
                return [$value, $i + 1];
            }

            $value .= $char;
            $i++;
        }

        return null;
    }

    private function readOperator(string $condition, int $offset): ?string
    {
        foreach (['>=', '<=', '<>', '!=', '==', '=', '>', '<'] as $operator) {
            if (substr_compare($condition, $operator, $offset, strlen($operator)) === 0) {
                return $operator;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function parseOr(array $user): ?bool
    {
        $left = $this->parseAnd($user);

        if ($left === null) {
            return null;
        }

        while ($this->peek() === 'or') {
            $this->pos++;
            $right = $this->parseAnd($user);

            if ($right === null) {
                return null;
            }

            $left = $left || $right;
        }

        return $left;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function parseAnd(array $user): ?bool
    {
        $left = $this->parseAtom($user);

        if ($left === null) {
            return null;
        }

        while ($this->peek() === 'and') {
            $this->pos++;
            $right = $this->parseAtom($user);

            if ($right === null) {
                return null;
            }

            $left = $left && $right;
        }

        return $left;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function parseAtom(array $user): ?bool
    {
        $token = $this->tokens[$this->pos] ?? null;

        if ($token === null || $token[0] !== 'field') {
            return null;
        }

        $this->pos++;
        $field = (string) $token[1];
        $next = $this->tokens[$this->pos] ?? null;

        if ($next !== null && $next[0] === 'op') {
            $this->pos++;
            $valueToken = $this->tokens[$this->pos] ?? null;

            if ($valueToken === null || $valueToken[0] !== 'value') {
                return null;
            }

            $this->pos++;

            if (!array_key_exists($field, $user)) {
                return false;
            }

            return $this->compare(
                $user[$field],
                (string) $next[1],
                $this->parseValue((string) $valueToken[1], (bool) ($valueToken[2] ?? false))
            );
        }

        return !empty($user[$field] ?? null);
    }

    private function peek(): ?string
    {
        $token = $this->tokens[$this->pos] ?? null;

        return $token === null ? null : (string) $token[0];
    }

    /**
     * @param mixed $left
     * @param mixed $right
     */
    private function compare($left, string $operator, $right): bool
    {
        if ($operator === '=' || $operator === '==') {
            return $left == $right;
        }

        if ($operator === '!=' || $operator === '<>') {
            return $left != $right;
        }

        if (is_numeric($left) && is_numeric($right)) {
            $left = (float) $left;
            $right = (float) $right;
        } else {
            $left = (string) $left;
            $right = (string) $right;
        }

        return match ($operator) {
            '>' => $left > $right,
            '>=' => $left >= $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            default => false,
        };
    }

    /**
     * @return mixed
     */
    private function parseValue(string $value, bool $quoted)
    {
        if ($quoted) {
            return $value;
        }

        $lower = strtolower($value);

        if ($lower === 'true') {
            return true;
        }

        if ($lower === 'false') {
            return false;
        }

        if ($lower === 'null') {
            return null;
        }

        if (preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }
}