<?php

declare(strict_types=1);

namespace youvision\store\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use youvision\store\admin\support\SqlSplitter;

/**
 * 初始化认证数据表
 */
class Install extends Command
{
    protected function configure(): void
    {
        $this->setName('store:admin:install')
            ->addOption('drop', null, Option::VALUE_NONE, 'Drop the auth tables before creating them')
            ->setDescription('Create the store auth tables');
    }

    protected function execute(Input $input, Output $output): int
    {
        $file = dirname(__DIR__) . '/database/store_admin.sql';

        if (!is_file($file)) {
            $output->writeln('<error>SQL file not found: ' . $file . '</error>');
            return 1;
        }

        $connection = (string) $this->app->config->get('database.default', 'mysql');
        $prefix = (string) $this->app->config->get('database.connections.' . $connection . '.prefix', '');
        $sql = str_replace('__PREFIX__', $prefix, (string) file_get_contents($file));

        if ($input->getOption('drop')) {
            preg_match_all('/CREATE TABLE IF NOT EXISTS `([^`]+)`/i', $sql, $matches);

            foreach (array_reverse($matches[1]) as $table) {
                $this->app->db->execute('DROP TABLE IF EXISTS `' . $table . '`');
                $output->writeln('<comment>Dropped table: ' . $table . '</comment>');
            }
        }

        $statements = SqlSplitter::split($sql);

        if ($statements === []) {
            $output->writeln('<error>No executable statement found: ' . $file . '</error>');
            return 1;
        }

        foreach ($statements as $statement) {
            $this->app->db->execute($statement);
        }

        $output->writeln('<info>Store auth tables installed, prefix: ' . ($prefix === '' ? '(none)' : $prefix) . '</info>');
        return 0;
    }
}