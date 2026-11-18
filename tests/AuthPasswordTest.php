<?php

declare(strict_types=1);

namespace youvision\store\admin\tests;

use think\App;
use PHPUnit\Framework\TestCase;
use youvision\store\admin\service\Auth;
use youvision\store\admin\support\admin\Salting;
use youvision\store\admin\tests\Support\FakeRepository;

final class AuthPasswordTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function auth(FakeRepository $repository, array $config = []): Auth
    {
        $app = new App(dirname(__DIR__));
        $app->config->set(
            array_replace_recursive((array) require dirname(__DIR__) . '/src/config.php', $config),
            'store_admin'
        );

        return new Auth($app, $repository);
    }

    public function testVerifyPasswordByUsernameEmailAndMobile(): void
    {
        $salt = 'salt-1';
        $salt2 = 'salt-2';
        $row = [
            'id' => 7,
            'username' => 'alice',
            'email' => 'alice@example.com',
            'mobile' => '13800000000',
            'password' => Salting::encode('secret', $salt, $salt2),
            'salt' => $salt,
            'salt2' => $salt2,
        ];

        $repository = new FakeRepository();
        $repository->loginUsers = [
            'alice' => $row,
            'alice@example.com' => $row,
            '13800000000' => $row,
        ];

        $auth = $this->auth($repository);

        self::assertSame(7, $auth->verifyPassword('alice', 'secret'));
        self::assertSame(7, $auth->verifyPassword('alice@example.com', 'secret'));
        self::assertSame(7, $auth->verifyPassword('13800000000', 'secret'));
        self::assertSame(0, $auth->verifyPassword('alice', 'wrong'));
        self::assertSame(0, $auth->verifyPassword('unknown', 'secret'));
        self::assertSame(0, $auth->verifyPassword('', 'secret'));
        self::assertSame(0, $auth->verifyPassword('alice', ''));
    }

    public function testCustomUserFieldMapping(): void
    {
        $salt = 'salt-1';
        $salt2 = 'salt-2';
        $repository = new FakeRepository();
        $repository->loginUsers['bob'] = [
            'uid' => 9,
            'user_name' => 'bob',
            'pwd' => Salting::encode('secret', $salt, $salt2),
            's1' => $salt,
            's2' => $salt2,
        ];

        $auth = $this->auth($repository, [
            'auth' => [
                'user_pk' => 'uid',
                'user_fields' => [
                    'username' => 'user_name',
                    'email' => '',
                    'mobile' => '',
                    'password' => 'pwd',
                    'salt' => 's1',
                    'salt2' => 's2',
                ],
            ],
        ]);

        self::assertSame(9, $auth->verifyPassword('bob', 'secret'));
    }

    public function testMissingHashFails(): void
    {
        $repository = new FakeRepository();
        $repository->loginUsers['nohash'] = [
            'id' => 1,
            'username' => 'nohash',
            'password' => '',
            'salt' => 'salt',
            'salt2' => 'salt2',
        ];

        self::assertSame(0, $this->auth($repository)->verifyPassword('nohash', 'secret'));
    }
}