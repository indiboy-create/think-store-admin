<?php

declare(strict_types=1);

namespace youvision\store\admin\tests;

use Firebase\JWT\JWT as FirebaseJwt;
use think\App;

use think\Request;
use PHPUnit\Framework\TestCase;
use youvision\store\admin\support\Jwt;

final class JwtTest extends TestCase
{
    /**
     * @param array<string, mixed> $jwt
     */
    private function app(array $jwt = []): App
    {
        $app = new App(dirname(__DIR__));
        $config = (array) require dirname(__DIR__) . '/src/config.php';
        $config['jwt']['secret'] = str_repeat('k', 32);
        $config['jwt'] = array_replace($config['jwt'], $jwt);

        $app->config->set($config, 'store_admin');
        $app->config->set([
            'default' => 'file',
            'stores' => [
                'file' => ['type' => 'File', 'path' => dirname(__DIR__) . '/runtime/cache/'],
            ],
        ], 'cache');

        return $app;
    }

    public function testIssueAndCheck(): void
    {
        $this->app();

        $token = Jwt::issue(['user_id' => 7, 'username' => 'alice']);
        $result = Jwt::check($token);

        self::assertSame(1, $result['code']);
        self::assertSame(0, $result['error_code']);
        self::assertSame(7, $result['data']['user_id']);
        self::assertSame('alice', $result['data']['username']);
        self::assertFalse($result['need_refresh']);
        self::assertGreaterThan(0, $result['expires_in']);
    }

    public function testTamperedTokenIsRejected(): void
    {
        $this->app();

        $token = Jwt::issue(['user_id' => 7]);
        $result = Jwt::check($token . 'x');

        self::assertSame(0, $result['code']);
        self::assertSame(Jwt::INVALID_TOKEN, $result['error_code']);
    }

    public function testExpiredTokenIsRejected(): void
    {
        $this->app();

        $now = time();
        $token = FirebaseJwt::encode([
            'iat' => $now - 100,
            'nbf' => $now - 100,
            'exp' => $now - 120,
            'jti' => 'expired-token',
            'data' => ['user_id' => 7],
        ], Jwt::secret(), 'HS256', 'expired-token');

        $result = Jwt::check($token);

        self::assertSame(0, $result['code']);
        self::assertSame(Jwt::TOKEN_EXPIRE_LONG, $result['error_code']);
    }

    public function testNeedRefreshWhenRemainingTtlIsBelowThreshold(): void
    {
        $this->app(['ttl' => 10, 'refresh_threshold' => 60]);

        $result = Jwt::check(Jwt::issue(['user_id' => 7]));

        self::assertSame(1, $result['code']);
        self::assertTrue($result['need_refresh']);
    }

    public function testRefreshBlacklistsOldToken(): void
    {
        $this->app();

        $old = Jwt::issue(['user_id' => 7]);
        $refresh = Jwt::refresh($old);

        self::assertSame(1, $refresh['code']);
        self::assertIsString($refresh['data']);
        self::assertNotSame($old, $refresh['data']);

        $oldResult = Jwt::check($old);
        self::assertSame(0, $oldResult['code']);
        self::assertSame(Jwt::TOKEN_LOGOUT, $oldResult['error_code']);

        $newResult = Jwt::check((string) $refresh['data']);
        self::assertSame(1, $newResult['code']);
        self::assertSame(7, $newResult['data']['user_id']);
    }

    public function testLogoutBlacklistsToken(): void
    {
        $this->app();

        $token = Jwt::issue(['user_id' => 7]);
        $logout = Jwt::logout($token);

        self::assertSame(1, $logout['code']);
        self::assertTrue($logout['data']);

        $result = Jwt::check($token);
        self::assertSame(0, $result['code']);
        self::assertSame(Jwt::TOKEN_LOGOUT, $result['error_code']);
    }

    public function testTokenFromRequest(): void
    {
        $token = 'header-token';
        $request = (new Request())->withHeader(['Authorization' => 'Bearer ' . $token]);

        self::assertSame($token, Jwt::tokenFromRequest($request));

        $raw = (new Request())->withHeader(['Authorization' => $token]);
        self::assertSame($token, Jwt::tokenFromRequest($raw));

        self::assertSame('', Jwt::tokenFromRequest(new Request()));
    }

    public function testMissingSecretThrows(): void
    {
        $this->app(['secret' => '']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(Jwt::SECRET_MISS);

        Jwt::issue(['user_id' => 7]);
    }

}