# think-store-admin

ThinkPHP 8 权限认证扩展，基于规则（Rule）、角色（Role）、用户角色关系（RoleUser）和角色规则关系（RoleRule）实现 RBAC 权限控制。支持按钮级、接口级、控制器/方法级认证，并可按用户字段设置条件规则。


包内同时提供基于 layui 的权限总览主题：页面由包内 View 渲染，静态资源通过内置 assets 路由输出。接入登录中间件后访问 `/store/think-store-admin/index`，即可查看运行配置、数据表、路由清单，并按用户 ID 只读查询规则与角色；本地演示可设置 `theme_access = public`。

## 环境要求

- PHP >= 8.2
- ThinkPHP >= 8.0
- `topthink/think-view` ^2.0（随本包自动安装）
- `firebase/php-jwt` ^7.2（随本包自动安装）
- `topthink/think-filesystem` ^3.0（随本包自动安装）

## 安装

```bash
composer require youvision/think-store-admin
```

服务与配置会自动注册。如果配置未生成或服务未生效，可执行：

```bash
php think service:discover
php think vendor:publish
```

安装后可直接访问 `/store/think-store-admin/index` 查看权限总览页。主题路由由服务在 `boot()` 阶段注册，宿主项目无需复制视图或静态资源。

## 配置

配置文件位于 `config/store_admin.php`，也可以执行以下命令生成：

```bash
php think store:admin:config
```

如果文件已存在需要覆盖，追加 `--force`：

```bash
php think store:admin:config --force
```

配置项按用途分为 `auth`、`salt`、`jwt`、`layui`、`upload`、`jump` 六组，主题与路由配置单独列出。

### auth

| 配置项 | 默认值 | 说明 |
| --- | --- | --- |
| `auth.on` | `1` | 权限认证总开关。`1` 开启，`0` 关闭；关闭后 `check()` 一律返回 `true` |
| `auth.type` | `1` | 认证方式。`1` 实时认证，每次认证都查询数据库；`2` 持久缓存认证 |
| `auth.cache_store` | `cache` | `auth.type = 2` 时的持久缓存驱动：`cache`（Redis/共享文件，推荐常驻内存与多 Worker）、`session`（仅单进程）、`none` |
| `auth.cache_ttl` | `0` | 持久缓存有效期（秒），`0` 表示永久 |
| `auth.cache_prefix` | `store_admin` | 缓存键前缀 |
| `auth.request_cache` | `true` | 是否启用请求内规则缓存；无 HTTP 请求上下文时自动关闭 |
| `auth.user` | `auth_user` | 用户表名（不含数据库前缀），需包含 `email`、`mobile`、`username`、`password`、`salt`、`salt2` |
| `auth.user_pk` | `id` | 用户表主键 |
| `auth.user_model` | 空 | 用户模型类名。留空时直接使用 `auth.user` 表查询 |
| `auth.user_fields` | 见下方 | 用户表字段映射 |
| `auth.url_lowercase` | `true` | URL 参数认证时是否忽略大小写 |
| `auth.middleware_allow` | `[]` | 中间件无需认证的规则，支持 `*` 通配符 |
| `auth.middleware_uid` | `user_id` | 中间件读取登录用户 ID 的请求属性名 |
| `auth.middleware_prefix` | 空 | 中间件规则前缀，例如 `admin` |
| `auth.middleware_json` | `false` | 未授权时是否统一返回 JSON |

> `auth.type = 2` 时推荐使用 `auth.cache_store = cache`（Redis 或共享文件缓存）；多 Worker 下不要使用 `session`。`auth.request_cache` 默认启用，无 HTTP 请求上下文时自动关闭。

`auth.user` 指向的用户表需要包含以下字段，字段名可通过 `auth.user_fields` 覆盖：

| 用途 | 默认字段 |
| --- | --- |
| 登录账号 | `username`、`email`、`mobile` |
| 密码哈希 | `password` |
| 第一段盐 | `salt` |
| 第二段盐 | `salt2` |

建议为 `username`、`email`、`mobile` 建立唯一索引，避免登录查询匹配到多条用户记录；将 `auth.user_fields` 中不存在的字段映射为空字符串即可跳过该字段。

```php
'auth' => [
    'user_fields' => [
        'email' => 'email',
        'mobile' => 'mobile',
        'username' => 'username',
        'password' => 'password',
        'salt' => 'salt',
        'salt2' => 'salt2',
    ],
],
```

