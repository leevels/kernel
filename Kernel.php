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

use ErrorException;
use Exception;
use Leevel\Http\Request;
use Leevel\Kernel\Bootstrap\LoadI18n;
use Leevel\Kernel\Bootstrap\LoadOption;
use Leevel\Kernel\Bootstrap\RegisterExceptionRuntime;
use Leevel\Kernel\Bootstrap\TraverseProvider;
use Leevel\Router\IRouter;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * 内核执行.
 */
abstract class Kernel implements IKernel
{
    /**
     * 应用初始化执行.
     */
    protected array $bootstraps = [
        LoadOption::class,
        LoadI18n::class,
        RegisterExceptionRuntime::class,
        TraverseProvider::class,
    ];

    /**
     * 构造函数.
     */
    public function __construct(protected IApp $app, protected IRouter $router)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request): Response
    {
        try {
            $this->registerBaseService($request);
            $this->bootstrap();
            $response = $this->getResponseWithRequest($request);
        } catch (Exception $e) {
            $this->reportException($e);
            $response = $this->renderException($request, $e);
        } catch (Throwable $e) {
            $e = new ErrorException(
                $e->getMessage(),
                $e->getCode(),
                E_ERROR,
                $e->getFile(),
                $e->getLine(),
                $e->getPrevious()
            );
            $this->reportException($e);
            $response = $this->renderException($request, $e);
        }

        $this->middlewareTerminate($request, $response);

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(Request $request, Response $response): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function bootstrap(): void
    {
        $this->app->bootstrap($this->bootstraps);
    }

    /**
     * {@inheritdoc}
     */
    public function getApp(): IApp
    {
        return $this->app;
    }

    /**
     * 返回运行处理器.
     */
    protected function getExceptionRuntime(): IExceptionRuntime
    {
        return $this->app
            ->container()
            ->make(IExceptionRuntime::class);
    }

    /**
     * 注册基础服务.
     */
    protected function registerBaseService(Request $request): void
    {
        $this->app
            ->container()
            ->instance('request', $request);
        $this->app
            ->container()
            ->alias('request', Request::class);
    }

    /**
     * 根据请求返回响应.
     */
    protected function getResponseWithRequest(Request $request): Response
    {
        return $this->dispatchRouter($request);
    }

    /**
     * 路由调度.
     */
    protected function dispatchRouter(Request $request): Response
    {
        return $this->router->dispatch($request);
    }

    /**
     * 上报错误.
     */
    protected function reportException(Exception $e): void
    {
        $this->getExceptionRuntime()->report($e);
    }

    /**
     * 渲染异常.
     */
    protected function renderException(Request $request, Exception $e): Response
    {
        return $this->getExceptionRuntime()->render($request, $e);
    }

    /**
     * 中间件结束响应.
     */
    protected function middlewareTerminate(Request $request, Response $response): void
    {
        $this->router->throughMiddleware($request, [$response]);
    }
}
