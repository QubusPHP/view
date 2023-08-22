<?php

declare(strict_types=1);

namespace Qubus\View;

use Closure;
use Qubus\Exception\Data\TypeException;
use Qubus\View\Adapter\Adapter;
use Qubus\View\Adapter\FileAdapter;
use RuntimeException;

use function array_pop;
use function explode;
use function implode;
use function md5;
use function preg_replace;
use function sprintf;
use function strtr;

final class Loader implements Renderer
{
    public const VERSION = '1.0.0';
    public const CLASS_PREFIX = '__ScaffoldTemplate_';
    public const RECOMPILE_NEVER = -1;
    public const RECOMPILE_NORMAL = 0;
    public const RECOMPILE_ALWAYS = 1;

    private bool $exceptionHandler = true;

    private Adapter $target;

    /**
     * @var array
     */
    private array $options = [];

    private array $paths = [];
    /**
     * @var array
     */
    private array $cache = [];

    /** @var string $extension */
    private string $extension;

    public function __construct(array $options = [])
    {
        if (!isset($options['source'])) {
            throw new RuntimeException('missing source directory');
        }

        if (!isset($options['target'])) {
            throw new RuntimeException('missing target directory');
        }

        $target = $options['target'];

        $source = $options['source'];
        if ($source instanceof Closure) {
            $source = $source->__invoke();
        }

        $options += [
            'mode' => self::RECOMPILE_NORMAL,
            'mkdir' => 0777,
            'helpers' => [],
            'extension' => '.html'
        ];

        if (!isset($options['adapter'])) {
            $options['adapter'] = new FileAdapter($source);
        }

        if (!is_dir($target)) {
            if ($options['mkdir'] === false) {
                throw new RuntimeException(sprintf('target directory %s not found', $target));
            }
            if (!mkdir($target, $options['mkdir'], true)) {
                throw new RuntimeException(sprintf('unable to create target directory %s', $target));
            }
        }

        $this->options = [
            'source' => is_array($source) ? $source : [$source],
            'target' => $target,
            'mode' => $options['mode'],
            'adapter' => $options['adapter'],
            'helpers' => $options['helpers'],
            'extension' => $options['extension'],
        ];

        $this->paths = [];
        $this->cache = [];
    }

    /**
     * @throws TypeException
     */
    protected function handleSyntaxError($exception): void
    {
        if ($this->exceptionHandler) {
            $adapter = $this->getAdapter();
            echo $this->renderString(file_get_contents(__DIR__ . '/templates/debug.html'), [
                'exception' => $exception,
                'source' => $adapter->getContents($exception->getTemplateFile()),
                'styles' => file_get_contents(
                    __DIR__ . '/templates/core.css'
                ) . file_get_contents(
                    __DIR__ . '/templates/exception.css'
                ),
                'loader' => $this
            ]);
            die();
        } else {
            throw $exception;
        }
    }

    /**
     * Get the expected extension of the template file.
     *
     * @return string
     */
    private function getTemplateExtension(): string
    {
        return '.' . ltrim($this->options['extension'], '.');
    }

    /**
     * Remove Extension from file.
     *
     * @param string $fileName
     * @return string
     */
    private function removeExtension(string $fileName): string
    {
        return str_replace([
            '.blade.php', '.blade.html', 'blade.htm', '.blade.tpl', '.pug', '.php', '.tpl', '.twig', '.blade',
            '.html', '.phtml', '.htm', '.templet.php', '.templet.html', '.templet.htm', '.templet.tpl', '.templet',
            '.template.php', '.template.html', '.template.htm', '.template.tpl', '.txt', '.txt',
            '.frame.php', '.frame.html', '.frame.htm', '.frame.tpl', '.frm', '.fr', '.fram',
        ], '', $fileName);
    }

    private function getClassName(string $path): string
    {
        return self::CLASS_PREFIX . md5($path);
    }

    public function normalizePath(string $path): array
    {
        $path = preg_replace('#/{2,}#', '/', strtr($path, '\\', '/'));
        $parts = [];
        foreach (explode('/', $path) as $i => $part) {
            if ($part === '..') {
                if (empty($parts)) {
                    throw new RuntimeException(sprintf(
                        '%s resolves to a path outside source.',
                        $path
                    ));
                } else {
                    array_pop($parts);
                }
            } elseif ($part !== '.') {
                $parts[] = $part;
            }
        }
        return $parts;
    }

