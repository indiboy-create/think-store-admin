<?php

declare(strict_types=1);

namespace youvision\store\admin\tests;

use think\App;
use think\Request;
use PHPUnit\Framework\TestCase;
use youvision\store\admin\AssetController;
use youvision\store\admin\Theme;
use youvision\store\admin\controller\ThemeController;
use youvision\store\admin\View;

final class ThemeViewTest extends TestCase
{
    private function app(): App
    {
        $app = new App(dirname(__DIR__));
        $app->config->set((array) require dirname(__DIR__) . '/src/config/store_admin.php', 'store_admin');

        return $app;
    }

    private function controller(?Theme $theme = null): ThemeController
    {
        $app = $this->app();
        $theme ??= new Theme((array) require dirname(__DIR__) . '/src/config/store_admin.php', dirname(__DIR__));

        return new ThemeController($theme, new View($app));
    }

    private function request(int $currentUid = 0, array $get = []): Request
    {
        $request = (new Request())->withGet($get);

        if ($currentUid > 0) {
            $request->store_admin_uid = $currentUid;
        }

        return $request;
    }

    public function testViewRendersIndexTemplate(): void
    {
        $view = new View($this->app());
        $html = $view->render('index', [
            'title' => '权限总览',
            'base' => '/store/think-store-admin',
            'home' => '/store/think-store-admin/index',
            'assets' => '/store/think-store-admin/assets',
            'apiUser' => '/store/think-store-admin/api/user',
            'version' => '1.0.0',
            'configs' => [['label' => '认证开关', 'value' => '已开启']],
            'tables' => [['name' => 'store_admin_rule', 'title' => '权限规则', 'desc' => '规则说明']],
            'routes' => [['method' => 'GET', 'path' => '/store/think-store-admin/index', 'name' => '权限总览']],
            'meta' => [['label' => '包名', 'value' => 'youvision/think-store-admin']],
            'uid' => 0,
        ]);

        self::assertStringContainsString('layui-layout-admin', $html);
        self::assertStringContainsString('/store/think-store-admin/assets/component/pear/css/pear.css', $html);
        self::assertStringContainsString('/store/think-store-admin/assets/admin/css/admin.css', $html);
        self::assertStringContainsString('/store/think-store-admin/assets/component/layui/layui.js', $html);
        self::assertStringContainsString('/store/think-store-admin/init', $html);
        self::assertStringContainsString('pear-admin', $html);
    }

    public function testThemeControllerRendersResponse(): void
    {
        $response = $this->controller()->index($this->request(5));

        self::assertSame(200, $response->getCode());
        self::assertStringContainsString('/store/think-store-admin/assets/component/pear/css/pear.css', (string) $response->getContent());
        self::assertStringContainsString('layui-layout-admin', (string) $response->getContent());
    }

    public function testThemeControllerUsesConfiguredThemeUser(): void
    {
        $theme = new Theme(array_replace(
            (array) require dirname(__DIR__) . '/src/config/store_admin.php',
            ['theme_user' => 8]
        ), dirname(__DIR__));

        self::assertSame(8, $theme->themeUser());

        $response = $this->controller($theme)->index($this->request(5));

        self::assertSame(200, $response->getCode());
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
        $file = AssetController::resolve('doc/css/index.css');

        self::assertNotNull($file);
        self::assertStringEndsWith('doc/css/index.css', str_replace('\\', '/', (string) $file));
        self::assertSame('text/css; charset=utf-8', AssetController::mime((string) $file));

        self::assertNull(AssetController::resolve(''));
        self::assertNull(AssetController::resolve('../src/config/store_admin.php'));
        self::assertNull(AssetController::resolve('css/missing.css'));
        self::assertSame('image/svg+xml', AssetController::mime('icon.svg'));
        self::assertSame('application/octet-stream', AssetController::mime('file.bin'));
    }

    public function testAssetControllerFileResponse(): void
    {
        $controller = new AssetController();

        $response = $controller->file('doc/css/index.css');

        self::assertSame(200, $response->getCode());
        self::assertStringContainsString('store-admin-brand', (string) $response->getContent());

        $missing = $controller->file('../config.php');

        self::assertSame(404, $missing->getCode());
    }
}