<?php

declare(strict_types=1);

namespace youvision\store\admin;

/**
 * 主题元数据与可见地址
 *
 * 页面、接口与静态资源地址都由这里推导，修改 route.prefix、
 * route.segment 或 assets 后模板与页面脚本会跟随变化。
 */
final class Theme
{
    public const DEFAULT_SEGMENT = 'think-store-admin';

    protected string $packageRoot;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(protected array $config = [], string $packageRoot = '')
    {
        $root = $packageRoot !== '' ? $packageRoot : dirname(__DIR__);
        $this->packageRoot = rtrim(str_replace('\\', '/', $root), '/');
    }

    /**
     * 主题页面根地址，例如 /store/think-store-admin
     */
    public function base(): string
    {
        $prefix = trim((string) ($this->config['route']['prefix'] ?? 'store'), '/');
        $segment = trim((string) ($this->config['route']['segment'] ?? self::DEFAULT_SEGMENT), '/');

        return ($prefix === '' ? '' : '/' . $prefix) . ($segment === '' ? '' : '/' . $segment);
    }

    /**
     * 权限总览页地址
     */
    public function home(): string
    {
        return $this->base() . '/index';
    }

    /**
     * 包内静态资源地址
     *
     * assets 为相对路径时拼在包内命名空间后；以 / 开头时视为外部托管的绝对地址。
     */
    public function assets(): string
    {
        $raw = trim((string) ($this->config['assets'] ?? 'assets'));

        if ($raw === '') {
            $raw = 'assets';
        }

        if (str_starts_with($raw, '/')) {
            return rtrim($raw, '/');
        }

        return $this->base() . '/' . trim($raw, '/');
    }

    /**
     * 用户权限查询接口地址
     */
    public function apiUser(): string
    {
        return $this->base() . '/api/user';
    }

    /**
     * 总览页默认查询的用户 ID
     */
    public function themeUser(): int
    {
        return max(0, (int) ($this->config['theme_user'] ?? 0));
    }

    /**
     * 主题访问模式：auth 需登录，public 公开
     */
    public function accessMode(): string
    {
        $mode = strtolower(trim((string) ($this->config['theme_access'] ?? 'auth')));

        return $mode === 'public' ? 'public' : 'auth';
    }

    /**
     * 查看其他用户权限所需规则，空字符串表示仅允许查询当前登录用户
     */
    public function permission(): string
    {
        return trim((string) ($this->config['theme_permission'] ?? ''));
    }

    /**
     * 当前包版本，优先读取 Composer 安装信息，回退到包内 composer.json。
     */
    public function version(): string
    {
        if (class_exists(\Composer\InstalledVersions::class)) {
            try {
                $version = \Composer\InstalledVersions::getPrettyVersion('youvision/think-store-admin');

                if (is_string($version) && $version !== '') {
                    return $version;
                }
            } catch (\Throwable) {
            }
        }

        $file = $this->packageRoot . '/composer.json';

        if (!is_file($file)) {
            return 'dev';
        }

        $json = json_decode((string) file_get_contents($file), true);
        $version = is_array($json) ? (string) ($json['version'] ?? '') : '';

        return $version !== '' ? $version : 'dev';
    }

    /**
     * 页面展示的配置项
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function configSummary(): array
    {
        $config = $this->config;

        return [
            ['label' => '认证开关', 'value' => (int) ($config['auth_on'] ?? 1) === 1 ? '已开启' : '已关闭'],
            ['label' => '认证方式', 'value' => (int) ($config['auth_type'] ?? 1) === 2 ? '持久缓存认证' : '实时认证'],
            ['label' => '缓存驱动', 'value' => (string) ($config['auth_cache_store'] ?? 'cache')],
            ['label' => '缓存前缀', 'value' => (string) ($config['auth_cache_prefix'] ?? 'store_admin')],
            ['label' => '请求内缓存', 'value' => (bool) ($config['auth_request_cache'] ?? true) ? '启用' : '关闭'],
            ['label' => '用户表', 'value' => (string) ($config['auth_user'] ?? 'member')],
            ['label' => '用户主键', 'value' => (string) ($config['auth_user_pk'] ?? 'id')],
            ['label' => '用户模型', 'value' => (string) ($config['auth_user_model'] ?? '') !== '' ? (string) $config['auth_user_model'] : '未指定，使用用户表查询'],
            ['label' => 'URL 参数大小写', 'value' => (bool) ($config['auth_url_lowercase'] ?? true) ? '忽略大小写' : '区分大小写'],
            ['label' => '主题访问', 'value' => $this->accessMode() === 'public' ? '公开（不推荐生产）' : '需登录'],
            ['label' => '跨用户查询', 'value' => $this->permission() !== '' ? '需规则 ' . $this->permission() : '仅本人'],
            ['label' => '总览页地址', 'value' => $this->home()],
        ];
    }

    /**
     * 认证数据表说明
     *
     * @return array<int, array{name: string, title: string, desc: string}>
     */
    public function tables(): array
    {
        return [
            ['name' => 'store_admin_rule', 'title' => '权限规则', 'desc' => '规则标识、类型、状态与条件表达式'],
            ['name' => 'store_admin_role', 'title' => '权限角色', 'desc' => '角色名称与启用状态'],
            ['name' => 'store_admin_role_user', 'title' => '用户角色关系', 'desc' => '用户与角色的多对多关联'],
            ['name' => 'store_admin_role_rule', 'title' => '角色规则关系', 'desc' => '角色与规则的关联，决定角色权限'],
        ];
    }

    /**
     * 主题路由清单
     *
     * @return array<int, array{method: string, path: string, name: string}>
     */
    public function routes(): array
    {
        return [
            ['method' => 'GET', 'path' => $this->home(), 'name' => '权限总览'],
            ['method' => 'GET', 'path' => $this->apiUser(), 'name' => '用户权限查询接口'],
            ['method' => 'GET', 'path' => $this->assets() . '/<path>', 'name' => '主题静态资源'],
        ];
    }

    /**
     * 包元数据
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function meta(): array
    {
        return [
            ['label' => '包名', 'value' => 'youvision/think-store-admin'],
            ['label' => '版本', 'value' => $this->version()],
            ['label' => 'PHP', 'value' => '^8.0'],
            ['label' => 'ThinkPHP', 'value' => '^8.0'],
            ['label' => '视图驱动', 'value' => 'topthink/think-view ^2.0'],
            ['label' => '静态资源', 'value' => 'theme/assets · 包内路由输出'],
        ];
    }
}