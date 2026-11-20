<?php

declare(strict_types=1);

namespace youvision\store\admin\tests;

use think\App;
use think\Request;
use PHPUnit\Framework\TestCase;
use youvision\store\admin\contract\Store;
use youvision\store\admin\service\Auth;
use youvision\store\admin\tests\Support\FakeRepository;
use youvision\store\admin\tests\Support\MemoryStore;

final class AuthTest extends TestCase
{
    /**
     * @param array<string, mixed>  $config
     * @param array<string, mixed>  $get
     */
    private function makeAuth(
        array $config = [],
        ?FakeRepository $repository = null,
        ?Store $store = null,
        array $get = []
    ): Auth {
        $app = new App(dirname(__DIR__));
        $app->config->set(
            array_replace_recursive(
                (array) require dirname(__DIR__) . '/src/config/store_admin.php',
                ['auth' => ['type' => 1]],
                $config
            ),
            'store_admin'
        );

        $request = (new Request())->withGet($get);
        $app->instance('request', $request);
        $app->instance(Request::class, $request);

        return new Auth($app, $repository, $store);
    }

    public function testCheckPassesWithOwnedRule(): void
    {
        $repository = new FakeRepository();
        $repository->roleIds = [1 => [10]];
        $repository->rules = [['name' => 'Admin/User/Index', 'type' => 1]];

        $auth = $this->makeAuth([], $repository);

        self::assertTrue($auth->check('admin/user/index', 1));
        self::assertSame(['admin/user/index'], $auth->rules(1));
    }

    public function testCheckFailsWithoutRole(): void
    {
        $repository = new FakeRepository();
        $repository->rules = [['name' => 'user/index']];

        $auth = $this->makeAuth([], $repository);

        self::assertFalse($auth->check('user/index', 1));
        self::assertSame([], $auth->rules(1));
    }

    public function testAuthOffAlwaysPasses(): void
    {
        $auth = $this->makeAuth(['auth' => ['on' => 0]], new FakeRepository());

        self::assertTrue($auth->check('any/rule', 1));
    }

    public function testConditionFiltersRule(): void
    {
        $repository = new FakeRepository();
        $repository->roleIds = [1 => [10]];
        $repository->rules = [
            ['name' => 'grade1', 'condition' => '{score}<100'],
            ['name' => 'grade2', 'condition' => '{score}>=100 and {score}<200'],
            ['name' => 'grade3', 'condition' => '{score}>=200'],
        ];

        $repository->users = [1 => ['id' => 1, 'score' => 150]];
        $auth = $this->makeAuth([], $repository);

        self::assertFalse($auth->check('grade1', 1));
        self::assertTrue($auth->check('grade2', 1));
        self::assertFalse($auth->check('grade3', 1));
        self::assertSame(['grade2'], $auth->rules(1));
    }

    public function testUrlParamAuthentication(): void
    {
        $repository = new FakeRepository();
        $repository->roleIds = [1 => [10]];
        $repository->rules = [['name' => 'user/index?type=admin']];

        $auth = $this->makeAuth([], $repository, null, ['type' => 'Admin']);

        self::assertTrue($auth->check('user/index', 1));

        $auth = $this->makeAuth([], $repository, null, ['type' => 'user']);

        self::assertFalse($auth->check('user/index', 1));
    }

    public function testAndRelation(): void
    {
        $repository = new FakeRepository();
        $repository->roleIds = [1 => [10]];
        $repository->rules = [['name' => 'user/add'], ['name' => 'user/edit']];

        $auth = $this->makeAuth([], $repository);

        self::assertTrue($auth->check('user/add,user/edit', 1, 1, 'url', 'and'));
        self::assertTrue($auth->check(['user/add', 'user/edit'], 1, 1, 'url', 'and'));
        self::assertFalse($this->makeAuth(
            [],
            $repository,
            null
        )->check('user/add,user/delete', 1, 1, 'url', 'and'));
    }

