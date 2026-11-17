<?php

declare(strict_types=1);

namespace youvision\store\admin\store;

use think\facade\Session;
use youvision\store\admin\contract\Store;

/**
 * 基于 Session 的认证缓存
 */
class SessionStore implements Store
{
    private const INDEX_KEY = '_store_admin_cache_keys';

    public function get(string $key): ?array
    {
        if (!Session::has($key)) {
            return null;
        }

        $value = Session::get($key);

        return is_array($value) ? $value : null;
    }

    public function put(string $key, array $value, int $ttl = 0): void
    {
        Session::set($key, $value);
        $this->rememberKey($key);
    }

    public function forget(string $key = '', string $prefix = ''): void
    {
        $keys = (array) Session::get(self::INDEX_KEY, []);

        if ($key !== '') {
            Session::delete($key);
            Session::set(self::INDEX_KEY, array_values(array_diff($keys, [$key])));

            return;
        }

        $remaining = [];

        foreach ($keys as $item) {
            $item = (string) $item;

            if ($prefix !== '' && !str_starts_with($item, $prefix)) {
                $remaining[] = $item;
                continue;
            }

            Session::delete($item);
        }

        Session::set(self::INDEX_KEY, $remaining);
    }

    private function rememberKey(string $key): void
    {
        $keys = (array) Session::get(self::INDEX_KEY, []);

        if (!in_array($key, $keys, true)) {
            $keys[] = $key;
            Session::set(self::INDEX_KEY, array_values($keys));
        }
    }
}