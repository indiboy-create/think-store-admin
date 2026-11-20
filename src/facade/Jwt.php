<?php

declare(strict_types=1);

namespace youvision\store\admin\facade;

use youvision\store\admin\service\Jwt as JwtService;

/**
 * JWT 静态入口
 *
 * @method static string issue(array $data = [])
 * @method static array check(string $token)
 * @method static array parse(string $token, bool $complete = false)
 * @method static array refresh(string $token)
 * @method static array logout(string $token)
 * @method static string tokenFromRequest(\think\Request $request)
 * @method static string secret(array $config = [])
 * @method static array config()
 */
class Jwt
{
    /**
     * @param array<int, mixed> $args
     * @return mixed
     */
    public static function __callStatic(string $method, array $args)
    {
        return JwtService::{$method}(...$args);
    }
}