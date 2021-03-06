<?php

declare(strict_types=1);

namespace Leevel\Kernel\Utils;

use Leevel\Filesystem\Helper\create_file;
use function Leevel\Filesystem\Helper\create_file;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Throwable;

/**
 * 文档解析 Markdown.
 */
class Doc
{
    /**
     * 文档接口开始标记.
    */
    public const API_START_TAG = '@api(';

    /**
     * 文档接口结束标记.
    */
    public const API_END_TAG = ')';

    /**
     * 文档接口多行结束标记.
    */
    public const API_MULTI_END_TAG = '",';

    /**
     * 解析文档保存基础路径.
    */
    protected string $basePath;

    /**
     * 解析文档的 Git 仓库.
    */
    protected string $git;

    /**
     * 国际化.
    */
    protected string $i18n;

    /**
     * 默认语言.
    */
    protected string $defaultI18n;

    /**
     * 解析文档保存路径.
    */
    protected ?string $savePath = null;

    /**
     * 解析文档行内容.
     */
    protected array $lines = [];

    /**
     * 解析文档的对应的地址.
    */
    protected string $filePath;

    /**
     * 文档生成日志路径.
    */
    protected ?string $logPath = null;

    /**
     * 构造函数.
     */
    public function __construct(string $path, string $i18n, string $defaultI18n, string $git)
    {
        $this->basePath = $path;
        $this->i18n = $i18n;
        $this->defaultI18n = $defaultI18n;
        $this->git = $git;
    }

    /**
     * 解析文档.
     */
    public function handle(string $className): string
    {
        if (false === $lines = $this->parseFileContnet($reflection = new ReflectionClass($className))) {
            return '';
        }
        $this->lines = $lines;
        $this->filePath = str_replace(['\\', 'Tests'], ['/', 'tests'], $className).'.php';

        if (!($markdown = $this->parseClassContent($reflection))) {
            return '';
        }

        $markdown .= $this->parseMethodContents($reflection);

        return $markdown;
    }

    /**
     * 解析文档并保存.
     */
    public function handleAndSave(string $className, ?string $path = null): array|bool
    {
        $markdown = trim($this->handle($className));
        $this->setSavePath($path);
        if (!$markdown || !$this->savePath) {
            return false;
        }

        $this->writeCache($this->savePath, $markdown);

        return [$this->savePath, $markdown];
    }

    /**
     * 设置文档生成日志路径.
     */
    public function setLogPath(string $logPath): void
    {
        $this->logPath = $logPath;
    }

    /**
     * 获取方法内容.
     */
    public static function getMethodBody(string $className, string $method, string $type = '', bool $withMethodName = true): string
    {
        $doc = new static('', '', '', '');
        if (false === $lines = $doc->parseFileContnet(new ReflectionClass($className))) {
            return '';
        }

        $methodInstance = new ReflectionMethod($className, $method);
        $result = $doc->parseMethodBody($lines, $methodInstance, $type);
        if ($withMethodName) {
            $result = '# '.$className.'::'.$method.PHP_EOL.$result;
        }

        return $result;
    }

    /**
     * 获取类内容.
     */
    public static function getClassBody(string $className): string
    {
        $lines = (new static('', '', '', ''))->parseFileContnet($reflectionClass = new ReflectionClass($className));
        $startLine = $reflectionClass->getStartLine() - 1;
        $endLine = $reflectionClass->getEndLine();
        $hasUse = false;
        $isOneFileOneClass = static::isOneFileOneClass($lines);

        $result = [];
        $result[] = 'namespace '.$reflectionClass->getNamespaceName().';';
        $result[] = '';
        foreach ($lines as $k => $v) {
            if ($k < $startLine || $k >= $endLine) {
                if ($k < $startLine && 0 === strpos($v, 'use ') && $isOneFileOneClass) {
                    $result[] = $v;
                    $hasUse = true;
                }

                continue;
            }
            if ($k === $startLine && true === $hasUse) {
                $result[] = '';
            }
            $result[] = $v;
        }

        return implode(PHP_EOL, $result);
    }

