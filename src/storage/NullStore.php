<?php

declare(strict_types=1);

namespace youvision\store\admin\storage;

use youvision\store\admin\contract\Store;

/**
 * 不落持久缓存的空实现
 */
class NullStore implements Store
{
    public function get(string $key): ?array
    {
        return null;
    }

    public function put(string $key, array $value, int $ttl = 0): void
    {
    }

    public function forget(string $key = '', string $prefix = ''): void
    {
    }
}