<?php

declare(strict_types=1);

use youvision\store\admin\Auth;
use youvision\store\admin\Jwt;
use youvision\store\admin\support\admin\Salting;

if (!function_exists('store_admin_check')) {
    /**
     * 权限认证快捷方法
     *
     * @param string|array<int, string> $name     需要验证的规则，支持逗号分隔字符串或数组
     * @param int                       $uid      用户 ID
     * @param int                       $type     规则类型，0 表示不限制
     * @param string                    $mode     认证模式，url 表示同时校验规则中的请求参数
     * @param string                    $relation 多规则关系：or 满足其一即通过，and 需全部满足
     */
    function store_admin_check($name, int $uid, int $type = 1, string $mode = 'url', string $relation = 'or'): bool
    {
        return Auth::check($name, $uid, $type, $mode, $relation);
    }
}

if (!function_exists('store_admin_rules')) {
    /**
     * @return array<int, string>
     */
    function store_admin_rules(int $uid, int $type = 1): array
    {
        return Auth::rules($uid, $type);
    }
}

if (!function_exists('store_admin_roles')) {
    /**
     * @return array<int, mixed>
     */
    function store_admin_roles(int $uid): array
    {
        return Auth::roles($uid);
    }
}

if (!function_exists('store_admin_role_ids')) {
    /**
     * @return array<int, int>
     */
    function store_admin_role_ids(int $uid): array
    {
        return array_map('intval', Auth::roles($uid, 'role_id'));
    }
}

if (!function_exists('store_admin_flush')) {
    function store_admin_flush(int $uid = 0, int $type = 0): void
    {
        Auth::flush($uid, $type);
    }
}

if (!function_exists('store_admin_purge_user')) {
    /**
     * 删除用户的角色关系并清理认证缓存
     */
    function store_admin_purge_user(int $uid): int
    {
        return Auth::purgeUser($uid);
    }
}
if (!function_exists('store_admin_make_salt')) {
    function store_admin_make_salt(?int $length = null): string
    {
        return Salting::makeSalt($length);
    }
}

if (!function_exists('store_admin_make_salt_pair')) {
    /**
     * @return array{salt: string, salt2: string}
     */
    function store_admin_make_salt_pair(?int $length = null, ?int $length2 = null): array
    {
        return Salting::makePair($length, $length2);
    }
}

if (!function_exists('store_admin_password')) {
    function store_admin_password(string $password, string $salt, string $salt2): string
    {
        return Salting::encode($password, $salt, $salt2);
    }
}

if (!function_exists('store_admin_password_verify')) {
    function store_admin_password_verify(string $password, string $salt, string $salt2, string $hash): bool
    {
        return Salting::verify($password, $salt, $salt2, $hash);
    }
}

if (!function_exists('store_admin_verify_password')) {
    /**
     * 校验用户名、邮箱或手机号与密码，成功返回用户 ID
     */
    function store_admin_verify_password(string $login, string $password): int
    {
        return Auth::verifyPassword($login, $password);
    }
}
if (!function_exists('store_admin_jwt_issue')) {
    /**
     * @param array<string, mixed> $claims
     */
    function store_admin_jwt_issue(array $claims = []): string
    {
        return Jwt::issue($claims);
    }
}

if (!function_exists('store_admin_jwt_check')) {
    /**
     * @return array<string, mixed>
     */
    function store_admin_jwt_check(string $token): array
    {
        return Jwt::check($token);
    }
}

if (!function_exists('store_admin_jwt_refresh')) {
    /**
     * @return array<string, mixed>
     */
    function store_admin_jwt_refresh(string $token): array
    {
        return Jwt::refresh($token);
    }
}

if (!function_exists('store_admin_jwt_logout')) {
    /**
     * @return array<string, mixed>
     */
    function store_admin_jwt_logout(string $token): array
    {
        return Jwt::logout($token);
    }
}

if (!function_exists('store_admin_jwt_token')) {
    function store_admin_jwt_token(\think\Request $request): string
    {
        return Jwt::tokenFromRequest($request);
    }
}