    /**
     * 是否一个文件一个类.
     *
     * - 多个文件在同一个类，因为 Psr4 查找规则，只可能在当前文件，则可以共享 use 文件导入
     */
    protected static function isOneFileOneClass(array $contentLines): bool
    {
        $content = implode(PHP_EOL, $contentLines);

        return strpos($content, 'class ') === strrpos($content, 'class ');
    }

    /**
     * 设置保存路径.
     */
    protected function setSavePath(?string $path = null): void
    {
        if (null === $path) {
            return;
        }

        $basePath = str_replace('{i18n}', $this->i18n ? '/'.$this->i18n : '', $this->basePath);
        $this->savePath = $basePath.'/'.$path.'.md';
    }

    /**
     * 方法是否需要被解析.
     */
    protected function isMethodNeedParsed(ReflectionMethod $method): bool
    {
        $name = $method->getName();

        return 0 === strpos($name, 'test') || 0 === strpos($name, 'doc');
    }

    /**
     * 解析文档内容.
     */
    protected function parseFileContnet(ReflectionClass $reflection): array|false
    {
        if (!$fileName = $reflection->getFileName()) {
            return false;
        }

        return explode(PHP_EOL, file_get_contents($fileName));
    }

    /**
     * 解析文档注解内容.
     */
    protected function parseClassContent(ReflectionClass $reflection): string
    {
        if (!($comment = $reflection->getDocComment()) ||
            !($info = $this->parseComment($comment, $reflection->getName()))) {
            return '';
        }

        $data = [];
        $data[] = $this->formatTitle($this->parseDocItem($info, 'title'), '#');
        $data[] = $this->formatFrom($this->git, $this->filePath);
        $data[] = $this->formatDescription($this->parseDocItem($info, 'description'));
        $data[] = $this->formatUsers($reflection);
        $data = array_filter($data);

        // 指定保存路径
        if (isset($info['path'])) {
            $this->setSavePath($info['path']);
        }

        return implode(PHP_EOL, $data).PHP_EOL;
    }

    /**
     * 解析所有方法注解内容.
     */
    protected function parseMethodContents(ReflectionClass $reflection): string
    {
        $markdown = '';
        foreach ($reflection->getMethods() as $method) {
            if (!$this->isMethodNeedParsed($method)) {
                continue;
            }
            $markdown .= $this->parseMethodContent($method, $reflection);
        }

        return $markdown;
    }

    /**
     * 解析方法注解内容.
     */
    protected function parseMethodContent(ReflectionMethod $method, ReflectionClass $reflectionClass): string
    {
        if (!($comment = $method->getDocComment()) ||
            !($info = $this->parseComment($comment, $reflectionClass->getName().'/'.$method->getName()))) {
            return '';
        }

        $data = [];
        $data[] = $this->formatTitle($this->parseDocItem($info, 'title'), $this->parseDocItem($info, 'level', '##'));
        $data[] = $this->formatDescription($this->parseDocItem($info, 'description'));
        $data[] = $this->formatBody($method, $this->parseDocItem($info, 'lang', 'php'));
        $data[] = $this->formatNote($this->parseDocItem($info, 'note'));
        $data = array_filter($data);

        return implode(PHP_EOL, $data).PHP_EOL;
    }

    /**
     * 解析文档项.
     */
    protected function parseDocItem(array $info, string $name, string $defaultValue = ''): string
    {
        $i18n = $this->i18n ? $this->i18n.':' : '';
        $defaultI18n = $this->defaultI18n ? $this->defaultI18n.':' : '';

        return $info[$i18n.$name] ??
            ($info[$defaultI18n.$name] ??
                ($info[$name] ?? $defaultValue));
    }

    /**
     * 格式化标题.
     */
    protected function formatTitle(string $title, string $level = '##'): string
    {
        if ($title) {
            $title = $level." {$title}".PHP_EOL;
        }

        return $title;
    }

