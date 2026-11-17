<?php

declare(strict_types=1);

namespace youvision\store\auth\support;

/**
 * 将 SQL 文件拆分为可独立执行的语句
 *
 * 相比按分号直接 explode，能够正确处理字符串、反引号标识符、
 * 行注释与块注释中的分号。
 */
final class SqlSplitter
{
    /**
     * @return array<int, string>
     */
    public static function split(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $quote = '';
        $inLineComment = false;
        $inBlockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($inLineComment) {
                if ($char === "\n" || $char === "\r") {
                    $inLineComment = false;
                    $buffer .= ' ';
                }
                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }
                continue;
            }

            if ($quote !== '') {
                $buffer .= $char;

                if ($char === '\\' && $quote !== '`' && $i + 1 < $length) {
                    $buffer .= $sql[++$i];
                    continue;
                }

                if ($char === $quote) {
                    if ($quote !== '`' && $next === $quote) {
                        $buffer .= $sql[++$i];
                        continue;
                    }
                    $quote = '';
                }

                continue;
            }

            if ($char === '-' && $next === '-') {
                $after = $i + 2 < $length ? $sql[$i + 2] : '';
                if ($after === '' || ctype_space($after)) {
                    $inLineComment = true;
                    $i++;
                    continue;
                }
            }

            if ($char === '#') {
                $inLineComment = true;
                continue;
            }

            if ($char === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }

            if ($char === '\'' || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }
}