### salt

| 配置项 | 默认值 | 说明 |
| --- | --- | --- |
| `salting.prefix` | `Y!o^U` | MD5 加盐算法前缀，上线后不建议修改 |
| `salting.suffix` | `V,is*Io(N` | MD5 加盐算法后缀，上线后不建议修改 |
| `salting.charset` | 大小写字母、数字和符号 | `makePair` 随机盐字符池，支持 UTF-8 |
| `salting.length` | `8` | 第一段盐默认长度 |
| `salting.length2` | `10` | 第二段盐默认长度 |
| `salting.salt_field` | `salt` | 用户表 salt 字段名 |
| `salting.salt2_field` | `salt2` | 用户表 salt2 字段名 |

登录校验：

```php
use youvision\store\admin\facade\Auth;

$uid = Auth::verifyPassword('alice', 'plain-password');
// 成功返回用户 ID，失败返回 0
```

注册或重置密码时生成盐与哈希：

```php
use youvision\store\admin\support\auth\Salting;

$pair = Salting::makePair();
$hash = Salting::encode($password, $pair['salt'], $pair['salt2']);
```

对应的全局函数：`store_admin_make_salt()`、`store_admin_make_salt_pair()`、`store_admin_password()`、`store_admin_password_verify()`、`store_admin_verify_password()`。

> 该算法为兼容 think-salting 的旧 MD5 加盐算法。`prefix`、`suffix` 上线后不要修改，否则历史密码将无法校验；新项目建议使用 `password_hash()` 的 Argon2id 或 bcrypt。

### jwt

> `jwt.secret` 为空时无法签发或校验 Token。生产环境请通过环境变量注入，并在 `config/store_admin.php` 中读取配置。

| 配置项 | 默认值 | 说明 |
| --- | --- | --- |
| `jwt.secret` | 空 | HS256 签名密钥，必须设置 |
| `jwt.alg` | `HS256` | 签名算法，应与密钥类型匹配 |
| `jwt.issuer` | 空 | 签发者，配置后校验 `iss` |
| `jwt.audience` | 空 | 受众，配置后校验 `aud` |
| `jwt.ttl` | `604800` | Token 有效期（7 天） |
| `jwt.refresh_threshold` | `36000` | 剩余有效期低于该值时提示刷新（10 小时） |
| `jwt.leeway` | `60` | 时钟偏差容忍秒数 |
| `jwt.blacklist_prefix` | `store_admin:jwt:blacklist:` | 注销黑名单缓存前缀 |
| `jwt.uid_claim` | `user_id` | Token 业务载荷中的用户 ID 字段名 |

使用示例：

```php
use youvision\store\admin\facade\Jwt;

$token = Jwt::issue(['user_id' => 1, 'username' => 'alice']);

$result = Jwt::check($token);
// code = 1 表示有效；need_refresh = true 时提示客户端刷新

$refreshed = Jwt::refresh($token);
// 旧 Token 进入黑名单，data 为新 Token

$logout = Jwt::logout($token);
// 将当前 Token 加入黑名单
```

校验失败时返回 `code = 0`，并通过 `error_code` 区分格式错误、过期、已注销等情况。

JWT 中间件与 RBAC 中间件按顺序注册，`JwtAuth` 会先写入 `request->user_id`：

```php
Route::group('api', function () {
    // 需要登录的接口
})->middleware([
    \youvision\store\admin\middleware\JwtAuth::class,
    \youvision\store\admin\middleware\AuthCheck::class,
]);
```

JWT 黑名单写入 ThinkPHP Cache，多 Worker/多节点部署时必须使用共享缓存（Redis 或共享文件）。

### layui

| 配置项 | 默认值 | 说明 |
| --- | --- | --- |
| `layui.id` | `id` | 主键字段名 |
| `layui.pid` | `pid` | 父级字段名 |
| `layui.title` | `title` | 名称字段名 |
| `layui.children` | `children` | `toTree()` 输出的子级字段名 |
| `layui.dropdown.id` | `id` | dropdown 数据源主键字段 |
| `layui.dropdown.title` | `title` | dropdown 数据源标题字段 |
| `layui.dropdown.child` | `child` | dropdown 输出子级字段 |
| `layui.dropdown.source_child` | `children` | 输入数据中的子级字段 |

用于把扁平数据转换为 Layui 树形组件，并封装 table 服务端响应：