    public function resolvePath(string $template, string $from = ''): string
    {
        /** Remove the extension from the file. */
        $template = $this->removeExtension($template);

        /** Replace the dot notation of directories and append file extension. */
        $template = str_replace('.', DIRECTORY_SEPARATOR, $template) . $this->getTemplateExtension();

        foreach ($this->options['source'] as $sourcePath) {
            $source = implode('/', $this->normalizePath($sourcePath));
            $file = $source . '/' . ltrim($template, '/');
            if (is_file($file)) {
                $parts = $this->normalizePath($source . '/' . dirname($from) . '/' . $template);
                foreach ($this->normalizePath($source) as $i => $part) {
                    if ($part !== $parts[$i]) {
                        throw new RuntimeException(sprintf('%s is outside the source directory', $template));
                    }
                }
                return $template;
            }
        }

        throw new RuntimeException(sprintf('Template %s not found.', $template));
    }

    /**
     * @return Adapter
     */
    protected function getAdapter(): Adapter
    {
        return $this->options['adapter'];
    }

    public function compile(string $template, $mode = null): Loader
    {
        $adapter = $this->getAdapter();

        $path = $this->resolvePath($template);

        $class = $this->getClassName($path);

        if (!$adapter->isReadable($path)) {
            throw new RuntimeException(sprintf('%s is not a valid readable template', $template));
        }

        $classFile = $this->options['target'] . '/' . $class . '.php';

        if (!isset($mode)) {
            $mode = $this->options['mode'];
        }

        $compile = match ($mode) {
            self::RECOMPILE_ALWAYS => true,
            self::RECOMPILE_NEVER => !file_exists($classFile),
            default => !file_exists($classFile) || filemtime($classFile) < $adapter->lastModified($path),
        };

        if ($compile) {
            $this->compileOrFail($adapter, $path, $class, $classFile);
        }

        return $this;
    }

    /**
     * @throws TypeException
     */
    public function load(string|Template $template, string $from = '')
    {
        if ($template instanceof Template) {
            return $template;
        }

        if (!is_string($template)) {
            throw new TypeException('string expected');
        }

        $adapter = $this->getAdapter();

        if (isset($this->paths[$template . $from])) {
            $path = $this->paths[$template . $from];
        } else {
            $path = $this->resolvePath($template, $from);
            $this->paths[$template . $from] = $path;
        }

        $class = $this->getClassName($path);

        if (isset($this->cache[$class])) {
            return $this->cache[$class];
        }

        if (!class_exists($class, false)) {
            if (!$adapter->isReadable($path)) {
                throw new RuntimeException(sprintf('%s is not a valid readable template.', $path));
            }

            $classFile = $this->options['target'] . '/' . $class . '.php';

            $compile = match ($this->options['mode']) {
                self::RECOMPILE_ALWAYS => true,
                self::RECOMPILE_NEVER => !file_exists($classFile),
                default => !file_exists($classFile) || filemtime($classFile) < $adapter->lastModified($path),
            };

            if ($compile) {
                $this->compileOrFail($adapter, $path, $class, $classFile);
            }

            require_once $classFile;
        }

        return $this->cache[$class] = new $class($this, $this->options['helpers']);
    }

    private function compileOrFail(Adapter $adapter, string $path, string $class, string $classFile): void
    {
        $target = new FileAdapter($this->options['target']);
        try {
            $lexer = new Lexer($adapter->getContents($path));
            $parser = new Parser($lexer->tokenize());
            $compiler = new Compiler($parser->parse($path, $class));
            $compiled = $compiler->compile();
            $target->putContents($classFile, $compiled);
        } catch (SyntaxErrorException $e) {
            $e->setTemplateFile($path);
            $this->handleSyntaxError($e->setMessage($path . ': ' . $e->getMessage()));
        }
    }

    /**
     * @throws TypeException
     */
    public function loadFromString($template)
    {
        if (!is_string($template)) {
            throw new TypeException('string expected');
        }

        $class = $this->getClassName($template);
        $target = new FileAdapter($this->options['target']);

        if (isset($this->cache[$class])) {
            return $this->cache[$class];
        }

        $classFile = $this->options['target'] . '/' . $class . '.php';
        $path = "";

        try {
            $lexer = new Lexer($template);
            $parser = new Parser($lexer->tokenize());
            $compiler = new Compiler($parser->parse($path, $class));
            $compiled = $compiler->compile();
            $target->putContents($classFile, $compiled);
        } catch (SyntaxErrorException $e) {
            $e->setTemplateFile($path);
            $this->handleSyntaxError($e->setMessage($path . ': ' . $e->getMessage()));
        }
        require_once $classFile;

        return $this->cache[$class] = new $class($this, $this->options['helpers']);
    }

    /**
     * @param Template|string $template
     * @param array $data
     * @return void
     * @throws TypeException
     */
    public function render(Template|string $template, array $data = []): void
    {
        $this->load($template)->display($data);
    }

    /**
     * @throws TypeException
     */
    public function renderString($source, array $data = [])
    {
        return $this->loadFromString($source)->display($data);
    }

    public function getVersion(): string
    {
        return self::VERSION;
    }

    public function setExceptionHandler(bool $bool = true): Loader
    {
        $this->exceptionHandler = $bool;
        return $this;
    }
}
