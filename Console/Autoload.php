<?php

declare(strict_types=1);

namespace Leevel\Kernel\Console;

use Leevel\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * 优化 composer 自动加载.
 */
class Autoload extends Command
{
    /**
     * 命令名字.
     */
    protected string $name = 'autoload';

    /**
     * 命令行描述.
     */
    protected string $description = 'Optimize base on composer dump-autoload --optimize [--no-dev]';

    /**
     * 响应命令.
     */
    public function handle(): int
    {
        $this->line('Start to cache autoload.');
        $this->line($command = $this->normalizeComposerCommand());
        exec($command);
        $this->info('Autoload cache succeed.');

        return self::SUCCESS;
    }

    /**
     * 取得 composer 优化命令.
     */
    protected function normalizeComposerCommand(): string
    {
        $command = sprintf(
            '%s dump-autoload --optimize',
            escapeshellarg((string) $this->getOption('composer'))
        );

        if (false === (bool) $this->getOption('dev')) {
            $command .= ' --no-dev';
        }

        return $command;
    }

    /**
     * 命令配置.
     */
    protected function getOptions(): array
    {
        return [
            [
                'composer',
                null,
                InputOption::VALUE_OPTIONAL,
                'Where is composer.',
                'composer',
            ],
            [
                'dev',
                '-d',
                InputOption::VALUE_NONE,
                'Without `--no-dev` config for `composer dump-autoload --optimize`.',
            ],
        ];
    }
}
