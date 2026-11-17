<?php

declare(strict_types=1);

namespace youvision\store\admin\middleware;

use Closure;
use think\App;
use think\Request;
use think\Response;
use youvision\store\admin\Jwt;

/**
 * JWT 认证中间件
 *
 * 校验 Bearer Token，并把业务载荷中的用户 ID 写入请求属性，
 * 供后续 AuthCheck / ThemeAccess 使用。
 */
class JwtAuth
{
    protected string $uidClaim = 'user_id';

    protected string $uidAttribute = 'user_id';

    public function __construct(?App $app = null)
    {
        $config = $app === null ? [] : (array) $app->config->get('store_admin', []);
        $auth = (array) ($config['auth'] ?? []);

        $this->uidClaim = (string) ($config['jwt']['uid_claim'] ?? 'user_id');
        $this->uidAttribute = (string) ($auth['middleware_uid'] ?? 'user_id');
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = Jwt::tokenFromRequest($request);

        if ($token === '') {
            return $this->deny('未提供令牌');
        }

        $result = Jwt::check($token);

        if ((int) ($result['code'] ?? 0) !== 1) {
            return $this->deny(
                (string) ($result['msg'] ?? '令牌无效'),
                (int) ($result['error_code'] ?? 0)
            );
        }

        $data = (array) ($result['data'] ?? []);
        $uid = (int) ($data[$this->uidClaim] ?? 0);

        if ($uid <= 0) {
            return $this->deny('令牌缺少用户标识');
        }

        $request->{$this->uidAttribute} = $uid;

        return $next($request);
    }

    protected function deny(string $message, int $errorCode = 0): Response
    {
        return Response::create([
            'code' => 0,
            'error_code' => $errorCode,
            'msg' => $message,
        ], 'json', 401);
    }
}