```php
use youvision\store\admin\support\layui\LayuiTable;
use youvision\store\admin\support\layui\LayuiTree;

$tree = (new LayuiTree())->init($list);

$tree->toTree();                  // tree.render 数据
$tree->toTreeTable();             // treeTable.render 数据
$tree->toMenu();                  // ul.layui-menu
$tree->toNav(['tree' => true]);   // ul.layui-nav

LayuiTable::response($paginator); // {code: 0, msg: '', count, data}
LayuiTable::page();
LayuiTable::sort(['id', 'created_at']);
LayuiTable::columns([['field' => 'id', 'title' => 'ID']]);
```

`LayuiTable` 的 `code = 0` 是 Layui 表格约定，与 JWT/Auth 的 `code = 1` 成功码不同。

> `LayuiTree::instance()` 是进程内单例，常驻内存环境建议直接使用 `new LayuiTree()`，或在请求结束时调用 `LayuiTree::reset()`。本模块不新增数据表。

#### layui.dropdown

`LayuiDropdown` 生成 Layui dropdown 所需的数据与操作结果：

```php
use youvision\store\admin\support\layui\LayuiDropdown;

$items = LayuiDropdown::items($tree);

$render = LayuiDropdown::render([
    'elem' => '#more-menu',
    'id' => 'more-menu',
    'trigger' => 'hover',
    'data' => $items,
]);

$reload = LayuiDropdown::reload('more-menu', $newItems, ['show' => true]);
$reloadData = LayuiDropdown::reloadData('more-menu', $newItems);
$close = LayuiDropdown::close('more-menu');
$open = LayuiDropdown::open('more-menu');
```

`reload` / `reloadData` 返回结构：

```json
{
  "code": 0,
  "msg": "",
  "data": {
    "id": "more-menu",
    "options": {
      "data": [],
      "show": true,
      "customName": {"id": "id", "title": "title", "children": "child"}
    }
  }
}
```

前端调用：

```js
fetch('/api/dropdown').then(function (res) {
  return res.json();
}).then(function (res) {
  dropdown.reload(res.data.id, res.data.options);
});
```

- `items()`：把树形数据转换为 `id/title/child` 结构，支持分割线和菜单组。
- `divider()`：生成分割线数据。
- `group($title, $children)`：生成纵向菜单组。
- `render()`：生成 `dropdown.render` 配置。
- `reload()`：完整重载响应。
- `reloadData()`：仅数据重载响应。
- `close()` / `open()`：生成关闭/打开操作结果。
- 该模块只返回数组，不注册 HTTP 路由；由业务控制器返回 JSON。

### upload

| 配置项 | 默认值 | 说明 |
| --- | --- | --- |
| `upload.enabled` | `true` | 是否注册上传接口 |
| `upload.field` | `file` | 上传文件域名称，与 Layui `field` 一致 |
| `upload.disk` | `public` | `think-filesystem` 磁盘名 |
| `upload.path` | `uploads/{date}` | 存储路径模板，支持 `{date}`、`{Y}`、`{m}`、`{d}` |
| `upload.url_prefix` | `/storage` | 磁盘未配置 `url` 时的访问地址前缀 |
| `upload.allowed_ext` | 常见图片/文档/压缩包 | 允许的文件后缀 |
| `upload.denied_ext` | php、html、svg 等 | 禁止的后缀，优先于允许列表 |
| `upload.allowed_mime` | `[]` | 额外 MIME 白名单，空表示不限制 |
| `upload.max_size` | `10485760` | 单文件最大字节数 |
| `upload.max_files` | `9` | 单次最多文件数 |
| `upload.middleware` | `[JwtAuth::class]` | 上传接口中间件，默认要求 JWT 登录 |

上传接口：

```text
POST /store/think-store-admin/upload
```

返回结构：

```json
{
  "code": 0,
  "msg": "",
  "data": {
    "src": "/storage/uploads/20261117/xxxx.png",
    "url": "/storage/uploads/20261117/xxxx.png",
    "name": "photo.png",
    "size": 12345,
    "ext": "png",
    "mime": "image/png"
  }
}
```

多文件上传时，`data` 为数组；`files` 始终返回完整列表。

```php
use youvision\store\admin\support\layui\LayuiUploads;

$result = LayuiUploads::handle($request);      // 自动读取 request->file('file')
$result = LayuiUploads::single($file);         // 单文件
$result = LayuiUploads::multiple($files);      // 多文件
```