    public function testTypeFilter(): void
    {
        $repository = new FakeRepository();
        $repository->roleIds = [1 => [10]];
        $repository->rules = [
            ['name' => 'user/index', 'type' => 1],
            ['name' => 'system/config', 'type' => 2],
        ];

        $auth = $this->makeAuth([], $repository);

        self::assertTrue($auth->check('user/index', 1));
        self::assertFalse($auth->check('system/config', 1));
        self::assertTrue($auth->check('system/config', 1, 2));
        self::assertTrue($auth->check('system/config', 1, 0));
        self::assertSame(['user/index', 'system/config'], $auth->rules(1, 0));
    }

    public function testPersistentCache(): void
    {
        $repository = new FakeRepository();
        $repository->roleIds = [1 => [10]];
        $repository->rules = [['name' => 'user/index']];
        $store = new MemoryStore();

        $auth = $this->makeAuth(['auth' => ['type' => 2]], $repository, $store);

        self::assertTrue($auth->check('user/index', 1));
        self::assertSame(1, $repository->ruleCalls);
        self::assertSame(1, $repository->userCalls);
        self::assertSame(['user/index'], $store->data['store_admin:list:1:1']['rules']);
        self::assertSame([], $store->data['store_admin:list:1:1']['signature']);

        unset($auth);
        $auth = $this->makeAuth(['auth' => ['type' => 2]], $repository, $store);

        self::assertTrue($auth->check('user/index', 1));
        self::assertSame(1, $repository->ruleCalls);

        $auth->flush(1);

        self::assertTrue($auth->check('user/index', 1));
        self::assertSame(2, $repository->ruleCalls);
    }

    public function testCachedRulesSkipRepository(): void
    {
        $repository = new FakeRepository();
        $store = new MemoryStore();
        $store->put('store_admin:list:1:1', [
            'rules' => ['cached/rule'],
            'signature' => [],
        ]);

        $auth = $this->makeAuth(['auth' => ['type' => 2]], $repository, $store);

        self::assertTrue($auth->check('cached/rule', 1));
        self::assertSame(0, $repository->roleIdCalls);
        self::assertSame(0, $repository->ruleCalls);
    }

    public function testFlushAllClearsCache(): void
    {
        $repository = new FakeRepository();
        $repository->roleIds = [1 => [10], 2 => [11]];
        $repository->rules = [['name' => 'user/index']];
        $store = new MemoryStore();

        $auth = $this->makeAuth(['auth' => ['type' => 2]], $repository, $store);

        $auth->rules(1);
        $auth->rules(2);

        self::assertNotEmpty($store->data);

        $auth->flush();

        self::assertSame([], $store->data);

        $auth->rules(1);

        self::assertSame(3, $repository->ruleCalls);
    }

    public function testPersistentCacheRecalculatesWhenConditionFieldChanges(): void
    {
        $repository = new FakeRepository();
        $repository->roleIds = [1 => [10]];
        $repository->rules = [['name' => 'grade2', 'condition' => '{score}>=100']];
        $repository->users = [1 => ['id' => 1, 'score' => 50]];
        $store = new MemoryStore();

        $auth = $this->makeAuth(['auth' => ['type' => 2]], $repository, $store);

        self::assertFalse($auth->check('grade2', 1));
        self::assertSame(1, $repository->ruleCalls);
        self::assertSame(1, $repository->userCalls);
        self::assertArrayHasKey('score', $store->data['store_admin:list:1:1']['signature']);

        unset($auth);
        $repository->users = [1 => ['id' => 1, 'score' => 150]];
        $auth = $this->makeAuth(['auth' => ['type' => 2]], $repository, $store);

        self::assertTrue($auth->check('grade2', 1));
        self::assertSame(2, $repository->ruleCalls);
        self::assertSame(2, $repository->userCalls);
        self::assertSame(['grade2'], $store->data['store_admin:list:1:1']['rules']);

        unset($auth);
        $auth = $this->makeAuth(['auth' => ['type' => 2]], $repository, $store);

        self::assertTrue($auth->check('grade2', 1));
        self::assertSame(2, $repository->ruleCalls);
        self::assertSame(3, $repository->userCalls);
    }

    public function testPurgeUserClearsRelationsAndCache(): void
    {
        $repository = new FakeRepository();
        $repository->roleIds = [1 => [10]];
        $repository->rules = [['name' => 'user/index']];
        $store = new MemoryStore();

        $auth = $this->makeAuth(['auth' => ['type' => 2]], $repository, $store);
        $auth->rules(1);

        self::assertNotEmpty($store->data);
        self::assertSame(2, $auth->purgeUser(1));
        self::assertSame([1], $repository->purgedUsers);
        self::assertSame([], $store->data);
        self::assertSame(0, $auth->purgeUser(0));
        self::assertSame([1], $repository->purgedUsers);
    }