    /**
     * 格式化来源.
     */
    protected function formatFrom(string $git, string $filePath): string
    {
        return <<<EOT
            ::: tip Testing Is Documentation
            [{$filePath}]({$git}/{$filePath})
            :::
                
            EOT;
    }

    /**
     * 格式化 uses.
     */
    protected function formatUsers(ReflectionClass $reflection): string
    {
        $uses = $this->parseUseDefined($this->lines, $reflection);
        if ($uses) {
            $uses = <<<EOT
                **Uses**
                
                ``` php
                <?php
                
                {$uses}
                ```

                EOT;
        }

        return $uses;
    }

    /**
     * 格式化描述.
     */
    protected function formatDescription(string $description): string
    {
        if ($description) {
            $description = $description.PHP_EOL;
        }

        return $description;
    }

    /**
     * 格式化注意事项.
     */
    protected function formatNote(string $note): string
    {
        if ($note) {
            $note = <<<EOT
                ::: tip
                {$note}
                :::
                    
                EOT;
        }

        return $note;
    }

    /**
     * 格式化内容.
     */
    protected function formatBody(ReflectionMethod $method, string $lang): string
    {
        $type = 0 === strpos($method->getName(), 'doc') ? 'doc' : '';
        $body = $this->parseMethodBody($this->lines, $method, $type);
        if ($body) {
            $body = <<<EOT
                ``` {$lang}
                {$body}
                ```
                    
                EOT;
        }

        return $body;
    }

    /**
     * 解析 use 导入类.
     */
    protected function parseUseDefined(array $lines, ReflectionClass $classRef): string
    {
        $startLine = $classRef->getStartLine() - 1;
        $result = [];

        foreach ($lines as $k => $v) {
            $v = trim($v);

            if ($k >= $startLine) {
                break;
            }

            if (0 === strpos($v, 'use ') &&
                !in_array($v, ['use Tests\TestCase;'], true) &&
                false === strpos($v, '\\Fixtures\\')) {
                $result[] = $v;
            }
        }

        return implode(PHP_EOL, $result);
    }

    /**
     * 解析方法内容.
     */
    protected function parseMethodBody(array $lines, ReflectionMethod $method, string $type = ''): string
    {
        $startLine = $method->getStartLine() - 1;
        $endLine = $method->getEndLine();
        $offsetLength = 4;
        $result = [];

        // 文档类删除周围的函数定义
        // 删除内容上下的 NowDoc 标记
        if ('doc' === $type) {
            $startLine += 3;
            $endLine -= 2;
            $offsetLength = 12;
        }

        // 返回函数定义
        if ('define' === $type) {
            $commentLine = $this->computeMethodCommentLine($lines, $startLine);
            $startLine -= $commentLine;
            $endLine = $startLine + 1 + $commentLine;
        }

        foreach ($lines as $k => $v) {
            if ($k < $startLine || $k >= $endLine) {
                continue;
            }
        }

        foreach ($lines as $k => $v) {
            if ($k < $startLine || $k >= $endLine) {
                continue;
            }
            $result[] = substr($v, $offsetLength);
        }

        $result = implode(PHP_EOL, $result);
        if ('define' === $type && !str_ends_with($result, ';')) {
            $result .= ';';
        }

        return $result;
    }

    /**
     * 计算方法的注释开始位置.
     */
    protected function computeMethodCommentLine(array $lines, int $startLine): int
    {
        if (!(isset($lines[$startLine - 1]) &&
            '     */' === $lines[$startLine - 1])) {
            return 0;
        }

        $commentIndex = $startLine - 2;
        while (isset($lines[$commentIndex]) && '    /**' !== $lines[$commentIndex]) {
            $commentIndex--;
        }

        return $startLine - $commentIndex;
    }