Layui 前端：

```js
layui.use(function () {
  var upload = layui.upload;

  upload.render({
    elem: '#upload-image',
    url: '/store/think-store-admin/upload',
    field: 'file',
    accept: 'images',
    exts: 'jpg|jpeg|png|gif|webp',
    size: 2048,
    done: function (res) {
      if (res.code === 0) {
        console.log(res.data.src);
      }
    }
  });
});
```

上传依赖 `topthink/think-filesystem` 的磁盘配置，例如 `config/filesystem.php`：

```php
return [
    'default' => 'local',
    'disks' => [
        'public' => [
            'type' => 'local',
            'root' => public_path() . 'storage',
            'url' => '/storage',
            'visibility' => 'public',
        ],
    ],
];
```

> 上传接口默认挂 `JwtAuth`，不使用主题页面的 `ThemeAccess`。服务端会重新校验后缀、大小、MIME；客户端 `accept`、`exts`、`size` 不能作为安全依据。上传目录应禁止执行 PHP 等脚本。

### jump

| 配置项 | 默认值 | 说明 |
| --- | --- | --- |
| `jump.success_tmpl` | 空 | 成功跳转模板；为空时使用包内 `theme/view/dispatch_jump.html` |
| `jump.error_tmpl` | 空 | 失败跳转模板；为空时使用包内模板 |
| `jump.wait` | `3` | 页面跳转等待秒数 |
| `jump.success_code` | `1` | Ajax/JSON 成功响应码 |
| `jump.error_code` | `0` | Ajax/JSON 失败响应码 |
| `jump.result_code` | `0` | `result()` 默认响应码 |
| `jump.redirect_code` | `302` | `redirect()` 默认状态码 |

`Jump` 是控制器 trait，ThinkPHP 8 主项目可以直接接入。

**1. 在 BaseController 中引入**

```php
<?php
declare(strict_types=1);

namespace app;

use youvision\store\admin\support\jump\Jump;

abstract class BaseController
{
    use Jump;

    protected $app;
    protected $request;

    public function __construct(\think\App $app)
    {
        $this->app = $app;
        $this->request = $app->request;
        $this->initialize();
    }

    protected function initialize()
    {
    }
}
```

**2. 控制器中直接返回**

```php
namespace app\controller;

use app\BaseController;

class User extends BaseController
{
    public function save()
    {
        return $this->success('保存成功', 'user/index');
    }

    public function remove()
    {
        return $this->error('删除失败', 'user/index');
    }

    public function api()
    {
        return $this->result(['id' => 1], 0, 'ok', 'json');
    }

    public function go()
    {
        return $this->redirect('/user/index', 302);
    }
}
```

行为说明：

- 普通 HTML 请求：`success()` / `error()` 渲染跳转模板。
- Ajax/JSON 请求：返回 `code/msg/data/url/wait`；`success` 默认 `code = 1`，`error` 默认 `code = 0`。
- `result()` 返回 `code/msg/time/data`，默认 JSON。
- `redirect()` 默认 302，支持隐式传参。
- 四个方法都抛出 `HttpResponseException`，调用处必须 `return`，且不要在之后继续输出。
- 控制器方法不要命名为 `success`、`error`、`result`、`redirect`。
- 自定义模板可配置为绝对路径。
- 该模块使用 MulanPSL-2.0 上游实现，详见 `THIRD_PARTY_NOTICES.md`。

### 主题与路由

| 配置项 | 默认值 | 说明 |
| --- | --- | --- |
| `route.enabled` | `true` | 是否注册权限总览主题路由 |
| `route.prefix` | `store` | 主题路由前缀，留空表示不添加前缀 |
| `route.segment` | `think-store-admin` | 包内命名空间，留空表示页面直接挂在路由前缀下 |
| `route.middleware` | `[]` | 应用到整组主题路由的中间件，例如登录或权限中间件 |
| `view` | 空 | 模板目录；留空使用包内 `theme/view`，填写时相对路径从项目根目录算起 |
| `assets` | `assets` | 静态资源地址；相对路径拼在命名空间后，以 `/` 开头时视为绝对地址 |
| `theme_user` | `0` | 总览页默认查询的用户 ID，`0` 表示当前登录用户 |
| `theme_access` | `auth` | 主题访问模式：`auth` 需登录，`public` 公开（仅用于本地演示） |
| `theme_permission` | 空 | 访问主题及查询其他用户所需规则；为空表示仅允许查询当前登录用户 |

