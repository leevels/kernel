<?php

declare(strict_types=1);

namespace Leevel\Kernel\Bootstrap;

use Exception;
use Leevel\I18n\I18n;
use Leevel\I18n\II18n;
use Leevel\I18n\Load;
use Leevel\Kernel\IApp;

/**
 * 载入语言包.
 */
class LoadI18n
{
    /**
     * 响应.
     */
    public function handle(IApp $app): void
    {
        $i18nDefault = $app
            ->container()
            ->make('option')
            ->get('i18n\\default');
        if ($app->isCachedI18n($i18nDefault)) {
            $data = (array) include $app->i18nCachedPath($i18nDefault);
        } else {
            $load = (new Load([$app->i18nPath()]))
                ->setI18n($i18nDefault)
                ->addDir($this->getExtend($app));
            $data = $load->loadData();
        }

        $app
            ->container()
            ->instance('i18n', $i18n = new I18n($i18nDefault));
        $app
            ->container()
            ->alias('i18n', [II18n::class, I18n::class]);
        $i18n->addtext($i18nDefault, $data);
    }

    /**
     * 获取扩展语言包.
     *
     * @throws \Exception
     */
    public function getExtend(IApp $app): array
    {
        $extend = $app
            ->container()
            ->make('option')
            ->get(':composer.i18ns', []);
        $path = $app->path();

        $extend = array_map(function (string $item) use ($path) {
            if (!is_file($item)) {
                $item = $path.'/'.$item;
            }

            if (!is_dir($item)) {
                $e = sprintf('I18n dir %s is not exist.', $item);

                throw new Exception($e);
            }

            return $item;
        }, $extend);

        return $extend;
    }
}
