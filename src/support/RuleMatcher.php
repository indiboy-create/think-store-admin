<?php

declare(strict_types=1);

namespace youvision\store\admin\support;

/**
 * 规则名称与 URL 参数匹配器
 */
final class RuleMatcher
{
    /**
     * @param string|array<int, string> $name
     * @return array<int, string>
     */
    public function normalizeNames(string|array $name): array
    {
        $names = is_string($name) ? explode(',', $name) : $name;
        $names = array_map(static fn ($item): string => strtolower(trim((string) $item)), $names);

        return array_values(array_filter($names, static fn (string $item): bool => $item !== ''));
    }

    /**
     * @param array<int, string>    $rules   用户拥有的规则列表
     * @param array<int, string>    $names   待校验的规则名称
     * @param array<string, mixed>  $params  当前请求参数
     * @param string                $mode    url 表示同时校验规则中的请求参数
     * @param string                $relation or 满足其一即通过，and 需全部满足
     */
    public function matches(
        array $rules,
        array $names,
        array $params = [],
        string $mode = 'url',
        string $relation = 'or',
        bool $lowercase = true
    ): bool {
        if ($names === []) {
            return false;
        }

        $requestParams = $lowercase ? $this->lowercaseParams($params) : $params;
        $passed = [];

        foreach ($rules as $rule) {
            if (!is_string($rule)) {
                continue;
            }

            $rule = trim($rule);

            if ($rule === '') {
                continue;
            }

            $node = $rule;
            $query = '';
            $queryPosition = strpos($rule, '?');

            if ($queryPosition !== false) {
                $node = substr($rule, 0, $queryPosition);
                $query = substr($rule, $queryPosition + 1);
            }

            $node = strtolower($node);

            if (!in_array($node, $names, true)) {
                continue;
            }

            if ($mode === 'url' && $query !== '') {
                parse_str($query, $param);
                $param = $lowercase ? $this->lowercaseParams($param) : $param;

                if (!$this->contains($requestParams, $param)) {
                    continue;
                }
            }

            $passed[$node] = true;
        }

        if (strtolower(trim($relation)) === 'and') {
            return array_diff($names, array_keys($passed)) === [];
        }

        if (strtolower(trim($relation)) === 'or') {
            return $passed !== [];
        }

        return false;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function lowercaseParams(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $key = strtolower((string) $key);

            if (is_array($value)) {
                $result[$key] = $this->lowercaseParams($value);
            } elseif (is_string($value)) {
                $result[$key] = strtolower($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * 判断请求参数是否包含期望参数
     *
     * @param array<string, mixed> $source
     * @param array<string, mixed> $expected
     */
    private function contains(array $source, array $expected): bool
    {
        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $source)) {
                return false;
            }

            if (is_array($value)) {
                if (!is_array($source[$key]) || !$this->contains($source[$key], $value)) {
                    return false;
                }

                continue;
            }

            if ((string) $source[$key] !== (string) $value) {
                return false;
            }
        }

        return true;
    }
}