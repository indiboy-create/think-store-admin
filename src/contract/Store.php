<?php

declare(strict_types=1);

namespace youvision\store\auth\contract;

/**
 * 认证结果持久缓存契约
 */
interface Store
{
    /**
     * 读取缓存，不存在时返回 null
     *
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array;

    /**
     * 写入缓存
     *
     * @param array<string, mixed> $value
     */
    public function put(string $key, array $value, int $ttl = 0): void;

    /**
     * 删除缓存
     *
     * @param string $key     缓存键，为空时删除全部认证缓存
     * @param string $prefix  缓存键前缀，用于定位索引
     */
    public function forget(string $key = '', string $prefix = ''): void;
}