    public function testRequestCacheDoesNotCrossRequests(): void
    {
        $repository = new FakeRepository();
        $repository->roleIds = [1 => [10]];
        $repository->rules = [['name' => 'user/index']];

        $app = new App(dirname(__DIR__));
        $app->config->set(
            array_replace_recursive(
                (array) require dirname(__DIR__) . '/src/config/store_admin.php',
                ['auth' => ['type' => 1]]
            ),
            'store_admin'
        );

        $request = (new Request())->withGet([])->withServer([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/store/think-store-admin/index',
        ]);
        $app->instance('request', $request);
        $app->instance(Request::class, $request);

        $auth = new TestableAuth($app, $repository);

        self::assertTrue($auth->check('user/index', 1));
        self::assertSame(1, $repository->ruleCalls);

        // 同一请求内复用请求缓存
        $repository->rules = [];

        self::assertTrue($auth->check('user/index', 1));
        self::assertSame(1, $repository->ruleCalls);

        // 模拟常驻内存环境下的下一个请求
        $auth->useRequest((new Request())->withGet([])->withServer([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/store/think-store-admin/index',
        ]));

        self::assertFalse($auth->check('user/index', 1));
        self::assertSame(2, $repository->ruleCalls);
    }

    public function testRequestCacheCanBeDisabled(): void
    {
        $repository = new FakeRepository();
        $repository->roleIds = [1 => [10]];
        $repository->rules = [['name' => 'user/index']];

        $app = new App(dirname(__DIR__));
        $app->config->set(array_replace_recursive(
            (array) require dirname(__DIR__) . '/src/config/store_admin.php',
            ['auth' => ['type' => 1, 'request_cache' => false]]
        ), 'store_admin');

        $request = (new Request())->withGet([])->withServer([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/store/think-store-admin/index',
        ]);
        $app->instance('request', $request);
        $app->instance(Request::class, $request);

        $auth = new TestableAuth($app, $repository);

        self::assertTrue($auth->check('user/index', 1));
        self::assertTrue($auth->check('user/index', 1));
        self::assertSame(2, $repository->ruleCalls);
    }

    public function testRoles(): void
    {
        $repository = new FakeRepository();
        $repository->roles = [1 => [
            ['role_id' => 10, 'role' => ['title' => '内容管理员']],
            ['role_id' => 20, 'role' => ['title' => '运营']],
        ]];

        $auth = $this->makeAuth([], $repository);

        self::assertSame($repository->roles[1], $auth->roles(1));
        self::assertSame([10, 20], $auth->roles(1, 'role_id'));
    }

    public function testEmptyRuleName(): void
    {
        $auth = $this->makeAuth([], new FakeRepository());

        self::assertFalse($auth->check('', 1));
        self::assertFalse($auth->check(' , ', 1));
        self::assertFalse($auth->check([], 1));
    }

    public function testInvalidRelation(): void
    {
        $repository = new FakeRepository();
        $repository->roleIds = [1 => [10]];
        $repository->rules = [['name' => 'user/index']];

        $auth = $this->makeAuth([], $repository);

        self::assertFalse($auth->check('user/index', 1, 1, 'url', 'xor'));
    }

    public function testRulesAreUniqueAndLowercase(): void
    {
        $repository = new FakeRepository();
        $repository->roleIds = [1 => [10, 11]];
        $repository->rules = [
            ['name' => 'User/Index'],
            ['name' => 'user/index'],
            ['name' => 'USER/EDIT'],
        ];

        $auth = $this->makeAuth([], $repository);

        self::assertSame(['user/index', 'user/edit'], $auth->rules(1));
    }
}
final class TestableAuth extends Auth
{
    protected function requestCacheEnabled(): bool
    {
        return (bool) ($this->config['auth']['request_cache'] ?? true);
    }

    public function useRequest(Request $request): void
    {
        $this->app->instance('request', $request);
        $this->app->instance(Request::class, $request);
    }
}