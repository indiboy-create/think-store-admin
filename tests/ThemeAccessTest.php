<?php

declare(strict_types=1);

namespace youvision\store\auth\tests;

use Closure;
use think\App;
use think\Request;
use think\Response;
use PHPUnit\Framework\TestCase;
use youvision\store\auth\middleware\ThemeAccess;

final class ThemeAccessTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function middleware(array $config = []): TestableThemeAccess
    {
        $app = new App(dirname(__DIR__));
        $app->config->set($config, 'store_auth');

        return new TestableThemeAccess($app);
    }

    private function next(): Closure
    {
        return static fn (): Response => Response::create('ok', 'html', 200);
    }

    public function testAuthModeRequiresLogin(): void
    {
        $response = $this->middleware()->handle(new Request(), $this->next());

        self::assertSame(403, $response->getCode());
    }

    public function testAuthModePassesWithUid(): void
    {
        $request = new Request();
        $request->user_id = 5;

        $response = $this->middleware()->handle($request, $this->next());

        self::assertSame(200, $response->getCode());
        self::assertSame(5, $request->middleware('store_auth_uid'));
    }

    public function testPublicModeAllowsAnonymous(): void
    {
        $response = $this->middleware(['theme_access' => 'public'])->handle(new Request(), $this->next());

        self::assertSame(200, $response->getCode());
    }

    public function testPermissionDenied(): void
    {
        $middleware = $this->middleware(['theme_permission' => 'store/auth/admin']);
        $middleware->grant = false;

        $request = new Request();
        $request->user_id = 5;

        $response = $middleware->handle($request, $this->next());

        self::assertSame(403, $response->getCode());
    }

    public function testPermissionGranted(): void
    {
        $middleware = $this->middleware(['theme_permission' => 'store/auth/admin']);
        $middleware->grant = true;

        $request = new Request();
        $request->user_id = 5;

        $response = $middleware->handle($request, $this->next());

        self::assertSame(200, $response->getCode());
    }

    public function testEmptyPermissionOnlyRequiresLogin(): void
    {
        $middleware = $this->middleware();
        $middleware->grant = false;

        $request = new Request();
        $request->user_id = 5;

        $response = $middleware->handle($request, $this->next());

        self::assertSame(200, $response->getCode());
    }

    public function testCustomUidAttribute(): void
    {
        $middleware = $this->middleware(['auth_middleware_uid' => 'admin_id']);

        $request = new Request();
        $request->admin_id = 9;

        $response = $middleware->handle($request, $this->next());

        self::assertSame(200, $response->getCode());
        self::assertSame(9, $request->middleware('store_auth_uid'));
    }
}

final class TestableThemeAccess extends ThemeAccess
{
    public bool $grant = true;

    protected function checkPermission(int $uid): bool
    {
        return $this->grant;
    }
}