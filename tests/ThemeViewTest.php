<?php

declare(strict_types=1);

namespace youvision\store\auth\tests;

use think\App;
use think\Request;
use PHPUnit\Framework\TestCase;
use youvision\store\auth\AssetController;
use youvision\store\auth\Theme;
use youvision\store\auth\ThemeController;
use youvision\store\auth\View;

final class ThemeViewTest extends TestCase
{
    private function app(): App
    {
        $app = new App(dirname(__DIR__));
        $app->config->set((array) require dirname(__DIR__) . '/src/config.php', 'store_auth');

        return $app;
    }

    private function controller(?Theme $theme = null): ThemeController
    {
        $app = $this->app();
        $theme ??= new Theme((array) require dirname(__DIR__) . '/src/config.php', dirname(__DIR__));

        return new ThemeController($theme, new View($app));
    }

    private function request(int $currentUid = 0, array $get = []): Request
    {
        $request = (new Request())->withGet($get);

        if ($currentUid > 0) {
            $request->store_auth_uid = $currentUid;
        }

        return $request;
    }

    public function testViewRendersIndexTemplate(): void
    {
        $view = new View($this->app());
        $html = $view->render('index', [
            'title' => '权限总览',
            'base' => '/store/think-store-auth',
            'home' => '/store/think-store-auth/index',
            'assets' => '/store/think-store-auth/assets',
            'apiUser' => '/store/think-store-auth/api/user',
            'version' => '1.0.0',
            'configs' => [['label' => '认证开关', 'value' => '已开启']],
            'tables' => [['name' => 'store_auth_rule', 'title' => '权限规则', 'desc' => '规则说明']],
            'routes' => [['method' => 'GET', 'path' => '/store/think-store-auth/index', 'name' => '权限总览']],
            'meta' => [['label' => '包名', 'value' => 'youvision/think-store-auth']],
            'uid' => 0,
        ]);

        self::assertStringContainsString('layui-layout-admin', $html);
        self::assertStringContainsString('/store/think-store-auth/assets/layui/css/layui.css', $html);
        self::assertStringContainsString('权限总览', $html);
        self::assertStringContainsString('认证开关', $html);
        self::assertStringContainsString('store_auth_rule', $html);
        self::assertStringContainsString('data-api="/store/think-store-auth/api/user"', $html);
    }

    public function testThemeControllerRendersResponse(): void
    {
        $response = $this->controller()->index($this->request(5));

        self::assertSame(200, $response->getCode());
        self::assertStringContainsString('Think Store Auth', (string) $response->getContent());
        self::assertStringContainsString('/store/think-store-auth/index', (string) $response->getContent());
        self::assertStringContainsString('data-uid="5"', (string) $response->getContent());
    }

    public function testThemeControllerUsesConfiguredThemeUser(): void
    {
        $theme = new Theme(array_replace(
            (array) require dirname(__DIR__) . '/src/config.php',
            ['theme_user' => 8]
        ), dirname(__DIR__));

        $response = $this->controller($theme)->index($this->request(5));

        self::assertStringContainsString('data-uid="8"', (string) $response->getContent());
    }

    public function testThemeUserEndpointRejectsInvalidUid(): void
    {
        $request = $this->request(5, ['uid' => 0]);

        $response = $this->controller()->user($request);

        self::assertSame(422, $response->getCode());
        self::assertStringContainsString('用户 ID', (string) $response->getContent());
    }

    public function testThemeUserEndpointRequiresLogin(): void
    {
        $request = $this->request(0, ['uid' => 7]);

        $response = $this->controller()->user($request);

        self::assertSame(403, $response->getCode());
        self::assertStringContainsString('登录', (string) $response->getContent());
    }

    public function testThemeUserEndpointRejectsOtherUsers(): void
    {
        $request = $this->request(5, ['uid' => 7]);

        $response = $this->controller()->user($request);

        self::assertSame(403, $response->getCode());
        self::assertStringContainsString('其他用户', (string) $response->getContent());
    }

    public function testAssetResolve(): void
    {
        $file = AssetController::resolve('css/index.css');

        self::assertNotNull($file);
        self::assertStringEndsWith('theme/assets/css/index.css', str_replace('\\', '/', (string) $file));
        self::assertSame('text/css; charset=utf-8', AssetController::mime((string) $file));

        self::assertNull(AssetController::resolve(''));
        self::assertNull(AssetController::resolve('../src/config.php'));
        self::assertNull(AssetController::resolve('css/missing.css'));
        self::assertSame('image/svg+xml', AssetController::mime('icon.svg'));
        self::assertSame('application/octet-stream', AssetController::mime('file.bin'));
    }

    public function testAssetControllerFileResponse(): void
    {
        $controller = new AssetController();

        $response = $controller->file('css/index.css');

        self::assertSame(200, $response->getCode());
        self::assertStringContainsString('store-auth-brand', (string) $response->getContent());

        $missing = $controller->file('../config.php');

        self::assertSame(404, $missing->getCode());
    }
}