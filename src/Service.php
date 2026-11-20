<?php

declare(strict_types=1);

namespace youvision\store\admin;

use think\Service as BaseService;
use youvision\store\admin\command\InstallCommand;
use youvision\store\admin\command\PublishConfig;
use youvision\store\admin\command\PurgeCommand;
use youvision\store\admin\service\Auth as AuthService;
use youvision\store\admin\support\theme\Theme;
use youvision\store\admin\support\url\Url;
use youvision\store\admin\support\view\View;

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
        $default = require __DIR__ . '/config/store_admin.php';
        $config = array_replace_recursive($default, (array) $this->app->config->get('store_admin', []));

        $this->app->config->set($config, 'store_admin');
        $this->app->instance(AuthService::class, new AuthService($this->app));

        $this->app->bind(Theme::class, function () {
            return new Theme(
                (array) $this->app->config->get('store_admin', []),
                dirname(__DIR__)
            );
        });

        $this->app->instance(View::class, new View($this->app));

        $this->app->bind(Url::class, function () {
            return new Url($this->app->make(Theme::class));
        });
    }

    /**
     * 注册主题路由与命令行指令
     */
    public function boot(): void
    {
        if ((bool) $this->app->config->get('store_admin.route.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/route/store_admin.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                'store:admin:config' => PublishConfig::class,
                'store:admin:install' => InstallCommand::class,
                'store:admin:purge' => PurgeCommand::class,
            ]);
        }
    }
}