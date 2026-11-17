<?php

declare(strict_types=1);

namespace youvision\store\auth\store;

use think\facade\Cache;
use youvision\store\auth\contract\Store;

/**
 * 基于 ThinkPHP Cache 的认证缓存
 */
class CacheStore implements Store
{
    private const INDEX_KEY = 'store_auth_cache_keys';

    public function get(string $key): ?array
    {
        $value = Cache::get($key);

        return is_array($value) ? $value : null;
    }

    public function put(string $key, array $value, int $ttl = 0): void
    {
        Cache::set($key, $value, $ttl > 0 ? $ttl : null);
        $this->rememberKey($key);
    }

    public function forget(string $key = '', string $prefix = ''): void
    {
        $keys = (array) Cache::get(self::INDEX_KEY, []);

        if ($key !== '') {
            Cache::delete($key);
            Cache::set(self::INDEX_KEY, array_values(array_diff($keys, [$key])));

            return;
        }

        $remaining = [];

        foreach ($keys as $item) {
            $item = (string) $item;

            if ($prefix !== '' && !str_starts_with($item, $prefix)) {
                $remaining[] = $item;
                continue;
            }

            Cache::delete($item);
        }

        if ($remaining === []) {
            Cache::delete(self::INDEX_KEY);

            return;
        }

        Cache::set(self::INDEX_KEY, $remaining);
    }

    private function rememberKey(string $key): void
    {
        $keys = (array) Cache::get(self::INDEX_KEY, []);

        if (!in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::set(self::INDEX_KEY, array_values($keys));
        }
    }
}