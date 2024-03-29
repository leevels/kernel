<?php

declare(strict_types=1);

namespace Leevel\Kernel\Testing;

/**
 * 助手方法.
 */
trait Helper
{
    /**
     * 执行方法.
     */
    protected function invokeTestMethod(object|string $classObj, string $method, array $args = []): mixed
    {
        $method = $this->parseTestMethod($classObj, $method);
        if ($args) {
            return $method->invokeArgs($classObj, $args);
        }

        return $method->invoke($classObj);
    }

    /**
     * 执行静态方法.
     */
    protected function invokeTestStaticMethod(object|string $classOrObject, string $method, array $args = []): mixed
    {
        $method = $this->parseTestMethod($classOrObject, $method);
        if ($args) {
            return $method->invokeArgs(null, $args);
        }

        return $method->invoke(null);
    }

    /**
     * 获取反射对象属性值.
     */
    protected function getTestProperty(object|string $classOrObject, string $prop): mixed
    {
        return $this
            ->parseTestProperty($classOrObject, $prop)
            ->getValue(\is_object($classOrObject) ? $classOrObject : null)
        ;
    }

    /**
     * 设置反射对象属性值.
     */
    protected function setTestProperty(object|string $classOrObject, string $prop, mixed $value): void
    {
        $value = \is_object($classOrObject) ? [$classOrObject, $value] : [$value];
        $this
            ->parseTestProperty($classOrObject, $prop)
            ->setValue(...$value)
        ;
    }

    /**
     * 分析对象反射属性.
     */
    protected function parseTestProperty(object|string $classOrObject, string $prop): \ReflectionProperty
    {
        /** @phpstan-ignore-next-line */
        $reflected = new \ReflectionClass($classOrObject);
        $property = $reflected->getProperty($prop);
        $property->setAccessible(true);

        return $property;
    }

    /**
     * 分析对象反射方法.
     */
    protected function parseTestMethod(object|string $classOrObject, string $method): \ReflectionMethod
    {
        /** @phpstan-ignore-next-line */
        $method = new \ReflectionMethod($classOrObject, $method);
        $method->setAccessible(true);

        return $method;
    }

    /**
     * 清理内容.
     */
    protected function normalizeContent(string $content): string
    {
        return str_replace([' ', "\t", "\n", "\r"], '', $content);
    }

    /**
     * 调试 JSON.
     */
    protected function varJson(array $data, ?int $id = null): string
    {
        $backtrace = debug_backtrace();
        if ('varJson' === ($method = $backtrace[1]['function'])) {
            $method = $backtrace[2]['function'];
        }
        $method .= $id;

        [$traceDir, $className] = $this->makeLogsDir();
        file_put_contents(
            $traceDir.'/'.sprintf('%s::%s.log', $className, $method),
            $result = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        return $result ?: '';
    }

    /**
     * 时间波动断言.
     *
     * - 程序可能在数秒不等的时间内执行，需要给定一个范围.
     */
    protected function assertTimeRange(string $data, int|string ...$timeRange): void
    {
        // @phpstan-ignore-next-line
        $this->assertTrue(\in_array($data, $timeRange, true));
    }

    /**
     * 断言真别名.
     */
    protected function assert(bool $data): void
    {
        // @phpstan-ignore-next-line
        $this->assertTrue($data);
    }

    /**
     * 读取缓存区数据.
     */
    protected function obGetContents(\Closure $call): string
    {
        ob_start();
        $call();
        $contents = ob_get_contents();
        ob_end_clean();

        return $contents ?: '';
    }
}
