<?php

declare(strict_types=1);

namespace youvision\store\admin\tests;

use Closure;
use think\App;
use think\Request;
use think\Response;
use PHPUnit\Framework\TestCase;
use youvision\store\admin\middleware\AuthCheck;

final class MiddlewareTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function middleware(array $config = []): TestableAuthCheck
    {
        $app = new App(dirname(__DIR__));
        $app->config->set($config, 'store_admin');

        return new TestableAuthCheck($app);
    }

    private function next(): Closure
    {
        return static fn (): Response => Response::create('ok', 'html', 200);
    }

    public function testDeniesWithoutUserId(): void
    {
        $middleware = $this->middleware();
        $request = new Request();

        $response = $middleware->handle($request, $this->next());

        self::assertSame(403, $response->getCode());
    }

    public function testDeniesWhenCheckFails(): void
    {
        $middleware = $this->middleware();
        $request = new Request();
        $request->user_id = 1;

        $response = $middleware->handle($request, $this->next());

        self::assertSame(403, $response->getCode());
    }

    public function testPassesWhenCheckSucceeds(): void
    {
        $middleware = $this->middleware();
        $middleware->grant = true;
        $request = new Request();
        $request->user_id = 1;

        $response = $middleware->handle($request, $this->next());

        self::assertSame(200, $response->getCode());
        self::assertSame('ok', $response->getContent());
        self::assertSame(1, $middleware->lastUid);
    }

    public function testAllowListSkipsCheck(): void
    {
        $middleware = $this->middleware(['auth' => ['middleware_allow' => ['/']]]);
        $request = new Request();

        $response = $middleware->handle($request, $this->next());

        self::assertSame(200, $response->getCode());
    }

    public function testAllowWildcard(): void
    {
        $middleware = $this->middleware(['auth' => ['middleware_allow' => ['user/*']]]);
        $middleware->forceRule = 'user/login';

        $response = $middleware->handle(new Request(), $this->next());

        self::assertSame(200, $response->getCode());
    }

    public function testExtraAllowFromParameters(): void
    {
        $middleware = $this->middleware();
        $middleware->forceRule = 'user/login';

        $response = $middleware->handle(new Request(), $this->next(), 'user/login');

        self::assertSame(200, $response->getCode());
    }

    public function testJsonDenyForAjax(): void
    {
        $middleware = $this->middleware();
        $request = new Request();
        $request->user_id = 1;
        $request->withHeader(['X-Requested-With' => 'XMLHttpRequest']);

        $response = $middleware->handle($request, $this->next());

        self::assertSame(403, $response->getCode());
        self::assertStringContainsString('未授权访问', $response->getContent());
    }

    public function testRulePrefix(): void
    {
        $middleware = $this->middleware(['auth' => ['middleware_prefix' => 'admin']]);
        $middleware->forceRule = 'user/index';

        $middleware->exposeAllowed('admin/user/index');

        self::assertTrue($middleware->isAllowed('admin/user/index'));
        self::assertFalse($middleware->isAllowed('user/index'));
    }
}

final class TestableAuthCheck extends AuthCheck
{
    public bool $grant = false;

    public ?int $lastUid = null;

    public ?string $forceRule = null;

    protected function rule(Request $request): string
    {
        return $this->forceRule ?? parent::rule($request);
    }

    protected function check(string $rule, int $uid): bool
    {
        $this->lastUid = $uid;

        return $this->grant;
    }

    public function exposeAllowed(string $pattern): void
    {
        $this->allow[] = $pattern;
    }

    public function isAllowed(string $rule): bool
    {
        return $this->allowed($rule);
    }
}