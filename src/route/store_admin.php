<?php

declare(strict_types=1);

use think\facade\Route;
use youvision\store\admin\controller\asset\Asset;
use youvision\store\admin\controller\Home;
use youvision\store\admin\controller\Index;
use youvision\store\admin\controller\Init;
use youvision\store\admin\controller\Menu;
use youvision\store\admin\controller\Passport;
use youvision\store\admin\controller\doc\Overview;
use youvision\store\admin\controller\filecloud\Upload;
use youvision\store\admin\controller\admin\Index as AdminIndex;
use youvision\store\admin\middleware\JwtAuth;
use youvision\store\admin\middleware\ThemeAccess;

/*
 * 权限总览主题路由
 *
 * 由 youvision\store\admin\Service 在 boot 阶段加载，宿主项目无需复制路由文件。
 * 前缀、命名空间与中间件来自 config/store_admin.php：
 * - store_admin.route.enabled     是否注册内置路由
 * - store_admin.route.prefix      路由前缀，默认 store
 * - store_admin.route.segment     包内命名空间，默认 think-store-admin
 * - store_admin.route.middleware  应用到整组路由的中间件
 * - store_admin.theme_access      auth 需登录，public 公开
 * - store_admin.theme_permission  访问主题与查询其他用户所需规则
 */
$prefix = trim((string) config('store_admin.route.prefix', 'store'), '/');
$segment = trim((string) config('store_admin.route.segment', 'think-store-admin'), '/');
$middleware = array_values(array_unique(array_merge(
    (array) config('store_admin.route.middleware', []),
    [ThemeAccess::class]
)));

$register = static function () use ($segment): void {
    $base = $segment === '' ? '' : $segment . '/';

    $pages = [
        'index' => ['get', Index::class, 'index'],
        'admin' => ['get', AdminIndex::class, 'index'],
        'home' => ['get', Home::class, 'index'],
        'menu' => ['get', Menu::class, 'index'],
        'init' => ['get', Init::class, 'index'],
        'passport/login' => ['*', Passport::class, 'login'],
        'passport/logout' => ['*', Passport::class, 'logout'],
    ];

    Route::get($segment === '' ? '/' : $segment, [Index::class, 'index'])
        ->name('store_admin.root')
        ->completeMatch();

    if ($segment !== '') {
        Route::get($segment . '/', [Index::class, 'index'])
            ->name('store_admin.root.slash')
            ->completeMatch();
    }

    foreach ($pages as $path => [$method, $controller, $action]) {
        Route::rule($base . $path, [$controller, $action], $method === '*' ? '*' : strtoupper($method))
            ->name('store_admin.' . str_replace('/', '.', $path))
            ->completeMatch();
    }

    // JSON 接口统一挂在 api 段下，与页面路由区分
    Route::get($base . 'api/user', [Overview::class, 'user'])
        ->name('store_admin.user')
        ->completeMatch();

    // 静态资源直接由路由输出，无需发布到 public
    Route::get($base . 'assets/<path>', [Asset::class, 'file'])
        ->pattern(['path' => '.*'])
        ->name('store_admin.asset');
};

$group = Route::group($prefix, $register);
$group->middleware($middleware);
// 上传接口独立于主题页面，避免继承 ThemeAccess 的 theme_permission
if ((bool) config('store_admin.upload.enabled', true)) {
    $uploadMiddleware = (array) config('store_admin.upload.middleware', [JwtAuth::class]);
    $uploadPath = ($prefix === '' ? '' : $prefix . '/')
        . ($segment === '' ? '' : $segment . '/')
        . 'upload';

    Route::post($uploadPath, [Upload::class, 'upload'])
        ->middleware($uploadMiddleware)
        ->name('store_admin.upload')
        ->completeMatch();
}