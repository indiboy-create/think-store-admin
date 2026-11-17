<?php

declare(strict_types=1);

return [

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

    // 认证与 RBAC 配置
    'auth' => [
        // 权限认证总开关：1 开启，0 关闭。关闭后 check() 一律返回 true
        'on' => 1,

        // 认证方式：1 实时认证，每次认证都查询数据库；
        // 2 持久缓存认证，规则列表写入 auth.cache_store 指定的存储
        'type' => 1,

        // 认证方式为 2 时的持久缓存驱动：cache（共享存储，适合常驻内存多 Worker）或 session（仅单进程）
        'cache_store' => 'cache',

        // 持久缓存有效期（秒），0 表示不设置过期时间
        'cache_ttl' => 0,

        // 缓存键前缀
        'cache_prefix' => 'store_admin',

        // 是否启用请求内规则缓存；无 HTTP 请求上下文时自动关闭
        'request_cache' => true,

        // 用户表名（不含数据库前缀），用于读取用户字段并解析规则 condition 中的 {字段名}
        // 例如用户表为 think_member 时，此处填写 member
        'user' => 'member',

        // 用户表主键
        'user_pk' => 'id',

        // 用户模型类名，为空时直接使用 auth.user 表查询
        'user_model' => '',

        // 用户表字段映射；auth.user 表需包含 email、mobile、username、password、salt、salt2
        'user_fields' => [
            'email' => 'email',
            'mobile' => 'mobile',
            'username' => 'username',
            'password' => 'password',
            'salt' => 'salt',
            'salt2' => 'salt2',
        ],

        // URL 参数认证时是否忽略大小写
        'url_lowercase' => true,

        // 中间件无需认证的规则，支持 * 通配符
        'middleware_allow' => [],

        // 中间件获取登录用户 ID 的请求属性名
        'middleware_uid' => 'user_id',

        // 中间件规则前缀，例如 admin 时规则为 admin/user/index
        'middleware_prefix' => '',

        // 中间件未授权时是否统一返回 JSON
        'middleware_json' => false,
    ],

    // 密码加盐配置，兼容 think-salting 的 AdminPassword 算法
    'salting' => [
        'prefix' => 'Y!o^U',
        'suffix' => 'V,is*Io(N',
        'charset' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+[]{};:,.<>?/~|',
        'length' => 8,
        'length2' => 10,
        'salt_field' => 'salt',
        'salt2_field' => 'salt2',
    ],

    // JWT 认证配置
    'jwt' => [
        // 签名密钥，必须设置，建议通过环境变量注入
        'secret' => '',
        'alg' => 'HS256',
        'issuer' => '',
        'audience' => '',
        // Token 有效期（秒）
        'ttl' => 60 * 60 * 24 * 7,
        // 剩余有效期低于该值时提示刷新（秒）
        'refresh_threshold' => 60 * 60 * 10,
        // 时钟偏差容忍（秒）
        'leeway' => 60,
        // 注销黑名单缓存前缀
        'blacklist_prefix' => 'store_admin:jwt:blacklist:',
        // Token 中用户 ID 的声明名
        'uid_claim' => 'user_id',
    ],

    // Layui 树形与表格工具配置
    'layui' => [
        // 主键字段名
        'id' => 'id',

        // 父级字段名
        'pid' => 'pid',

        // 名称字段名
        'title' => 'title',

        // 子级字段名
        'children' => 'children',

        // Layui dropdown 字段映射
        'dropdown' => [
            'id' => 'id',
            'title' => 'title',
            'child' => 'child',
            'source_child' => 'children',
        ],
    ],

    // 上传接口配置
    'upload' => [
        // 是否启用上传接口
        'enabled' => true,

        // 上传文件域名称，与 Layui upload 的 field 保持一致
        'field' => 'file',

        // topthink/think-filesystem 的磁盘名
        'disk' => 'public',

        // 存储路径模板，支持 {date}、{Y}、{m}、{d}
        'path' => 'uploads/{date}',

        // 磁盘未配置 url 时使用的 URL 前缀
        'url_prefix' => '/storage',

        // 允许的文件后缀
        'allowed_ext' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
            'pdf', 'zip', 'rar', '7z',
            'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt',
        ],

        // 禁止的文件后缀，优先于 allowed_ext
        'denied_ext' => [
            'php', 'phtml', 'phar', 'jsp', 'asp', 'aspx',
            'exe', 'sh', 'bat', 'cmd', 'html', 'htm', 'svg', 'js', 'xml',
        ],

        // 允许的 MIME 类型，为空表示不额外限制
        'allowed_mime' => [],

        // 单个文件最大字节数
        'max_size' => 10 * 1024 * 1024,

        // 单次最多上传文件数
        'max_files' => 9,

        // 上传接口中间件，默认要求 JWT 登录
        'middleware' => [
            \youvision\store\admin\middleware\JwtAuth::class,
        ],
    ],

    // 权限总览主题路由
    'route' => [
        // 是否注册内置主题路由
        'enabled' => true,

        // 路由前缀，留空表示不添加前缀，例如 store
        'prefix' => 'store',

        // 包内命名空间，留空表示页面直接挂在路由前缀下
        'segment' => 'think-store-admin',

        // 应用到整组主题路由的中间件，例如 [\app\middleware\Auth::class]
        'middleware' => [],
    ],
];