## 枚举

`EnumEnhance` 提供枚举名称、值、比较、实例化、数组转换和多语言描述，位于：

```php
youvision\store\admin\support\enums\EnumEnhance
```

```php
namespace app\enums;

use youvision\store\admin\support\enums\EnumEnhance;

enum UserType: int
{
    use EnumEnhance;

    case ADMINISTRATOR = 0;
    case MODERATOR = 1;
    case SUBSCRIBER = 2;
}
```

常用方法：

```php
UserType::names();        // ['ADMINISTRATOR', 'MODERATOR', 'SUBSCRIBER']
UserType::values();       // [0, 1, 2]
UserType::hasValue(1);
UserType::hasName('MODERATOR');
UserType::fromName('ADMINISTRATOR');
UserType::fromValue(0);
UserType::guess('MODERATOR');
UserType::toArray();
UserType::toSelectArray();
```

语言包位置：

```php
// app/lang/zh-cn/enums.php
use app\enums\UserType;

return [
    UserType::class => [
        UserType::ADMINISTRATOR->value => '管理员',
        UserType::MODERATOR->value => '监督员',
        UserType::SUBSCRIBER->value => '订阅用户',
    ],
];
```

`toSelectArray()` 可直接用于 Layui 表单选项；`toArray()` 可转换为 `LayuiDropdown` 的 `id/title` 数据。该模块没有配置项，不新增数据表。

## 初始化数据表

执行安装命令，会自动创建规则表、角色表及关系表，表名会拼接数据库配置中的前缀：

```bash
php think store:admin:install
```

创建的数据表：

| 表名 | 说明 |
| --- | --- |
| `auth_rule` | 权限规则表，`name` 为规则唯一标识，`status = 1` 时有效，`condition` 为条件表达式 |
| `auth_role` | 权限角色表 |
| `auth_role_user` | 用户与角色关系表 |
| `auth_role_rule` | 角色与规则关系表 |

需要删除并重建表时追加 `--drop`，**该操作会清空认证数据**：

```bash
php think store:admin:install --drop
```

## 快速开始

### 方法签名

```php
use youvision\store\admin\facade\Auth;

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
use youvision\store\admin\facade\Auth;

if (Auth::check('admin/user/index', 1)) {
    // 拥有该规则权限
} else {
    // 无权限
}
```

也可以直接使用全局函数 `store_admin_check()`：

```php
if (store_admin_check('admin/user/index', 1)) {
    // 拥有该规则权限
}
```

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
    \youvision\store\admin\middleware\AuthCheck::class,
];
```

控制器名、方法名会自动拼成规则名；`auth.middleware_prefix` 可增加前缀，例如配置为 `admin` 时规则为 `admin/user/index`。`auth.middleware_allow` 用于配置免认证规则，支持 `*` 通配符。

自定义认证示例：

```php
declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;
use youvision\store\admin\facade\Auth;

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
use youvision\store\admin\facade\Auth;

// 用户拥有的全部规则
$rules = Auth::rules(1);

// 用户所属的角色数据，包含 role_id 与 role 关联数据
$roles = Auth::roles(1);

// 用户所属的角色 ID
$roleIds = Auth::roles(1, 'role_id');
```

对应的全局函数：

```php
store_admin_rules(1);      // 全部规则
store_admin_roles(1);      // 全部角色
store_admin_role_ids(1);   // 全部角色 ID
```

## 权限模型

权限认证按规则进行。先定义规则，再将规则分配给角色，最后将用户加入角色：

1. 在 `auth_rule` 表中添加规则，例如 `user/edit`。
2. 在 `auth_role` 表中创建角色，例如「内容管理员」。
3. 在 `auth_role_rule` 表中关联角色与规则。
4. 在 `auth_role_user` 表中关联用户与角色。

一个用户可以属于多个角色，只要其中任意一个角色拥有目标规则，认证即通过。

删除角色或规则时，包内模型事件会同步清理 `auth_role_rule`、`auth_role_user` 关系并刷新认证缓存。删除用户后应调用：

```php
use youvision\store\admin\facade\Auth;

