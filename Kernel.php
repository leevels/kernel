<?php

declare(strict_types=1);

namespace Leevel\Kernel;

use Leevel\Http\Request;
use Leevel\Kernel\Bootstrap\LoadConfig;
use Leevel\Kernel\Bootstrap\LoadI18n;
use Leevel\Kernel\Bootstrap\RegisterExceptionRuntime;
use Leevel\Kernel\Bootstrap\TraverseProvider;
use Leevel\Kernel\Exceptions\IRuntime;
use Leevel\Router\IRouter;
use Leevel\Support\Pipeline;
use Symfony\Component\HttpFoundation\Response;

/**
 * 内核执行.
 */
abstract class Kernel implements IKernel
{
    /**
     * 应用初始化执行.
     */
    protected array $bootstraps = [
        LoadConfig::class,
        LoadI18n::class,
        RegisterExceptionRuntime::class,
        TraverseProvider::class,
    ];

    /**
     * 应用扩展初始化执行.
     */
    protected array $extendBootstraps = [];

    /**
     * 系统中间件.
     */
    protected array $middlewares = [];

    /**
     * 解析后的系统中间件.
     */
    protected array $resolvedMiddlewares = [];

    /**
     * 构造函数.
     */
    public function __construct(
        protected IApp $app,
        protected IRouter $router
    ) {
        if ($this->middlewares) {
            $this->resolvedMiddlewares = $this->parseMiddlewares($this->middlewares);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function handle(Request $request): Response
    {
        try {
            $this->registerBaseService($request);
            $this->bootstrap();

            return $this->throughMiddleware($request, function (\Closure $next, Request $request): Response {
                return $this->getResponseWithRequest($request);
            });
        } catch (\Exception $e) {
            $this->reportException($e);

            return $this->throughMiddleware($request, function (\Closure $next, Request $request) use ($e): Response {
                return $this->renderException($request, $e);
            });
        } catch (\Throwable $e) {
            $e = new \ErrorException(
                $e->getMessage(),
                $e->getCode(),
                E_ERROR,
                $e->getFile(),
                $e->getLine(),
                $e->getPrevious()
            );
            $this->reportException($e);

            return $this->throughMiddleware($request, function (\Closure $next, Request $request) use ($e): Response {
                return $this->renderException($request, $e);
            });
        }
    }

    /**
     * {@inheritDoc}
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->routerTerminateMiddleware($request, $response);
        $this->terminateMiddleware($request, $response);
    }

    /**
     * {@inheritDoc}
     */
    public function bootstrap(): void
    {
        $this->app->bootstrap(array_merge($this->bootstraps, $this->extendBootstraps));
    }

    /**
     * {@inheritDoc}
     */
    public function getApp(): IApp
    {
        return $this->app;
    }

    /**
     * 返回运行处理器.
     */
    protected function getExceptionRuntime(): IRuntime
    {
        // @phpstan-ignore-next-line
        return $this->app
            ->container()
            ->make(IRuntime::class)
        ;
    }

    /**
     * 注册基础服务.
     */
    protected function registerBaseService(Request $request): void
    {
        $this->app
            ->container()
            ->instance('request', $request)
        ;
        $this->app
            ->container()
            ->alias('request', Request::class)
        ;
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
    protected function reportException(\Exception $e): void
    {
        $this->getExceptionRuntime()->report($e);
    }

    /**
     * 渲染异常.
     */
    protected function renderException(Request $request, \Exception $e): Response
    {
        return $this->getExceptionRuntime()->render($request, $e);
    }

    /**
     * 路由终止中间件.
     */
    protected function routerTerminateMiddleware(Request $request, Response $response): void
    {
        $this->router->throughTerminateMiddleware($request, $response);
    }

    /**
     * 穿越中间件.
     */
    protected function throughMiddleware(Request $request, \Closure $then): Response
    {
        if (empty($this->resolvedMiddlewares['handle'])) {
            return $then(function (): void {}, $request);
        }

        // @phpstan-ignore-next-line
        return (new Pipeline($this->app->container()))
            ->send([$request])
            ->through($this->resolvedMiddlewares['handle'])
            ->then($then)
        ;
    }

    /**
     * 穿越终止中间件.
     */
    protected function terminateMiddleware(Request $request, Response $response): void
    {
        if (empty($this->resolvedMiddlewares['terminate'])) {
            return;
        }

        (new Pipeline($this->app->container()))
            ->send([$request, $response])
            ->through($this->resolvedMiddlewares['terminate'])
            ->then()
        ;
    }

    /**
     * 分析中间件.
     */
    protected function parseMiddlewares(array $middlewares): array
    {
        $result = [];
        foreach ($middlewares as $middleware) {
            [$middleware, $params] = $this->parseMiddlewareParams($middleware);
            foreach ((new \ReflectionClass($middleware))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if (\in_array($name = $method->getName(), ['handle', 'terminate'], true)) {
                    $result[$name][] = $this->packageMiddleware($middleware.'@'.$name, $params);
                }
            }
        }

        return $result;
    }

    /**
     * 分析中间件及其参数.
     */
    protected function parseMiddlewareParams(string $middleware): array
    {
        $params = '';
        if (str_contains($middleware, ':')) {
            [$middleware, $params] = explode(':', $middleware);
        }

        return [$middleware, $params];
    }

    /**
     * 打包中间件.
     */
    protected function packageMiddleware(string $middleware, string $params): string
    {
        return $middleware.($params ? ':'.$params : '');
    }
}
