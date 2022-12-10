<?php

declare(strict_types=1);

namespace Leevel\Kernel\Console;

use Exception;
use Leevel\Console\Command;
use Leevel\Http\Request;
use Leevel\Kernel\IApp;
use Leevel\Kernel\IKernel;
use Nyholm\Psr7\Factory\Psr17Factory;
use Spiral\RoadRunner;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Throwable;

/**
 * RoadRunner.
 *
 * @see https://github.com/spiral/roadrunner
 * @codeCoverageIgnore
 */
class RoadRunnerServer extends Command
{
    /**
     * 命令名字.
     */
    protected string $name = 'rr:server';

    /**
     * 命令行描述.
     */
    protected string $description = 'Start road runner server';

    /**
     * 响应命令.
     */
    public function handle(IApp $app): int
    {
        $this->checkEnvironment();
        $this->setDisplayErrors();
        $kernel = $app->container()->make(IKernel::class);
        $httpFoundationFactory = new HttpFoundationFactory();
        $psr17Factory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
        );
        $worker = RoadRunner\Worker::create();
        $worker = new RoadRunner\Http\PSR7Worker($worker, $psr17Factory, $psr17Factory, $psr17Factory);

        while ($req = $worker->waitRequest()) {
            try {
                $symfonyRequest = $httpFoundationFactory->createRequest($req);
                $request = Request::createFromSymfonyRequest($symfonyRequest);
                $response = $kernel->handle($request);
                $rsp = $psrHttpFactory->createResponse($response);
                $worker->respond($rsp);
                $kernel->terminate($request, $response);
            } catch (Throwable $e) {
                $worker->getWorker()->error((string) $e);
            }
        }

        return 0;
    }

    /**
     * 校验环境.
     *
     * @throws \Exception
     */
    protected function checkEnvironment(): void
    {
        if (!class_exists(RoadRunner\Http\PSR7Worker::class) ||
            !class_exists(HttpFoundationFactory::class) ||
            !class_exists(Psr17Factory::class)) {
            $message = 'Go RoadRunner needs the following packages'.PHP_EOL.
                'composer require spiral/roadrunner ^2.14.1'.PHP_EOL.
                'composer require spiral/dumper ^2.12.1.'.PHP_EOL.
                'composer require nyholm/psr7 ^1.5.'.PHP_EOL.
                'composer require symfony/psr-http-message-bridge ^2.0';

            throw new Exception($message);
        }
    }

    /**
     * 设置显示错误为 stderr.
     */
    protected function setDisplayErrors(): void
    {
        ini_set('display_errors', 'stderr');
    }
}
