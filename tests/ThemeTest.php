<?php

declare(strict_types=1);

namespace youvision\store\admin\tests;

use PHPUnit\Framework\TestCase;
use youvision\store\admin\Theme;

final class ThemeTest extends TestCase
{
    private function theme(array $config = []): Theme
    {
        return new Theme(array_replace_recursive((array) require dirname(__DIR__) . '/src/config/store_admin.php', $config), dirname(__DIR__));
    }

    public function testDefaultAddresses(): void
    {
        $theme = $this->theme();

        self::assertSame('/store/think-store-admin', $theme->base());
        self::assertSame('/store/think-store-admin/index', $theme->home());
        self::assertSame('/store/think-store-admin/assets', $theme->assets());
        self::assertSame('/store/think-store-admin/api/user', $theme->apiUser());
        self::assertSame(0, $theme->themeUser());
    }

    public function testCustomAddresses(): void
    {
        $theme = $this->theme([
            'route' => ['prefix' => '', 'segment' => 'auth'],
            'assets' => '/static/auth',
            'theme_user' => 8,
        ]);

        self::assertSame('/auth', $theme->base());
        self::assertSame('/auth/index', $theme->home());
        self::assertSame('/static/auth', $theme->assets());
        self::assertSame('/auth/api/user', $theme->apiUser());
        self::assertSame(8, $theme->themeUser());
    }

    public function testEmptySegment(): void
    {
        $theme = $this->theme(['route' => ['prefix' => 'store', 'segment' => '']]);

        self::assertSame('/store', $theme->base());
        self::assertSame('/store/index', $theme->home());
        self::assertSame('/store/assets', $theme->assets());
    }

    public function testConfigSummary(): void
    {
        $summary = $this->theme([
            'auth' => [
                'on' => 0,
                'type' => 2,
                'cache_store' => 'cache',
                'user' => 'users',
            ],
        ])->configSummary();

        $values = [];

        foreach ($summary as $item) {
            $values[$item['label']] = $item['value'];
        }

        self::assertSame('已关闭', $values['认证开关']);
        self::assertSame('持久缓存认证', $values['认证方式']);
        self::assertSame('cache', $values['缓存驱动']);
        self::assertSame('启用', $values['请求内缓存']);
        self::assertSame('users', $values['用户表']);
        self::assertSame('需登录', $values['主题访问']);
        self::assertSame('仅本人', $values['跨用户查询']);
        self::assertCount(12, $summary);
    }

    public function testAccessModeAndPermission(): void
    {
        $theme = $this->theme([
            'theme_access' => 'public',
            'theme_permission' => 'store/auth/admin',
        ]);

        self::assertSame('public', $theme->accessMode());
        self::assertSame('store/auth/admin', $theme->permission());

        $deny = $this->theme(['theme_access' => 'unknown', 'theme_permission' => '  ']);

        self::assertSame('auth', $deny->accessMode());
        self::assertSame('', $deny->permission());
    }

    public function testTablesAndRoutes(): void
    {
        $theme = $this->theme();

        self::assertCount(4, $theme->tables());
        self::assertSame('store_admin_rule', $theme->tables()[0]['name']);
        self::assertCount(3, $theme->routes());
        self::assertSame('/store/think-store-admin/index', $theme->routes()[0]['path']);
        self::assertSame('/store/think-store-admin/assets/<path>', $theme->routes()[2]['path']);
    }

    public function testMetaAndVersion(): void
    {
        $theme = $this->theme();

        self::assertSame('youvision/think-store-admin', $theme->meta()[0]['value']);
        self::assertNotSame('', $theme->version());
    }
}