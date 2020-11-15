<?php

declare(strict_types=1);

/*
 * This file is part of the ************************ package.
 * _____________                           _______________
 *  ______/     \__  _____  ____  ______  / /_  _________
 *   ____/ __   / / / / _ \/ __`\/ / __ \/ __ \/ __ \___
 *    __/ / /  / /_/ /  __/ /  \  / /_/ / / / / /_/ /__
 *      \_\ \_/\____/\___/_/   / / .___/_/ /_/ .___/
 *         \_\                /_/_/         /_/
 *
 * The PHP Framework For Code Poem As Free As Wind. <Query Yet Simple>
 * (c) 2010-2020 http://queryphp.com All rights reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Leevel\Kernel;

use Leevel\Http\Request;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * 异常运行时接口.
 */
interface IExceptionRuntime
{
    /**
     * 异常上报.
     */
    public function report(Throwable $e): void;

    /**
     * 异常是否需要上报.
     */
    public function reportable(Throwable $e): bool;

    /**
     * 异常渲染.
     */
    public function render(Request $request, Throwable $e): Response;

    /**
     * 命令行渲染.
     */
    public function renderForConsole(OutputInterface $output, Throwable $e): void;
}
