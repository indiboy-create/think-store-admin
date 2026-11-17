<?php

declare(strict_types=1);

use youvision\store\auth\Auth;

if (!function_exists('store_auth_check')) {
    /**
     * 权限认证快捷方法
     *
     * @param string|array<int, string> $name     需要验证的规则，支持逗号分隔字符串或数组
     * @param int                       $uid      用户 ID
     * @param int                       $type     规则类型，0 表示不限制
     * @param string                    $mode     认证模式，url 表示同时校验规则中的请求参数
     * @param string                    $relation 多规则关系：or 满足其一即通过，and 需全部满足
     */
    function store_auth_check($name, int $uid, int $type = 1, string $mode = 'url', string $relation = 'or'): bool
    {
        return Auth::check($name, $uid, $type, $mode, $relation);
    }
}

if (!function_exists('store_auth_rules')) {
    /**
     * @return array<int, string>
     */
    function store_auth_rules(int $uid, int $type = 1): array
    {
        return Auth::rules($uid, $type);
    }
}

if (!function_exists('store_auth_roles')) {
    /**
     * @return array<int, mixed>
     */
    function store_auth_roles(int $uid): array
    {
        return Auth::roles($uid);
    }
}

if (!function_exists('store_auth_role_ids')) {
    /**
     * @return array<int, int>
     */
    function store_auth_role_ids(int $uid): array
    {
        return array_map('intval', Auth::roles($uid, 'role_id'));
    }
}

if (!function_exists('store_auth_flush')) {
    function store_auth_flush(int $uid = 0, int $type = 0): void
    {
        Auth::flush($uid, $type);
    }
}

if (!function_exists('store_auth_purge_user')) {
    /**
     * 删除用户的角色关系并清理认证缓存
     */
    function store_auth_purge_user(int $uid): int
    {
        return Auth::purgeUser($uid);
    }
}
