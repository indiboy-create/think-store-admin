<?php

declare(strict_types=1);

namespace youvision\store\admin\service;

use think\App;
use think\Request;
use youvision\store\admin\contract\Repository;
use youvision\store\admin\contract\Store;
use youvision\store\admin\repository\DatabaseRepository;
use youvision\store\admin\store\CacheStore;
use youvision\store\admin\store\NullStore;
use youvision\store\admin\store\SessionStore;
use youvision\store\admin\support\ConditionEvaluator;
use youvision\store\admin\support\RuleMatcher;
use youvision\store\admin\support\admin\Salting;

/**
 * 权限认证核心服务
 */
class Auth
{
    protected App $app;

    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    protected Repository $repository;

    protected Store $store;

    protected RuleMatcher $matcher;

    protected ConditionEvaluator $evaluator;

    /**
     * 当前请求的缓存指纹，用于识别常驻内存环境下的新请求
     */
    protected ?string $requestFingerprint = null;

    public function __construct(App $app, ?Repository $repository = null, ?Store $store = null)
    {
        $this->app = $app;
        $this->config = array_replace_recursive(
            require dirname(__DIR__) . '/config/store_admin.php',
            (array) $app->config->get('store_admin', [])
        );
        $this->repository = $repository ?? new DatabaseRepository($app, $this->config);
        $this->store = $store ?? $this->createStore();
        $this->matcher = new RuleMatcher();
        $this->evaluator = new ConditionEvaluator();
    }

    /**
     * 检查用户是否拥有指定规则
     *
     * @param string|array<int, string> $name     规则名称，支持逗号分隔字符串或数组
     * @param int                       $uid      用户 ID
     * @param int                       $type     规则类型，0 表示不限制
     * @param string                    $mode     认证模式，url 表示同时校验规则中的请求参数
     * @param string                    $relation 多规则关系：or 满足其一即通过，and 需全部满足
     */
    public function check(
        string|array $name,
        int $uid,
        int $type = 1,
        string $mode = 'url',
        string $relation = 'or'
    ): bool {
        if (!(int) $this->config['auth']['on']) {
            return true;
        }

        $names = $this->matcher->normalizeNames($name);

        if ($names === []) {
            return false;
        }

        $params = $mode === 'url' ? (array) $this->app->request->param() : [];

        return $this->matcher->matches(
            $this->getAuthList($uid, $type),
            $names,
            $params,
            $mode,
            $relation,
            (bool) $this->config['auth']['url_lowercase']
        );
    }

    /**
     * 获取用户拥有的全部规则名称
     *
     * @return array<int, string>
     */
    public function rules(int $uid, int $type = 1): array
    {
        return $this->getAuthList($uid, $type);
    }

    /**
     * 获取用户所属角色
     *
     * @param int    $uid   用户 ID
     * @param string $field 指定字段时仅返回该字段值
     * @return array<int, mixed>
     */
    public function roles(int $uid, string $field = ''): array
    {
        return $this->repository->roles($uid, $field);
    }

    /**
     * 校验用户名、邮箱或手机号与密码
     *
     * 成功返回用户 ID，失败返回 0。用户表字段通过 auth.user_fields 映射，
     * 密码算法兼容 think-salting 的 AdminPassword。
     */
    public function verifyPassword(string $login, string $password): int
    {
        $login = trim($login);

        if ($login === '' || $password === '' || !method_exists($this->repository, 'findByLogin')) {
            return 0;
        }

        $user = (array) $this->repository->findByLogin($login);

        if ($user === []) {
            return 0;
        }

        $fields = (array) ($this->config['auth']['user_fields'] ?? []);
        $passwordField = (string) ($fields['password'] ?? 'password');
        $saltField = (string) ($fields['salt'] ?? ($this->config['salting']['salt_field'] ?? 'salt'));
        $salt2Field = (string) ($fields['salt2'] ?? ($this->config['salting']['salt2_field'] ?? 'salt2'));
        $hash = (string) ($user[$passwordField] ?? '');

        if ($hash === '') {
            return 0;
        }

        if (!Salting::verify(
            $password,
            (string) ($user[$saltField] ?? ''),
            (string) ($user[$salt2Field] ?? ''),
            $hash
        )) {
            return 0;
        }

        $pk = (string) ($this->config['auth']['user_pk'] ?? 'id');

        return (int) ($user[$pk] ?? 0);
    }

