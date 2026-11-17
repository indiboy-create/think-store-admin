<?php

declare(strict_types=1);

namespace youvision\store\admin\tests;

use think\App;
use think\Request;
use think\Response;
use PHPUnit\Framework\TestCase;
use youvision\store\admin\Jwt;
use youvision\store\admin\middleware\JwtAuth;

final class JwtAuthTest extends TestCase
{
    private function app(): App
    {
        $app = new App(dirname(__DIR__));
        $config = (array) require dirname(__DIR__) . '/src/config.php';
        $config['jwt']['secret'] = str_repeat('k', 32);

        $app->config->set($config, 'store_admin');
        $app->config->set([
            'default' => 'file',
            'stores' => [
                'file' => ['type' => 'File', 'path' => dirname(__DIR__) . '/runtime/cache/'],
            ],
        ], 'cache');

        return $app;
    }

    private function next(): \Closure
    {
        return static fn (): Response => Response::create('ok', 'html', 200);
    }

    public function testSetsUserIdFromToken(): void
    {
        $app = $this->app();
        $token = Jwt::issue(['user_id' => 7]);
        $request = (new Request())->withHeader(['Authorization' => 'Bearer ' . $token]);

        $response = (new JwtAuth($app))->handle($request, $this->next());

        self::assertSame(200, $response->getCode());
        self::assertSame(7, $request->user_id);
    }

    public function testMissingTokenReturnsUnauthorized(): void
    {
        $app = $this->app();
        $response = (new JwtAuth($app))->handle(new Request(), $this->next());

        self::assertSame(401, $response->getCode());
        self::assertStringContainsString('令牌', (string) $response->getContent());
    }

    public function testInvalidTokenReturnsUnauthorized(): void
    {
        $app = $this->app();
        $request = (new Request())->withHeader(['Authorization' => 'Bearer invalid.token.value']);

        $response = (new JwtAuth($app))->handle($request, $this->next());

        self::assertSame(401, $response->getCode());
        self::assertStringContainsString('令牌', (string) $response->getContent());
    }

    public function testTokenWithoutUserIdReturnsUnauthorized(): void
    {
        $app = $this->app();
        $token = Jwt::issue(['username' => 'alice']);
        $request = (new Request())->withHeader(['Authorization' => 'Bearer ' . $token]);

        $response = (new JwtAuth($app))->handle($request, $this->next());

        self::assertSame(401, $response->getCode());
        self::assertStringContainsString('用户标识', (string) $response->getContent());
    }
}