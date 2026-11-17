<?php

declare(strict_types=1);

namespace youvision\store\auth\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use youvision\store\auth\service\Auth as AuthService;

/**
 * 清理已删除用户的角色关系与认证缓存
 */
class Purge extends Command
{
    protected function configure(): void
    {
        $this->setName('store:auth:purge')
            ->addOption('uid', null, Option::VALUE_REQUIRED, 'User ID to purge')
            ->setDescription('Remove a user from auth relations and flush auth cache');
    }

    protected function execute(Input $input, Output $output): int
    {
        $uid = (int) $input->getOption('uid');

        if ($uid <= 0) {
            $output->writeln('<error>Please provide a valid --uid option.</error>');
            return 1;
        }

        $count = $this->app->make(AuthService::class)->purgeUser($uid);

        $output->writeln('<info>Removed ' . $count . ' role relation(s) for user ' . $uid . ', auth cache flushed.</info>');

        return 0;
    }
}