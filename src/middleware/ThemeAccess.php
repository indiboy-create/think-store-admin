<?php

declare(strict_types=1);

namespace youvision\store\admin\middleware;

use Closure;
use think\App;
use think\Request;
use think\Response;
use youvision\store\admin\facade\Auth;

/**
 * 权限总览主题访问控制
 *
 * 默认要求登录：从请求的 user_id 中间件属性读取用户 ID，该属性应由
 * 登录中间件写入。theme_access 配置为 public 时跳过登录检查，仅用于
 * 本地演示；配置 theme_permission 后，还需拥有对应规则才能访问。
 */
class ThemeAccess
{
    protected string $mode = 'auth';

    protected string $permission = '';

    protected string $uidAttribute = 'user_id';

    public function __construct(?App $app = null)
    {
        $config = $app === null ? [] : (array) $app->config->get('store_admin', []);

        $this->mode = strtolower(trim((string) ($config['theme_access'] ?? 'auth')));
        $this->permission = trim((string) ($config['theme_permission'] ?? ''));
        $this->uidAttribute = (string) ($config['auth']['middleware_uid'] ?? 'user_id');
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->mode === 'public') {
            return $next($request);
        }

        $uid = $this->resolveUserId($request);

        if ($uid <= 0) {
            return $this->deny($request, '请先登录后再访问权限总览');
        }

        $request->store_admin_uid = $uid;

        if ($this->permission !== '' && !$this->checkPermission($uid)) {
            return $this->deny($request, '无权访问权限总览');
        }

        return $next($request);
    }

    /**
     * 读取登录中间件写入的用户 ID，不读取请求参数
     */
    protected function resolveUserId(Request $request): int
    {
        return (int) $request->{$this->uidAttribute};
    }

    protected function checkPermission(int $uid): bool
    {
        return Auth::check($this->permission, $uid, 0, 'node');
    }

    protected function deny(Request $request, string $message): Response
    {
        if ($request->isAjax() || $request->isJson()) {
            return Response::create(['code' => 403, 'msg' => $message], 'json', 403);
        }

        return Response::create('403 ' . $message, 'html', 403);
    }
}