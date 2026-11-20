<?php

declare(strict_types=1);

namespace youvision\store\admin\facade;

use youvision\store\admin\service\Auth as AuthService;

/**
 * 权限认证静态入口
 *
 * @method static bool check(string|array $name, int $uid, int $type = 1, string $mode = 'url', string $relation = 'or')
 * @method static array rules(int $uid, int $type = 1)
 * @method static array roles(int $uid, string $field = '')
 * @method static void flush(int $uid = 0, int $type = 0)
 * @method static int purgeUser(int $uid)
 * @method static int verifyPassword(string $login, string $password)
 *
 * @see \youvision\store\admin\service\Auth
 */
class Auth
{
    /**
     * 转发静态调用到认证服务
     *
     * @param array<int, mixed> $args
     * @return mixed
     */
    public static function __callStatic(string $method, array $args)
    {
        return app(AuthService::class)->{$method}(...$args);
    }
}