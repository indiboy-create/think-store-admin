<?php

declare(strict_types=1);

namespace youvision\store\admin\tests;

use PHPUnit\Framework\TestCase;
use youvision\store\admin\support\url\Url;
use youvision\store\admin\Theme;

final class UrlTest extends TestCase
{
    private function url(array $config = []): Url
    {
        $theme = new Theme(array_replace_recursive(
            (array) require dirname(__DIR__) . '/src/config/store_admin.php',
            $config
        ), dirname(__DIR__));

        return new Url($theme);
    }

    public function testDefaultAddresses(): void
    {
        $url = $this->url();

        self::assertSame('/store/think-store-admin', $url->base());
        self::assertSame('/store/think-store-admin/index', $url->home());
        self::assertSame('/store/think-store-admin/passport/login', $url->to('passport/login'));
        self::assertSame('/store/think-store-admin/assets/component/layui/layui.js', $url->asset('component/layui/layui.js'));
        self::assertSame('/store/think-store-admin/api/user', $url->api('user'));
    }

    public function testQueryParams(): void
    {
        $url = $this->url();

        self::assertSame('/store/think-store-admin/menu?page=1&size=20', $url->to('menu', ['page' => 1, 'size' => 20]));
        self::assertSame('/store/think-store-admin/menu?q=a+b', $url->to('menu', ['q' => 'a b']));
    }

    public function testEmptyPrefixAndSegment(): void
    {
        $url = $this->url(['route' => ['prefix' => '', 'segment' => '']]);

        self::assertSame('', $url->base());
        self::assertSame('/index', $url->home());
        self::assertSame('/passport/login', $url->to('passport/login'));
        self::assertSame('/assets/css/index.css', $url->asset('css/index.css'));
    }

    public function testExternalAssets(): void
    {
        $url = $this->url(['assets' => '//cdn.example.com/store-admin']);

        self::assertSame('//cdn.example.com/store-admin/component/layui/layui.js', $url->asset('component/layui/layui.js'));
    }

    public function testAbsoluteFallsBackWithoutRequest(): void
    {
        $url = $this->url();

        self::assertSame('/store/think-store-admin/index', $url->to('index', [], true));
    }

    public function testTrailingSlashesAreNormalized(): void
    {
        $url = $this->url();

        self::assertSame('/store/think-store-admin/menu', $url->to('/menu/'));
        self::assertSame('/store/think-store-admin/assets/a.css', $url->asset('/a.css'));
    }
}