    /**
     * 清理认证缓存
     *
     * @param int $uid  用户 ID，0 表示全部用户
     * @param int $type 规则类型，0 表示全部类型
     */
    public function flush(int $uid = 0, int $type = 0): void
    {
        if ($uid === 0) {
            $this->saveRequestCache([]);
            $this->store->forget('', $this->cacheKeyPrefix());

            return;
        }

        $cache = $this->requestCache();

        if ($type === 0) {
            foreach (array_keys($cache) as $key) {
                if (str_starts_with((string) $key, $uid . ':')) {
                    unset($cache[$key]);
                }
            }
        } else {
            unset($cache[$uid . ':' . $type]);
        }

        $this->saveRequestCache($cache);

        if ($type === 0) {
            $this->store->forget('', $this->cacheKeyPrefix() . $uid . ':');

            return;
        }

        $this->store->forget($this->cacheKey($uid, $type));
    }

    /**
     * 删除用户的角色关系并清理认证缓存
     *
     * 应用删除用户时应调用该方法，避免用户 ID 复用时继承旧权限。
     */
    public function purgeUser(int $uid): int
    {
        if ($uid <= 0) {
            return 0;
        }

        $count = 0;

        if (method_exists($this->repository, 'purgeUser')) {
            $count = (int) $this->repository->purgeUser($uid);
        }

        $this->flush($uid);

        return $count;
    }

    /**
     * 获取用户的有效规则列表
     *
     * 持久缓存同时保存规则名称与条件字段签名：条件字段发生变化时自动重算，
     * 授权关系变化仍需通过模型事件或 flush() 失效。
     *
     * @return array<int, string>
     */
    protected function getAuthList(int $uid, int $type): array
    {
        $cacheKey = $uid . ':' . $type;
        $requestCache = $this->requestCache();

        if (array_key_exists($cacheKey, $requestCache)) {
            return $requestCache[$cacheKey];
        }

        $storeKey = $this->cacheKey($uid, $type);
        $cached = $this->store->get($storeKey);
        $user = null;

        if ($cached !== null && isset($cached['rules']) && is_array($cached['rules'])) {
            $signature = isset($cached['signature']) && is_array($cached['signature']) ? $cached['signature'] : [];

            if ($signature === []) {
                return $this->rememberRequestCache($cacheKey, array_values(array_map('strval', $cached['rules'])));
            }

            $user = $this->repository->user($uid);

            if ($this->signatureMatches($signature, $user)) {
                return $this->rememberRequestCache($cacheKey, array_values(array_map('strval', $cached['rules'])));
            }
        }

        $roleIds = $this->repository->roleIds($uid);

        if ($roleIds === []) {
            return $this->rememberAuthList($cacheKey, $storeKey, [], []);
        }

        $user ??= $this->repository->user($uid);
        $rules = $this->repository->rulesForRoles($roleIds, $type);
        $authList = [];
        $signature = [];

        foreach ($rules as $rule) {
            $ruleName = strtolower(trim((string) ($rule['name'] ?? '')));

            if ($ruleName === '') {
                continue;
            }

            $condition = trim((string) ($rule['condition'] ?? ''));

            foreach ($this->evaluator->fields($condition) as $field) {
                if (array_key_exists($field, $user)) {
                    $signature[$field] = $this->signatureValue($user[$field]);
                }
            }

            if ($condition === '' || $this->evaluator->evaluate($condition, $user)) {
                $authList[] = $ruleName;
            }
        }

        return $this->rememberAuthList($cacheKey, $storeKey, array_values(array_unique($authList)), $signature);
    }

