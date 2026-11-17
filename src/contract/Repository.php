<?php

declare(strict_types=1);

namespace youvision\store\admin\contract;

/**
 * 认证数据源契约
 */
interface Repository
{
    /**
     * 获取用户关联的角色 ID
     *
     * @return array<int, int>
     */
    public function roleIds(int $uid): array;

    /**
     * 获取角色关联的有效规则
     *
     * @param array<int, int> $roleIds
     * @param int             $type 规则类型，0 表示不限制
     * @return array<int, array{name: string, condition: string, type: int}>
     */
    public function rulesForRoles(array $roleIds, int $type): array;

    /**
     * 获取用户字段数据，用户不存在时返回空数组
     *
     * @return array<string, mixed>
     */
    public function user(int $uid): array;

    /**
     * 获取用户所属角色
     *
     * @return array<int, mixed>
     */
    public function roles(int $uid, string $field = ''): array;
}