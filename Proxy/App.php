<?php

declare(strict_types=1);

namespace Leevel\Kernel\Proxy;

use Leevel\Di\Container;
use Leevel\Kernel\App as BaseApp;

// 这里需要整理di中的方法

/**
 * 代理 app.
 *
 * @method static string                version()                                                                                     获取程序版本.
 * @method static bool                  isConsole()                                                                                   是否为 PHP 运行模式命令行.
 * @method static void                  setPath(string $path)                                                                         设置应用路径.
 * @method static string                path(string $path = '')                                                                       获取基础路径.
 * @method static void                  setAppPath(string $path)                                                                      设置应用路径.
 * @method static string                appPath(string $path = '')                                                                    获取应用路径.
 * @method static void                  setStoragePath(string $path)                                                                  设置存储路径.
 * @method static string                storagePath(string $path = '')                                                                获取存储路径.
 * @method static void                  setThemesPath(string $path)                                                                   设置主题路径.
 * @method static string                themesPath(string $path = '')                                                                 获取主题路径.
 * @method static void                  setConfigPath(string $path)                                                                   设置配置路径.
 * @method static string                configPath(string $path = '')                                                                 获取配置路径.
 * @method static void                  setI18nPath(string $path)                                                                     设置语言包路径.
 * @method static string                i18nPath(?string $path = null)                                                                获取语言包路径.
 * @method static void                  setEnvPath(string $path)                                                                      设置环境变量路径.
 * @method static string                envPath()                                                                                     获取环境变量路径.
 * @method static void                  setEnvFile(string $file)                                                                      设置环境变量文件.
 * @method static string                envFile()                                                                                     获取环境变量文件.
 * @method static string                fullEnvPath()                                                                                 获取环境变量完整路径.
 * @method static void                  setI18nCachedPath(string $i18nCachedPath)                                                     设置语言包缓存路径.
 * @method static string                i18nCachedPath(string $i18n)                                                                  获取语言包缓存路径.
 * @method static bool                  isCachedI18n(string $i18n)                                                                    是否存在语言包缓存.
 * @method static void                  setConfigCachedPath(string $configCachedPath)                                                 设置配置缓存路径.
 * @method static string                configCachedPath()                                                                            获取配置缓存路径.
 * @method static bool                  isCachedConfig()                                                                              是否存在配置缓存.
 * @method static void                  setRouterCachedPath(string $routerCachedPath)                                                 设置路由缓存路径.
 * @method static string                routerCachedPath()                                                                            获取路由缓存路径.
 * @method static bool                  isCachedRouter()                                                                              是否存在路由缓存.
 * @method static string                namespacePath(string $specificClass)                                                          获取命名空间目录真实路径.
 * @method static bool                  isDebug()                                                                                     是否开启调试.
 * @method static bool                  isDevelopment()                                                                               是否为开发环境.
 * @method static string                environment()                                                                                 获取运行环境.
 * @method static mixed                 env(string $name, $defaults = null)                                                           获取应用的环境变量.
 * @method static void                  bootstrap(array $bootstraps)                                                                  初始化应用.
 * @method static void                  registerAppProviders()                                                                        注册应用服务提供者.
 * @method static \Leevel\Di\IContainer container()                                                                                   返回 IOC 容器.
 * @method static \Leevel\Di\IContainer bind($name, $service = null, bool $share = false)                                             注册到容器.
 * @method static \Leevel\Di\IContainer instance($name, $service)                                                                     注册为实例.
 * @method static \Leevel\Di\IContainer singleton($name, $service = null)                                                             注册单一实例.
 * @method static \Leevel\Di\IContainer alias($alias, $value = null)                                                                  设置别名.
 * @method static mixed                 make(string $name, array $args = [], bool $throw = false)                                     创建容器服务并返回.
 * @method static mixed                 call($callback, array $args = [])                                                             实例回调自动注入.
 * @method static void                  remove(string $name)                                                                          删除服务和实例.
 * @method static bool                  exists(string $name)                                                                          服务或者实例是否存在.
 * @method static void                  clear()                                                                                       清理容器.
 * @method static void                  callProviderBootstrap(\Leevel\Di\Provider $provider)                                          执行 bootstrap.
 * @method static \Leevel\Di\Provider   makeProvider(string $provider)                                                                创建服务提供者.
 * @method static \Leevel\Di\Provider   register($provider)                                                                           注册服务提供者.
 * @method static bool                  isBootstrap()                                                                                 是否已经初始化引导.
 * @method static void                  registerProviders(array $providers, array $deferredProviders = [], array $deferredAlias = []) 注册服务提供者.
 */
class App
{
    /**
     * 实现魔术方法 __callStatic.
     *
     * @throws \Error
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        try {
            return self::proxy()->{$method}(...$args);
        } catch (\Error $e) {
            if (str_contains($e->getMessage(), sprintf('Call to undefined method %s::%s()', BaseApp::class, $method))) {
                return self::proxyContainer()->{$method}(...$args);
            }

            throw $e;
        }
    }

    /**
     * 代理 Container 服务.
     */
    public static function proxyContainer(): Container
    {
        // @phpstan-ignore-next-line
        return Container::singletons();
    }

    /**
     * 代理服务.
     */
    public static function proxy(): BaseApp
    {
        // @phpstan-ignore-next-line
        return Container::singletons()->make('app');
    }
}