Auth::purgeUser(1);       // 删除用户角色关系并清理缓存
store_admin_purge_user(1); // 全局函数
```

也可以执行 `php think store:admin:purge --uid=1`。模型事件只在通过模型写入或删除时触发；直接执行 SQL 或使用查询构造器不会自动清理，需要手动调用 `Auth::flush()`。

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

只有当前请求同时包含 `type=admin` 时，该规则才会被认为通过。默认忽略大小写，可通过 `auth.url_lowercase` 关闭。将 `$mode` 指定为非 `url` 值时，只匹配规则节点，不校验请求参数。

## 缓存

- `auth.type = 1`：实时认证，权限变更即时生效；同一请求内会复用规则列表，可通过 `auth.request_cache = false` 关闭。
- `auth.type = 2`：首次认证后将规则名称与条件字段签名写入持久缓存：
  - 条件规则引用的用户字段（如 `{score}`）发生变化时，下次认证会自动重新计算。
  - 使用 `auth.cache_store = cache` 前，需要先配置 ThinkPHP 的 `cache`（Redis 或共享文件缓存）。
  - 角色、规则、用户角色关系变化时，通过包内模型操作会自动刷新缓存。
  - 直接使用 SQL 或查询构造器修改授权关系后，需要手动调用 `flush()`。
  - `auth.cache_ttl` 控制缓存有效期，`0` 表示永久；授权变更频繁时建议设置合理有效期。
- 请求内缓存挂在当前 `Request` 对象上，请求结束即回收，不会跨请求保留；无 HTTP 请求上下文时自动关闭。
- 常驻内存（Swoole/Workerman）多 Worker 环境应使用 `auth.cache_store = cache` 并配置共享缓存；模型事件清理共享缓存后，其他 Worker 的下个请求会重新计算。`session` 无法清理其他会话，仅适用于单进程场景。

```php
use youvision\store\admin\facade\Auth;

Auth::flush(1);    // 清理用户 1 的全部类型缓存
Auth::flush(1, 1); // 清理用户 1、类型 1 的缓存
Auth::flush();     // 清理全部认证缓存
```

## 权限总览主题

主题提供只读的权限管理总览，页面位于 `theme/view`，layui 与自定义样式位于 `theme/assets`，由内置路由直接输出，无需发布到宿主 `public` 目录。

### 页面与接口

| 方法 | 地址 | 说明 |
| --- | --- | --- |
| `GET` | `/store/think-store-admin/index` | 权限总览页 |
| `GET` | `/store/think-store-admin/api/user?uid=1` | 只读查询用户规则与角色 |
| `GET` | `/store/think-store-admin/assets/<path>` | 输出包内 layui、样式与脚本 |

总览页包含：

- 认证开关、认证方式、缓存驱动、用户表等运行配置。
- `auth_rule`、`auth_role`、`auth_role_user`、`auth_role_rule` 四张数据表的用途。
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
// config/store_admin.php
return [
    'route' => [
        'middleware' => [\app\middleware\AdminLogin::class],
    ],
    'theme_access' => 'auth',
    'theme_permission' => 'store/auth/admin',
];
```

### 自定义与保护

- 修改 `route.prefix` 或 `route.segment` 可调整访问地址，例如 `route.prefix = admin` 时页面地址变为 `/admin/think-store-admin/index`。
- 将 `route.middleware` 配置为登录或权限中间件，例如 `[\app\middleware\AdminAuth::class]`，可限制主题页面的访问。
- 将 `view` 配置为 `view/think-store-admin`（相对项目根目录）可在宿主项目覆盖模板。
- 将 `assets` 配置为绝对地址（以 `/` 开头）可改用外部托管的静态资源。
- `theme_user` 配置为有效用户 ID 后，总览页打开时会自动查询该用户的权限；配置为 `0` 时查询当前登录用户。
- `route.enabled = false` 时不再注册内置路由，可由应用自行定义。

> 权限查询接口会返回用户规则与角色信息。默认需要登录且仅允许查询本人；查看其他用户必须配置 `theme_permission` 并授予对应规则。

## 命令行

| 命令 | 说明 |
| --- | --- |
| `php think store:admin:config` | 生成 `config/store_admin.php` 配置文件 |
| `php think store:admin:config --force` | 覆盖已存在的配置文件 |
| `php think store:admin:install` | 创建认证数据表，已存在的表不会重复创建 |
| `php think store:admin:install --drop` | 删除并重建认证数据表，会清空数据 |
| `php think store:admin:purge --uid=1` | 删除指定用户的角色关系并清理认证缓存 |


