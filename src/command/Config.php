<?php

declare(strict_types=1);

namespace youvision\store\auth\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 发布认证配置文件
 */
class Config extends Command
{
    protected function configure(): void
    {
        $this->setName('store:auth:config')
            ->addOption('force', 'f', Option::VALUE_NONE, 'Overwrite the existing config file')
            ->setDescription('Publish the store auth config file');
    }

    protected function execute(Input $input, Output $output): int
    {
        $target = $this->app->getConfigPath() . 'store_auth.php';

        if (is_file($target) && !$input->getOption('force')) {
            $output->writeln('<info>Config file already exists: ' . $target . '</info>');
            return 0;
        }

        $source = dirname(__DIR__) . '/config.php';

        if (!is_file($source) || file_put_contents($target, (string) file_get_contents($source)) === false) {
            $output->writeln('<error>Unable to write config file: ' . $target . '</error>');
            return 1;
        }

        $output->writeln('<info>Config file created: ' . $target . '</info>');
        return 0;
    }
}