# think-store-auth

ThinkPHP 8 权限认证扩展，基于规则（Rule）、角色（Role）、用户角色关系（RoleUser）和角色规则关系（RoleRule）实现 RBAC 权限控制。支持按钮级、接口级、控制器/方法级认证，并可按用户字段设置条件规则。

本项目参考 [indiboy-create/think-auth](https://github.com/indiboy-create/think-auth) 重写，包名、命名空间、数据表、配置名和命令行均使用 `store` 前缀，可与应用中其它 auth 扩展共存。

包内同时提供基于 layui 的权限总览主题：页面由包内 View 渲染，静态资源通过内置 assets 路由输出。接入登录中间件后访问 `/store/think-store-auth/index`，即可查看运行配置、数据表、路由清单，并按用户 ID 只读查询规则与角色；本地演示可设置 `theme_access = public`。

## 环境要求

- PHP >= 8.0
- ThinkPHP >= 8.0
- `topthink/think-view` ^2.0（随本包自动安装）

## 安装

```bash
composer require youvision/think-store-auth
```

服务与配置会自动注册。如果配置未生成或服务未生效，可执行：

```bash
php think service:discover
php think vendor:publish
```

安装后可直接访问 `/store/think-store-auth/index` 查看权限总览页。主题路由由服务在 `boot()` 阶段注册，宿主项目无需复制视图或静态资源。

## 配置

配置文件位于 `config/store_auth.php`，也可以执行以下命令生成：

```bash
php think store:auth:config
```

如果文件已存在需要覆盖，追加 `--force`：

```bash
php think store:auth:config --force
```

配置项说明：

| 配置项 | 默认值 | 说明 |
| --- | --- | --- |
| `auth_on` | `1` | 权限认证总开关。`1` 开启，`0` 关闭；关闭后 `check()` 一律返回 `true` |
| `auth_type` | `1` | 认证方式。`1` 实时认证，每次认证都查询数据库；`2` 持久缓存认证 |
| `auth_cache_store` | `cache` | `auth_type = 2` 时的持久缓存驱动：`cache`（Redis/共享文件，推荐常驻内存与多 Worker）、`session`（仅单进程）、`none` |
| `auth_cache_ttl` | `0` | 持久缓存有效期（秒），`0` 表示永久 |
| `auth_cache_prefix` | `store_auth` | 缓存键前缀 |
| `auth_request_cache` | `true` | 是否启用请求内规则缓存；无 HTTP 请求上下文时自动关闭 |
| `auth_user` | `member` | 用户表名（不含数据库前缀），用于读取用户字段并解析条件中的 `{字段}` |
| `auth_user_pk` | `id` | 用户表主键 |
| `auth_user_model` | 空 | 用户模型类名。留空时直接使用 `auth_user` 表查询 |
| `auth_url_lowercase` | `true` | URL 参数认证时是否忽略大小写 |
| `auth_middleware_allow` | `[]` | 中间件无需认证的规则，支持 `*` 通配符 |
| `auth_middleware_uid` | `user_id` | 中间件读取登录用户 ID 的请求属性名 |
| `auth_middleware_prefix` | 空 | 中间件规则前缀，例如 `admin` |
| `auth_middleware_json` | `false` | 未授权时是否统一返回 JSON |
| `route.enabled` | `true` | 是否注册权限总览主题路由 |
| `route.prefix` | `store` | 主题路由前缀，留空表示不添加前缀 |
| `route.segment` | `think-store-auth` | 包内命名空间，留空表示页面直接挂在路由前缀下 |
| `route.middleware` | `[]` | 应用到整组主题路由的中间件，例如登录或权限中间件 |
| `view` | 空 | 模板目录；留空使用包内 `theme/view`，填写时相对路径从项目根目录算起 |
| `assets` | `assets` | 静态资源地址；相对路径拼在命名空间后，以 `/` 开头时视为绝对地址 |
| `theme_user` | `0` | 总览页默认查询的用户 ID，`0` 表示当前登录用户 |
| `theme_access` | `auth` | 主题访问模式：`auth` 需登录，`public` 公开（仅用于本地演示） |
| `theme_permission` | 空 | 访问主题及查询其他用户所需规则；为空表示仅允许查询当前登录用户 |

> `auth_type = 2` 时推荐使用 `auth_cache_store = cache`（Redis 或共享文件缓存）；多 Worker 下不要使用 `session`。`auth_request_cache` 默认启用，无 HTTP 请求上下文时自动关闭。

## 初始化数据表

执行安装命令，会自动创建规则表、角色表及关系表，表名会拼接数据库配置中的前缀：

```bash
php think store:auth:install
```

创建的数据表：

| 表名 | 说明 |
| --- | --- |
| `store_auth_rule` | 权限规则表，`name` 为规则唯一标识，`status = 1` 时有效，`condition` 为条件表达式 |
| `store_auth_role` | 权限角色表 |
| `store_auth_role_user` | 用户与角色关系表 |
| `store_auth_role_rule` | 角色与规则关系表 |

需要删除并重建表时追加 `--drop`，**该操作会清空认证数据**：

```bash
php think store:auth:install --drop
```

## 快速开始

### 方法签名

```php
use youvision\store\auth\Auth;

// 检查权限：$name 支持逗号分隔字符串或数组；$type 为规则类型，0 表示不限制；
// $mode 为 url 时同时校验规则中的请求参数；$relation 支持 or / and
Auth::check(string|array $name, int $uid, int $type = 1, string $mode = 'url', string $relation = 'or'): bool;

// 获取用户满足条件的全部规则
Auth::rules(int $uid, int $type = 1): array;

// 获取用户所属角色；传入 $field 时仅返回该字段
Auth::roles(int $uid, string $field = ''): array;

// 清理认证缓存；$uid = 0 表示全部用户，$type = 0 表示全部类型
Auth::flush(int $uid = 0, int $type = 0): void;
```

### 权限判断

```php
use youvision\store\auth\Auth;

if (Auth::check('admin/user/index', 1)) {
    // 拥有该规则权限
} else {
    // 无权限
}
```

也可以通过全局函数判断：

```php
if (store_auth_check('admin/user/index', 1)) {
    // 拥有该规则权限
}
```

为兼容 think-auth，`auth_check()`、`get_auth_rules()`、`get_auth_roles()`、`get_auth_role_ids()` 也会在未被其它扩展定义时注册。多个扩展共存时请优先使用 `store_auth_*` 系列函数。

### 多规则认证

多个规则默认按 `or` 关系认证，满足任意一个即通过：

```php
Auth::check('user/add,user/edit', 1);
```

将第五个参数 `$relation` 指定为 `and` 时，需要同时满足所有规则：

```php
Auth::check('user/add,user/edit', 1, 1, 'url', 'and');
```

规则传入数组同样有效：

```php
Auth::check(['user/add', 'user/edit'], 1);
```

### 规则类型

规则表的 `type` 字段用于区分规则类别，例如 `1` 表示接口权限、`2` 表示菜单。`check()` 的 `$type` 会按该字段过滤：

```php
Auth::check('admin/user/index', 1, 1); // 只匹配 type = 1 的规则
Auth::check('admin/menu/index', 1, 2);  // 只匹配 type = 2 的规则
Auth::check('admin/user/index', 1, 0);  // 不限制类型
```

### 控制器/方法级认证

将规则名称定义为 `控制器/方法` 的小写形式，例如 `admin/user/index`，然后使用内置中间件或在自己的控制器基类中统一认证。

内置中间件：

```php
// app/middleware.php
return [
    \youvision\store\auth\middleware\AuthCheck::class,
];
```

控制器名、方法名会自动拼成规则名；`auth_middleware_prefix` 可增加前缀，例如配置为 `admin` 时规则为 `admin/user/index`。`auth_middleware_allow` 用于配置免认证规则，支持 `*` 通配符。

自定义认证示例：

```php
declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;
use youvision\store\auth\Auth;

class AuthCheck
{
    protected array $allow = ['user/login'];

    public function handle(Request $request, Closure $next): Response
    {
        $rule = strtolower($request->controller() . '/' . $request->action());

        if (!in_array($rule, $this->allow, true) && !Auth::check($rule, (int) $request->user_id)) {
            return response('403 未授权访问', 403);
        }

        return $next($request);
    }
}
```

> `$request->user_id` 需替换为应用实际的登录用户获取方式。内置中间件从请求的 `user_id` 中间件属性读取用户 ID，该属性应由登录认证中间件写入；应用也可以继承并覆盖 `resolveUserId()`，从可信登录态取值，不要使用请求参数中的用户 ID。

### 获取用户权限与角色

```php
use youvision\store\auth\Auth;

// 用户拥有的全部规则
$rules = Auth::rules(1);

// 用户所属的角色数据，包含 role_id 与 role 关联数据
$roles = Auth::roles(1);

// 用户所属的角色 ID
$roleIds = Auth::roles(1, 'role_id');
```

对应的全局函数：

```php
store_auth_rules(1);      // 全部规则
store_auth_roles(1);      // 全部角色
store_auth_role_ids(1);   // 全部角色 ID
```

## 权限模型

权限认证按规则进行。先定义规则，再将规则分配给角色，最后将用户加入角色：

1. 在 `store_auth_rule` 表中添加规则，例如 `user/edit`。
2. 在 `store_auth_role` 表中创建角色，例如「内容管理员」。
3. 在 `store_auth_role_rule` 表中关联角色与规则。
4. 在 `store_auth_role_user` 表中关联用户与角色。

一个用户可以属于多个角色，只要其中任意一个角色拥有目标规则，认证即通过。

删除角色或规则时，包内模型事件会同步清理 `store_auth_role_rule`、`store_auth_role_user` 关系并刷新认证缓存。删除用户后应调用：

```php
use youvision\store\auth\Auth;

Auth::purgeUser(1);       // 删除用户角色关系并清理缓存
store_auth_purge_user(1); // 全局函数
```

也可以执行 `php think store:auth:purge --uid=1`。模型事件只在通过模型写入或删除时触发；直接执行 SQL 或使用查询构造器不会自动清理，需要手动调用 `Auth::flush()`。

## 条件规则

规则的 `condition` 字段可根据用户表字段设置附加条件。字段使用花括号包裹，例如用户表包含 `score` 字段：

| name | condition |
| --- | --- |
| `grade1` | `{score}<100` |
| `grade2` | `{score}>=100 and {score}<200` |
| `grade3` | `{score}>=200` |

支持的语法：

- 比较运算符：`>`、`>=`、`<`、`<=`、`=`、`==`、`!=`、`<>`
- 多个条件使用 `and`、`or` 组合，`and` 优先级高于 `or`
- 值支持数字、单引号或双引号字符串、`true`、`false`、`null`
- 仅写 `{字段}` 表示该字段有值即通过
- 引号内的 `and`、`or` 按普通字符串处理

> 条件解析不执行 PHP 代码，无法识别的表达式直接判定为不通过。不支持括号、函数调用和四则运算。

## URL 参数认证

当规则名称中带有查询参数时，认证还会校验当前请求参数，例如规则：

```text
user/index?type=admin
```

只有当前请求同时包含 `type=admin` 时，该规则才会被认为通过。默认忽略大小写，可通过 `auth_url_lowercase` 关闭。将 `$mode` 指定为非 `url` 值时，只匹配规则节点，不校验请求参数。

## 缓存

- `auth_type = 1`：实时认证，权限变更即时生效；同一请求内会复用规则列表，可通过 `auth_request_cache = false` 关闭。
- `auth_type = 2`：首次认证后将规则名称与条件字段签名写入持久缓存：
  - 条件规则引用的用户字段（如 `{score}`）发生变化时，下次认证会自动重新计算。
  - 使用 `auth_cache_store = cache` 前，需要先配置 ThinkPHP 的 `cache`（Redis 或共享文件缓存）。
  - 角色、规则、用户角色关系变化时，通过包内模型操作会自动刷新缓存。
  - 直接使用 SQL 或查询构造器修改授权关系后，需要手动调用 `flush()`。
  - `auth_cache_ttl` 控制缓存有效期，`0` 表示永久；授权变更频繁时建议设置合理有效期。
- 请求内缓存挂在当前 `Request` 对象上，请求结束即回收，不会跨请求保留；无 HTTP 请求上下文时自动关闭。
- 常驻内存（Swoole/Workerman）多 Worker 环境应使用 `auth_cache_store = cache` 并配置共享缓存；模型事件清理共享缓存后，其他 Worker 的下个请求会重新计算。`session` 无法清理其他会话，仅适用于单进程场景。

```php
use youvision\store\auth\Auth;

Auth::flush(1);    // 清理用户 1 的全部类型缓存
Auth::flush(1, 1); // 清理用户 1、类型 1 的缓存
Auth::flush();     // 清理全部认证缓存
```

## 权限总览主题

主题提供只读的权限管理总览，页面位于 `theme/view`，layui 与自定义样式位于 `theme/assets`，由内置路由直接输出，无需发布到宿主 `public` 目录。

### 页面与接口

| 方法 | 地址 | 说明 |
| --- | --- | --- |
| `GET` | `/store/think-store-auth/index` | 权限总览页 |
| `GET` | `/store/think-store-auth/api/user?uid=1` | 只读查询用户规则与角色 |
| `GET` | `/store/think-store-auth/assets/<path>` | 输出包内 layui、样式与脚本 |

总览页包含：

- 认证开关、认证方式、缓存驱动、用户表等运行配置。
- `store_auth_rule`、`store_auth_role`、`store_auth_role_user`、`store_auth_role_rule` 四张数据表的用途。
- 主题路由清单与包版本信息。
- 按用户 ID 查询可用规则与所属角色，结果以 layui 数据表格展示。

页面不提供写操作，规则、角色与授权的增删改仍需由应用自行实现。

### 访问控制

主题路由会自动挂载 `ThemeAccess` 中间件，默认 `theme_access = auth`：

- 登录用户 ID 从请求的 `user_id` 中间件属性读取，该属性应由登录中间件写入。未登录时页面和接口均返回 `403`。
- `theme_access = public` 会跳过登录检查，并公开配置信息与查询接口，仅适合本地演示，不要用于生产。
- `theme_permission` 配置规则后，用户还需拥有该规则才能访问主题；同一规则也用于判断能否查询其他用户。
- `theme_permission` 为空时，普通用户只能查询本人权限，查询其他用户返回 `403`。
- `route.middleware` 中的自定义中间件排在 `ThemeAccess` 之前，登录中间件应配置在这里。

```php
// config/store_auth.php
return [
    'route' => [
        'middleware' => [\app\middleware\AdminLogin::class],
    ],
    'theme_access' => 'auth',
    'theme_permission' => 'store/auth/admin',
];
```

### 自定义与保护

- 修改 `route.prefix` 或 `route.segment` 可调整访问地址，例如 `route.prefix = admin` 时页面地址变为 `/admin/think-store-auth/index`。
- 将 `route.middleware` 配置为登录或权限中间件，例如 `[\app\middleware\AdminAuth::class]`，可限制主题页面的访问。
- 将 `view` 配置为 `view/think-store-auth`（相对项目根目录）可在宿主项目覆盖模板。
- 将 `assets` 配置为绝对地址（以 `/` 开头）可改用外部托管的静态资源。
- `theme_user` 配置为有效用户 ID 后，总览页打开时会自动查询该用户的权限；配置为 `0` 时查询当前登录用户。
- `route.enabled = false` 时不再注册内置路由，可由应用自行定义。

> 权限查询接口会返回用户规则与角色信息。默认需要登录且仅允许查询本人；查看其他用户必须配置 `theme_permission` 并授予对应规则。

## 命令行

| 命令 | 说明 |
| --- | --- |
| `php think store:auth:config` | 生成 `config/store_auth.php` 配置文件 |
| `php think store:auth:config --force` | 覆盖已存在的配置文件 |
| `php think store:auth:install` | 创建认证数据表，已存在的表不会重复创建 |
| `php think store:auth:install --drop` | 删除并重建认证数据表，会清空数据 |
| `php think store:auth:purge --uid=1` | 删除指定用户的角色关系并清理认证缓存 |

## 从 think-auth 迁移

| think-auth | think-store-auth |
| --- | --- |
| `youvision/think-auth` | `youvision/think-store-auth` |
| `youvision\auth\...` | `youvision\store\auth\...` |
| `config/auth.php`（配置名 `auth`） | `config/store_auth.php`（配置名 `store_auth`） |
| `auth_rule` / `auth_role` / `auth_role_user` / `auth_role_rule` | `store_auth_rule` / `store_auth_role` / `store_auth_role_user` / `store_auth_role_rule` |
| `php think auth:config` | `php think store:auth:config` |
| `php think auth:install` | `php think store:auth:install` |
| `auth_check()` / `get_auth_rules()` 等 | 优先使用 `store_auth_check()` / `store_auth_rules()` 等 |

迁移时需要注意：

- `check()` 的 `$type` 现在会按规则表的 `type` 字段过滤，`0` 表示不限制。若历史数据的 `type` 不是 `1`，请显式传入对应类型或 `0`。
- `roles($uid)` 返回包含 `role_id` 和 `role` 关联数据的数组；`roles($uid, 'role_id')` 的行为保持不变。
- 条件解析器更严格，同时支持引号内含 `and` / `or` 的字符串；无法识别的表达式按不通过处理。
- 表名和命令已带 `store` 前缀，与原扩展可以同时安装。如需沿用原表名，请自行调整模型与 SQL。

## 目录结构

```text
route/
└── store_auth.php          主题页面、查询接口与静态资源路由
src/
├── Auth.php                静态入口
├── AssetController.php     包内静态资源输出
├── Service.php             服务注册与主题路由加载
├── Theme.php               主题地址与页面元数据
├── ThemeController.php     权限总览页与查询接口
├── View.php                包内模板渲染器
├── config.php              默认配置
├── helper.php              全局函数
├── command/
│   ├── Config.php          发布配置命令
│   ├── Install.php         创建数据表命令
│   └── Purge.php           清理已删除用户的授权关系
├── contract/
│   ├── Repository.php      认证数据源契约
│   └── Store.php           持久缓存契约
├── database/
│   └── store_auth.sql      建表语句
├── middleware/
│   ├── AuthCheck.php       控制器/方法级认证中间件
│   └── ThemeAccess.php     权限总览访问控制
├── model/
│   ├── Role.php
│   ├── RoleRule.php
│   ├── RoleUser.php
│   └── Rule.php
├── repository/
│   └── DatabaseRepository.php
├── service/
│   └── Auth.php            认证核心逻辑
├── store/
│   ├── CacheStore.php
│   ├── NullStore.php
│   └── SessionStore.php
└── support/
    ├── CacheFlusher.php    授权变更后的缓存清理
    ├── ConditionEvaluator.php
    ├── RuleMatcher.php
    └── SqlSplitter.php
theme/
├── view/
│   └── index.html          权限总览页
└── assets/
    ├── css/index.css       主题样式
    ├── js/index.js         权限查询脚本
    └── layui/              本地 layui 资源
tests/                      单元测试
```

## 测试

```bash
composer install
composer test
```

## 安全建议

- 规则名称应来自后端定义或固定白名单，不要直接使用未经校验的用户输入。
- 用户 ID 必须来自可信的登录态，不要使用请求参数中的用户 ID。
- 生产环境建议使用 `auth_type = 1`，权限变更可即时生效。
- 使用 `store:auth:install --drop` 前请确认数据已备份。
- 删除用户时必须调用 `Auth::purgeUser($uid)` 或 `store:auth:purge --uid`，避免用户 ID 复用时继承旧权限。
- 直接使用 SQL 或查询构造器修改角色、规则、授权关系后，需手动调用 `Auth::flush()`；模型事件不会覆盖绕过模型的写入。
- 权限总览页与 `api/user` 接口默认需要登录，且仅允许查询本人；`theme_access = public` 仅用于本地演示，不要用于生产。
- 查看其他用户权限需配置 `theme_permission` 并授予对应规则，同时通过 `route.middleware` 挂载可信登录中间件。
- 主题静态资源路由只读取包内 `theme/assets`，并已拒绝目录穿越；不要在主项目额外放行任意文件读取。

## License

[Apache-2.0](LICENSE)