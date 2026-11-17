<?php

declare(strict_types=1);

return [
    // 权限认证总开关：1 开启，0 关闭。关闭后 check() 一律返回 true
    'auth_on' => 1,

    // 认证方式：1 实时认证，每次认证都查询数据库；
    // 2 持久缓存认证，规则列表写入 auth_cache_store 指定的存储
    'auth_type' => 1,

    // 认证方式为 2 时的持久缓存驱动：cache（共享存储，适合常驻内存多 Worker）或 session（仅单进程）
    'auth_cache_store' => 'cache',

    // 持久缓存有效期（秒），0 表示不设置过期时间
    'auth_cache_ttl' => 0,

    // 缓存键前缀
    'auth_cache_prefix' => 'store_auth',

    // 是否启用请求内规则缓存；无 HTTP 请求上下文时自动关闭
    'auth_request_cache' => true,

    // 用户表名（不含数据库前缀），用于读取用户字段并解析规则 condition 中的 {字段名}
    // 例如用户表为 think_member 时，此处填写 member
    'auth_user' => 'member',

    // 用户表主键
    'auth_user_pk' => 'id',

    // 用户模型类名，为空时直接使用 auth_user 表查询
    'auth_user_model' => '',

    // URL 参数认证时是否忽略大小写，保持与原 think-auth 行为一致
    'auth_url_lowercase' => true,

    // 中间件无需认证的规则，支持 * 通配符
    'auth_middleware_allow' => [],

    // 中间件获取登录用户 ID 的请求属性名
    'auth_middleware_uid' => 'user_id',

    // 中间件规则前缀，例如 admin 时规则为 admin/user/index
    'auth_middleware_prefix' => '',

    // 中间件未授权时是否统一返回 JSON
    'auth_middleware_json' => false,

    // 权限总览主题路由
    'route' => [
        // 是否注册内置主题路由
        'enabled' => true,

        // 路由前缀，留空表示不添加前缀，例如 store
        'prefix' => 'store',

        // 包内命名空间，留空表示页面直接挂在路由前缀下
        'segment' => 'think-store-auth',

        // 应用到整组主题路由的中间件，例如 [\app\middleware\Auth::class]
        'middleware' => [],
    ],

    // 模板目录：留空使用包内 theme/view；填写时相对路径从项目根目录算起
    'view' => '',

    // 静态资源地址：相对路径会拼在包内命名空间后，以 / 开头时视为绝对地址
    'assets' => 'assets',

    // 总览页默认查询的用户 ID，0 表示当前登录用户
    'theme_user' => 0,

    // 主题访问控制：auth 需登录，public 公开（仅用于本地演示，不推荐生产）
    'theme_access' => 'auth',

    // 查看其他用户权限所需规则，为空表示仅允许查询当前登录用户
    'theme_permission' => '',
];