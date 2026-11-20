<?php

declare(strict_types=1);

namespace youvision\store\admin\support\jump;

use think\App;
use think\exception\HttpResponseException;
use think\Request;
use think\Response;
use youvision\store\admin\support\theme\Theme;

/**
 * 控制器跳转响应 trait。
 *
 * 行为参考 think-jump：success/error 自动适配 HTML 与 JSON，
 * result 返回统一 API 数据，redirect 执行 URL 重定向。
 */
trait Jump
{
    protected function success(
        mixed $msg = '',
        ?string $url = null,
        mixed $data = '',
        ?int $wait = null,
        array $header = []
    ): void {
        $this->dispatchJump([
            'code' => (int) $this->jumpConfig('success_code', 1),
            'msg' => $msg,
            'data' => $data,
            'url' => $this->resolveJumpUrl($url, true),
            'wait' => $wait ?? (int) $this->jumpConfig('wait', 3),
        ], $header, 'success');
    }

    protected function error(
        mixed $msg = '',
        ?string $url = null,
        mixed $data = '',
        ?int $wait = null,
        array $header = []
    ): void {
        $this->dispatchJump([
            'code' => (int) $this->jumpConfig('error_code', 0),
            'msg' => $msg,
            'data' => $data,
            'url' => $this->resolveJumpUrl($url, false),
            'wait' => $wait ?? (int) $this->jumpConfig('wait', 3),
        ], $header, 'error');
    }

    protected function result(
        mixed $data,
        ?int $code = null,
        mixed $msg = '',
        string $type = '',
        array $header = []
    ): void {
        $type = strtolower(trim($type));

        if ($type === '' || $type === 'html') {
            $type = 'json';
        }

        $response = Response::create([
            'code' => $code ?? (int) $this->jumpConfig('result_code', 0),
            'msg' => $msg,
            'time' => time(),
            'data' => $data,
        ], $type)->header($header);

        throw new HttpResponseException($response);
    }

    protected function redirect(string $url, ?int $code = null, array $with = []): void
    {
        $response = Response::create($url, 'redirect')->code($code ?? (int) $this->jumpConfig('redirect_code', 302));

        if ($with !== []) {
            $response->with($with);
        }

        throw new HttpResponseException($response);
    }

    protected function getResponseType(): string
    {
        $request = $this->jumpRequest();

        return $request->isJson() || $request->isAjax() ? 'json' : 'html';
    }

    protected function jumpApp(): App
    {
        return $this->app ?? app();
    }

    protected function jumpRequest(): Request
    {
        return $this->request ?? request();
    }

    protected function jumpConfig(string $name, mixed $default = null): mixed
    {
        return $this->jumpApp()->config->get('store_admin.jump.' . $name, $default);
    }

    protected function resolveJumpUrl(?string $url, bool $success): ?string
    {
        if ($url === null) {
            if ($success) {
                $referer = (string) $this->jumpRequest()->server('HTTP_REFERER');

                return $referer !== '' ? $referer : null;
            }

            return $this->jumpRequest()->isAjax() || $this->jumpRequest()->isJson()
                ? ''
                : 'javascript:history.back(-1);';
        }

        if ($url === '') {
            return '';
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) === 1 || str_starts_with($url, '/')) {
            return $url;
        }

        return (string) $this->jumpApp()->route->buildUrl($url);
    }

    protected function jumpTemplate(string $type): string
    {
        $template = trim((string) $this->jumpConfig($type === 'success' ? 'success_tmpl' : 'error_tmpl', ''));

        return $template !== '' ? $template : dirname(__DIR__, 3) . '/theme/view/jump/' . ($type === 'success' ? 'default' : 'error') . '.html';
    }

    protected function jumpSafeText(mixed $value): string
    {
        return htmlspecialchars(strip_tags(is_scalar($value) ? (string) $value : ''), ENT_QUOTES, 'UTF-8');
    }

    protected function dispatchJump(array $result, array $header, string $type): void
    {
        if ($this->getResponseType() === 'html') {
            $view = $result;
            $view['assets'] = $this->jumpApp()->make(Theme::class)->assets();
            $view['msg'] = $this->jumpSafeText($result['msg'] ?? '');
            $view['url'] = htmlspecialchars((string) ($result['url'] ?? ''), ENT_QUOTES, 'UTF-8');
            $view['wait'] = max(0, (int) ($result['wait'] ?? 0));

            $response = Response::create($this->jumpTemplate($type), 'view')
                ->assign($view)
                ->header($header);
        } else {
            $response = Response::create($result, 'json')->header($header);
        }

        throw new HttpResponseException($response);
    }
}