    /**
     * 获取 API 注解信息.
     *
     * @throws \RuntimeException
     */
    protected function parseComment(string $comment, string $logName): array
    {
        $findApi = $inMultiComment = false;
        $result = [];
        $code = ['$result = ['];
        foreach (explode(PHP_EOL, $comment) as $v) {
            $originalV = $v;
            $v = trim($v, '* ');

            // @api 开始
            if (self::API_START_TAG === $v) {
                $findApi = true;
            } elseif (true === $findApi) {
                // @api 结尾
                if (self::API_END_TAG === $v) {
                    break;
                }

                // 匹配字段格式，以便于支持多行
                if (false === $inMultiComment && preg_match('/^[a-zA-Z:-]+=\"/', $v)) {
                    $code[] = $this->parseSingleComment($v);
                } else {
                    list($content, $inMultiComment) = $this->parseMultiComment($v, $originalV);
                    $code[] = $content;
                }
            }
        }

        $code[] = '];';
        $hasComment = count($code) > 2;
        $code = implode('', $code);

        try {
            $logName = str_replace('\\', '/', $logName).'.php';
            if ($hasComment) {
                eval($code);
                if ($this->logPath) {
                    $this->writeCache($this->logPath.'/logs/'.$logName, '<?php'.PHP_EOL.$code);
                }
            }
        } catch (Throwable) {
            if ($this->logPath) {
                $this->writeCache($errorsLogPath = $this->logPath.'/errors/'.$logName, '<?php'.PHP_EOL.$code);
                $e = sprintf('Documentation error was found and report at %s.', $errorsLogPath);

                throw new RuntimeException($e);
            }

            $e = 'Documentation error was found'.PHP_EOL.PHP_EOL.'<?php'.PHP_EOL.$code;

            throw new RuntimeException($e);
        }

        return $result;
    }

    /**
     * 分析多行注释.
     */
    protected function parseMultiComment(string $content, string $originalContent): array
    {
        $inMultiComment = true;
        if ('' === $content) {
            return [PHP_EOL, $inMultiComment];
        }

        $content = $originalContent;
        if (0 === strpos($content, ' * ')) {
            $content = substr($content, 3);
        }
        if (0 === strpos($content, '     * ')) {
            $content = substr($content, 7);
        }

        // 多行结尾必须独立以便于区分
        if (self::API_MULTI_END_TAG !== trim($content)) {
            $content = $this->parseExecutableCode($content);
        } else {
            $inMultiComment = false;
        }

        $content = str_replace('$', '\$', $content).PHP_EOL;

        return [$content, $inMultiComment];
    }

    /**
     * 分析单行注释.
     */
    protected function parseSingleComment(string $content): string
    {
        $pos = strpos($content, '=');
        $left = '"'.substr($content, 0, $pos).'"';
        $right = $this->normalizeSinggleRight(substr($content, $pos + 1));

        return $left.'=>'.$right;
    }

    /**
     * 整理单行注释右边值.
     */
    protected function normalizeSinggleRight(string $content): string
    {
        $content = $this->parseExecutableCode($content);
        if (0 === strpos($content, '\"')) {
            $content = substr($content, 1);
        }
        if (str_ends_with($content, '\",')) {
            $content = substr($content, 0, strlen($content) - 3).'",';
        }

        return str_replace('$', '\$', $content);
    }

    /**
     * 分析可执行代码.
     */
    protected function parseExecutableCode(string $content): string
    {
        if (preg_match_all('/\{\[(.+)\]\}/', $content, $matches)) {
            $content = str_replace(
                $matches[1][0],
                base64_encode($matches[1][0]),
                $content,
            );
        }

        // 保护单引号不被转义
        $content = str_replace($singleQuote = '\'', $singleQuoteEncoded = base64_encode('single-quote'), $content);
        $content = addslashes($content);
        $content = str_replace($singleQuoteEncoded, $singleQuote, $content);

        if (!empty($matches)) {
            foreach ($matches[1] as $tmp) {
                $content = str_replace('{['.base64_encode($tmp).']}', '".'.$tmp.'."', $content);
            }
        }

        return $content;
    }

    /**
     * 写入缓存.
     */
    protected function writeCache(string $cachePath, string $data): void
    {
        create_file($cachePath, $data);
    }
}

// import fn.
class_exists(create_file::class);