    /**
     * @param array<int, string>    $authList
     * @param array<string, string> $signature
     * @return array<int, string>
     */
    protected function rememberAuthList(string $cacheKey, string $storeKey, array $authList, array $signature): array
    {
        $this->rememberRequestCache($cacheKey, $authList);
        $this->store->put($storeKey, [
            'rules' => $authList,
            'signature' => $signature,
        ], (int) $this->config['auth']['cache_ttl']);

        return $authList;
    }

    /**
     * @param array<int, string> $authList
     * @return array<int, string>
     */
    protected function rememberRequestCache(string $cacheKey, array $authList): array
    {
        $cache = $this->requestCache();
        $cache[$cacheKey] = $authList;
        $this->saveRequestCache($cache);

        return $authList;
    }

    /**
     * @param array<string, string> $signature
     * @param array<string, mixed>  $user
     */
    protected function signatureMatches(array $signature, array $user): bool
    {
        foreach ($signature as $field => $hash) {
            if (!array_key_exists($field, $user)) {
                return false;
            }

            if ($hash !== $this->signatureValue($user[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $value
     */
    protected function signatureValue($value): string
    {
        return hash('sha256', serialize($value));
    }

    /**
     * 请求内缓存默认启用；没有 HTTP 请求上下文时自动关闭
     */
    protected function requestCacheEnabled(): bool
    {
        if (!(bool) ($this->config['auth']['request_cache'] ?? true)) {
            return false;
        }

        return $this->currentRequest() !== null;
    }

    protected function currentRequest(): ?Request
    {
        try {
            $request = $this->app->request;
        } catch (\Throwable) {
            return null;
        }

        if ($request === null || (string) $request->server('REQUEST_METHOD') === '') {
            return null;
        }

        return $request;
    }

    /**
     * 请求内缓存挂在 Request 对象上，请求结束即回收，不会跨请求保留
     *
     * @return array<string, array<int, string>>
     */
    protected function requestCache(): array
    {
        if (!$this->requestCacheEnabled()) {
            return [];
        }

        $request = $this->currentRequest();

        if ($request === null) {
            return [];
        }

        $fingerprint = $this->requestFingerprintValue($request);

        if ($fingerprint !== $this->requestFingerprint) {
            $this->requestFingerprint = $fingerprint;
            $request->store_admin_list = [];
        }

        $cache = $request->middleware('store_admin_list', []);

        return is_array($cache) ? $cache : [];
    }

    /**
     * @param array<string, array<int, string>> $cache
     */
    protected function saveRequestCache(array $cache): void
    {
        if (!$this->requestCacheEnabled()) {
            return;
        }

        $request = $this->currentRequest();

        if ($request !== null) {
            $request->store_admin_list = $cache;
        }
    }

    /**
     * 识别常驻内存环境下复用的 Request 对象
     */
    protected function requestFingerprintValue(Request $request): string
    {
        return spl_object_id($request)
            . '|' . (string) $request->server('REQUEST_TIME_FLOAT')
            . '|' . $request->method()
            . '|' . $request->url();
    }

    protected function createStore(): Store
    {
        if ((int) $this->config['auth']['type'] !== 2) {
            return new NullStore();
        }

        $store = strtolower(trim((string) $this->config['auth']['cache_store']));

        if ($store === 'cache') {
            return new CacheStore();
        }

        if ($store === 'session') {
            return new SessionStore();
        }

        return new NullStore();
    }

    protected function cacheKey(int $uid, int $type): string
    {
        return $this->cacheKeyPrefix() . $uid . ':' . $type;
    }

    protected function cacheKeyPrefix(): string
    {
        $prefix = trim((string) $this->config['auth']['cache_prefix'], ':');

        return $prefix === '' ? 'list:' : $prefix . ':list:';
    }
}