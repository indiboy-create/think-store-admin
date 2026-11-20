<?php

declare(strict_types=1);

namespace youvision\store\admin\support\url;

use youvision\store\admin\support\theme\Theme;

/**
 * 包内链接生成器
 *
 * 仅基于 store_admin.route 与 assets 配置拼接地址，不依赖宿主路由注册，
 * 因此不受宿主 Route::buildUrl、伪静态后缀与域名配置影响。
 */
final class Url
{
    public function __construct(protected Theme $theme)
    {
    }

    /**
     * 包访问根地址，例如 /store/think-store-admin
     */
    public function base(): string
    {
        return $this->theme->base();
    }

    /**
     * 首页地址
     */
    public function home(): string
    {
        return $this->to('index');
    }

    /**
     * 生成包内地址
     *
     * @param array<string, mixed> $params   查询参数
     * @param bool                 $absolute 是否补全协议与域名
     */
    public function to(string $path = '', array $params = [], bool $absolute = false): string
    {
        $path = trim($path, '/');
        $url = $this->base() . ($path === '' ? '' : '/' . $path);

        if ($params !== []) {
            $url .= '?' . http_build_query($params);
        }

        if (!$absolute) {
            return $url;
        }

        $domain = $this->domain();

        return $domain === '' ? $url : $domain . $url;
    }

    /**
     * 生成包内静态资源地址，支持 assets 配置为外部托管地址
     */
    public function asset(string $path = ''): string
    {
        $path = ltrim($path, '/');

        return $this->theme->assets() . ($path === '' ? '' : '/' . $path);
    }

    /**
     * 生成包内 JSON 接口地址
     */
    public function api(string $path = ''): string
    {
        $path = ltrim($path, '/');

        return $this->to($path === '' ? 'api' : 'api/' . $path);
    }

    /**
     * 当前请求的协议与域名，无 HTTP 上下文时返回空字符串
     */
    protected function domain(): string
    {
        try {
            $request = app('request');
        } catch (\Throwable) {
            return '';
        }

        if ($request === null) {
            return '';
        }

        $domain = rtrim((string) $request->domain(), '/');

        return preg_match('#^https?://[^/]+#', $domain) === 1 ? $domain : '';
    }
}