## 目录结构

```text


src/
├── Service.php             服务注册与主题路由加载
├── helper.php              全局函数
├── facade/
│   ├── Auth.php            权限认证静态入口
│   └── Jwt.php             JWT 静态入口
├── config/
│   └── store_admin.php     默认配置
├── route/
│   └── store_admin.php     主题页面、查询接口与静态资源路由
├── controller/
│   ├── Dashboard.php      权限总览首页
│   ├── Home.php           首页
│   ├── Init.php           初始化配置
│   ├── Menu.php           Layui 菜单数据
│   ├── Passport.php       登录与退出
│   ├── admin/
│   │   └── Index.php      后台首页
│   ├── asset/
│   │   └── Asset.php      包内静态资源输出
│   ├── theme/
│   │   └── Overview.php   权限总览页与查询接口
│   └── upload/
│       └── Upload.php     Layui 上传接口
├── command/
│   ├── PublishConfig.php   发布配置命令
│   ├── InstallCommand.php  创建数据表命令
│   └── PurgeCommand.php    清理已删除用户的授权关系
├── contract/
│   ├── Repository.php      认证数据源契约
│   └── Store.php           持久缓存契约
├── database/
│   └── store_admin.sql      建表语句
├── middleware/
│   ├── AuthCheck.php       控制器/方法级认证中间件
│   ├── JwtAuth.php         JWT 认证中间件
│   └── ThemeAccess.php     权限总览访问控制
├── model/
│   ├── BaseModel.php       模型基类
│   └── auth/
│       ├── Role.php
│       ├── RoleRule.php
│       ├── RoleUser.php
│       └── Rule.php
├── repository/
│   └── DatabaseRepository.php
├── service/
│   ├── Auth.php            认证核心逻辑
│   └── Jwt.php             JWT 签发与校验
├── storage/
│   ├── CacheStore.php
│   ├── NullStore.php
│   └── SessionStore.php

└── support/
    ├── CacheFlusher.php    授权变更后的缓存清理
    ├── ConditionEvaluator.php
    ├── RuleMatcher.php
    ├── SqlSplitter.php
    ├── auth/
    │   └── Salting.php     密码加盐算法
    ├── enums/
    │   └── EnumEnhance.php 枚举增强 trait
    ├── jump/
    │   └── Jump.php        控制器跳转响应 trait
    ├── theme/
    │   └── Theme.php       主题地址与页面元数据
    ├── layui/
    │   ├── LayuiDropdown.php Layui dropdown 数据与重载
    │   ├── LayuiTable.php    Layui table 服务端工具
    │   ├── LayuiTree.php     Layui 树形结构工具
    │   └── LayuiUploads.php  Layui 上传接口工具
    ├── url/
    │   └── Url.php          包内链接生成器
    └── view/
        └── View.php         包内模板渲染器
theme/
├── view/
│   ├── index.html          权限总览页
│   ├── home.html           首页
│   ├── login.html          登录页
│   ├── layui.html          Layui 组件示例
│   ├── dispatch_jump.html  跳转提示模板
│   ├── admin/
│   │   └── index.html      后台首页
│   └── doc/
│       └── index.html      静态文档首页
└── assets/
    ├── component/
    │   └── layui/          本地 layui 资源
    └── doc/
        ├── css/index.css   文档与主题样式
        └── js/index.js     权限查询脚本
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
- 生产环境建议使用 `auth.type = 1`，权限变更可即时生效。
- 使用 `store:admin:install --drop` 前请确认数据已备份。
- 删除用户时必须调用 `Auth::purgeUser($uid)` 或 `store:admin:purge --uid`，避免用户 ID 复用时继承旧权限。
- 直接使用 SQL 或查询构造器修改角色、规则、授权关系后，需手动调用 `Auth::flush()`；模型事件不会覆盖绕过模型的写入。
- 权限总览页与 `api/user` 接口默认需要登录，且仅允许查询本人；`theme_access = public` 仅用于本地演示，不要用于生产。
- 查看其他用户权限需配置 `theme_permission` 并授予对应规则，同时通过 `route.middleware` 挂载可信登录中间件。
- 主题静态资源路由只读取包内 `theme/assets`，并已拒绝目录穿越；不要在主项目额外放行任意文件读取。

## License

[Apache-2.0](LICENSE)