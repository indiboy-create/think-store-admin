<?php

declare(strict_types=1);

use think\facade\Route;
use youvision\store\auth\AssetController;
use youvision\store\auth\ThemeController;
use youvision\store\auth\middleware\ThemeAccess;

/*
 * 权限总览主题路由
 *
 * 由 youvision\store\auth\Service 在 boot 阶段加载，宿主项目无需复制路由文件。
 * 前缀、命名空间与中间件来自 config/store_auth.php：
 * - store_auth.route.enabled     是否注册内置路由
 * - store_auth.route.prefix      路由前缀，默认 store
 * - store_auth.route.segment     包内命名空间，默认 think-store-auth
 * - store_auth.route.middleware  应用到整组路由的中间件
 * - store_auth.theme_access      auth 需登录，public 公开
 * - store_auth.theme_permission  访问主题与查询其他用户所需规则
 */
$prefix = trim((string) config('store_auth.route.prefix', 'store'), '/');
$segment = trim((string) config('store_auth.route.segment', 'think-store-auth'), '/');
$middleware = array_values(array_unique(array_merge(
    (array) config('store_auth.route.middleware', []),
    [ThemeAccess::class]
)));

$register = static function () use ($segment): void {
    $base = $segment === '' ? '' : $segment . '/';

    Route::get($base . 'index', [ThemeController::class, 'index'])
        ->name('store_auth.index')
        ->completeMatch();

    // JSON 接口统一挂在 api 段下，与页面路由区分
    Route::get($base . 'api/user', [ThemeController::class, 'user'])
        ->name('store_auth.user')
        ->completeMatch();

    // 静态资源直接由路由输出，无需发布到 public
    Route::get($base . 'assets/<path>', [AssetController::class, 'file'])
        ->pattern(['path' => '.*'])
        ->name('store_auth.asset');
};

$group = Route::group($prefix, $register);
$group->middleware($middleware);