<?php

declare(strict_types=1);

namespace youvision\store\auth;

use think\Service as BaseService;
use youvision\store\auth\command\Config;
use youvision\store\auth\command\Install;
use youvision\store\auth\command\Purge;
use youvision\store\auth\service\Auth as AuthService;

/**
 * 认证服务注册入口
 */
class Service extends BaseService
{
    /**
     * 注册配置、认证服务与主题服务
     */
    public function register(): void
    {
        $default = require __DIR__ . '/config.php';
        $config = array_replace_recursive($default, (array) $this->app->config->get('store_auth', []));

        $this->app->config->set($config, 'store_auth');
        $this->app->instance(AuthService::class, new AuthService($this->app));

        $this->app->bind(Theme::class, function () {
            return new Theme(
                (array) $this->app->config->get('store_auth', []),
                dirname(__DIR__)
            );
        });

        $this->app->instance(View::class, new View($this->app));
    }

    /**
     * 注册主题路由与命令行指令
     */
    public function boot(): void
    {
        if ((bool) $this->app->config->get('store_auth.route.enabled', true)) {
            $this->loadRoutesFrom(dirname(__DIR__) . '/route/store_auth.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                'store:auth:config' => Config::class,
                'store:auth:install' => Install::class,
                'store:auth:purge' => Purge::class,
            ]);
        }
    }
}