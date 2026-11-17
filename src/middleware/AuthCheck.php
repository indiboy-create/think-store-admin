<?php

declare(strict_types=1);

namespace youvision\store\admin\middleware;

use Closure;
use think\App;
use think\Request;
use think\Response;
use youvision\store\admin\Auth;

/**
 * 控制器/方法级权限认证中间件
 *
 * 规则名称为 控制器/方法 的小写形式，可通过 auth.middleware_prefix 增加前缀。
 */
class AuthCheck
{
    /**
     * @var array<int, string>
     */
    protected array $allow = [];

    protected string $uidAttribute = 'user_id';

    protected string $prefix = '';

    protected bool $json = false;

    public function __construct(?App $app = null)
    {
        $config = $app === null ? [] : (array) $app->config->get('store_admin', []);

        $this->allow = (array) ($config['auth']['middleware_allow'] ?? []);
        $this->uidAttribute = (string) ($config['auth']['middleware_uid'] ?? 'user_id');
        $this->prefix = trim((string) ($config['auth']['middleware_prefix'] ?? ''), '/');
        $this->json = (bool) ($config['auth']['middleware_json'] ?? false);
    }

    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        $rule = $this->rule($request);

        if ($this->allowed($rule, $params)) {
            return $next($request);
        }

        $uid = $this->resolveUserId($request);

        if ($uid > 0 && $this->check($rule, $uid)) {
            return $next($request);
        }

        return $this->deny($request);
    }

    protected function check(string $rule, int $uid): bool
    {
        return Auth::check($rule, $uid);
    }

    protected function rule(Request $request): string
    {
        $controller = str_replace('\\', '/', (string) $request->controller());
        $rule = strtolower($controller . '/' . $request->action());

        if ($this->prefix !== '') {
            $rule = strtolower($this->prefix) . '/' . $rule;
        }

        return $rule;
    }

    /**
     * @param array<int, string> $extra
     */
    protected function allowed(string $rule, array $extra = []): bool
    {
        foreach (array_merge($this->allow, $extra) as $pattern) {
            $pattern = strtolower(trim((string) $pattern));

            if ($pattern === $rule) {
                return true;
            }

            if (str_contains($pattern, '*')) {
                $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';

                if (preg_match($regex, $rule) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 解析登录用户 ID
     *
     * 默认读取前序中间件写入请求的 user_id 属性，不读取请求参数。
     * 应用应继承本中间件并覆盖该方法，从可信登录态获取用户 ID。
     */
    protected function resolveUserId(Request $request): int
    {
        return (int) $request->{$this->uidAttribute};
    }

    protected function deny(Request $request): Response
    {
        if ($this->json || $request->isAjax()) {
            return Response::create(['code' => 403, 'msg' => '未授权访问'], 'json', 403);
        }

        return Response::create('403 未授权访问', 'html', 403);
    }
}