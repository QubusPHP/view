<?php

declare(strict_types=1);

namespace Qubus\View;

use Closure;
use Qubus\Exception\Data\TypeException;
use Qubus\View\Adapter\Adapter;
use Qubus\View\Adapter\FileAdapter;
use RuntimeException;
use InvalidArgumentException;
use Throwable;

use function array_pop;
use function explode;
use function implode;
use function md5;
use function preg_replace;
use function realpath;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strtr;

final class Loader implements Renderer
{
    public const string VERSION = '3.2.0';
    public const string CLASS_PREFIX = '__ScaffoldTemplate_';
    public const int RECOMPILE_NEVER = -1;
    public const int RECOMPILE_NORMAL = 0;
    public const int RECOMPILE_ALWAYS = 1;

    private bool $exceptionHandler = true;

    /**
     * @var array
     */
    private array $options = [];

    private array $paths = [];
    /**
     * @var array
     */
    private array $cache = [];

    public function __construct(array $options = [])
    {
        if (!isset($options['source'])) {
            throw new RuntimeException('missing source directory');
        }

        if (!isset($options['target'])) {
            throw new RuntimeException('missing target directory');
        }

        $target = $options['target'];
        if (!is_string($target) || $target === '' || str_contains($target, "\0")) {
            throw new InvalidArgumentException('target must be a non-empty directory path');
        }

        $source = $options['source'];
        if ($source instanceof Closure) {
            $source = $source->__invoke();
        }

        if ((!is_string($source) && !is_array($source)) || $source === [] || $source === '') {
            throw new InvalidArgumentException('source must be a directory path or a non-empty array of paths');
        }
        foreach ((array) $source as $sourcePath) {
            if (!is_string($sourcePath) || $sourcePath === '' || str_contains($sourcePath, "\0")) {
                throw new InvalidArgumentException('source paths must be non-empty strings');
            }
        }

        $options += [
            'mode' => self::RECOMPILE_NORMAL,
            'mkdir' => 0777,
            'helpers' => [],
            'extension' => '.html',
            'exception_handler' => true,
        ];

        $extension = ltrim(trim((string) $options['extension']), '.');
        if (
            $extension === ''
            || str_contains($extension, "\0")
            || str_contains($extension, '/')
            || str_contains($extension, '\\')
        ) {
            throw new InvalidArgumentException('template extension must be a file extension, not a path');
        }
        $options['extension'] = $extension;

        if (!isset($options['adapter'])) {
            $options['adapter'] = new FileAdapter($source);
        }
        if (!$options['adapter'] instanceof Adapter) {
            throw new InvalidArgumentException('adapter must implement ' . Adapter::class);
        }
        if (!is_array($options['helpers'])) {
            throw new InvalidArgumentException('helpers must be an array of callables');
        }

        if (!is_dir($target)) {
            if ($options['mkdir'] === false) {
                throw new RuntimeException(sprintf('target directory %s not found', $target));
            }
            if (!mkdir($target, $options['mkdir'], true) && !is_dir($target)) {
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
        $this->exceptionHandler = (bool) $options['exception_handler'];
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
        return '.' . $this->options['extension'];
    }

    /**
     * Remove Extension from file.
     *
     * @param string $fileName
     * @return string
     */
    private function removeExtension(string $fileName): string
    {
        $extensions = [
            '.blade.php', '.blade.html', 'blade.htm', '.blade.tpl', '.pug', '.php', '.tpl', '.twig', '.blade',
            '.html', '.phtml', '.htm', '.templet.php', '.templet.html', '.templet.htm', '.templet.tpl', '.templet',
            '.template.php', '.template.html', '.template.htm', '.template.tpl', '.txt', '.txt',
            '.frame.php', '.frame.html', '.frame.htm', '.frame.tpl', '.frm', '.fr', '.fram',
        ];

        foreach ($extensions as $extension) {
            if (str_ends_with($fileName, $extension)) {
                return substr($fileName, 0, -strlen($extension));
            }
        }

        return $fileName;
    }

    private function getClassName(string $path): string
    {
        return self::CLASS_PREFIX . md5($path);
    }

    public function normalizePath(string $path): array
    {
        if (str_contains($path, "\0")) {
            throw new RuntimeException('template paths cannot contain null bytes');
        }

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
            } elseif ($part !== '.' && ($part !== '' || $i === 0)) {
                $parts[] = $part;
            }
        }
        return $parts;
    }

    public function resolvePath(string $template, string $from = ''): string
    {
        if ($template === '' || str_contains($template, "\0")) {
            throw new RuntimeException('template names must be non-empty and cannot contain null bytes');
        }

        $hadLeadingSlash = str_starts_with(strtr($template, '\\', '/'), '/');

        /** Remove the extension from the file. */
        $template = $this->removeExtension($template);

        $templateParts = $this->normalizePath(strtr($template, '\\', '/'));
        $template = implode('/', array_filter($templateParts, static fn ($part): bool => $part !== ''));

        /** Replace the dot notation of directories and append file extension. */
        $template = str_replace('.', '/', $template) . $this->getTemplateExtension();
        $template = ltrim($template, '/');

        $candidates = [$template];
        if ($from !== '') {
            $relative = implode('/', $this->normalizePath(dirname($from) . '/' . $template));
            if ($relative !== $template) {
                $candidates[] = ltrim($relative, '/');
            }
        }

        $adapter = $this->getAdapter();
        foreach ($candidates as $candidate) {
            if ($adapter->isReadable($candidate)) {
                $this->assertPathIsInsideSource($adapter, $candidate);
                return $candidate;
            }
        }

        throw new RuntimeException(sprintf(
            'Template %s%s not found.',
            $hadLeadingSlash ? '/' : '',
            $template
        ));
    }

    private function assertPathIsInsideSource(Adapter $adapter, string $path): void
    {
        if (!$adapter instanceof FileAdapter) {
            return;
        }

        $resolved = realpath($adapter->getStreamUrl($path));
        if ($resolved === false) {
            throw new RuntimeException(sprintf('Template %s not found.', $path));
        }

        foreach ($this->options['source'] as $source) {
            $root = realpath($source);
            $sourcePrefix = $root === false ? null : rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if ($sourcePrefix !== null && str_starts_with($resolved, $sourcePrefix)) {
                return;
            }
        }

        throw new RuntimeException(sprintf('%s is outside the source directory', $path));
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

        $pathKey = $template . "\0" . $from;
        if (isset($this->paths[$pathKey])) {
            $path = $this->paths[$pathKey];
        } else {
            $path = $this->resolvePath($template, $from);
            $this->paths[$pathKey] = $path;
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

    /**
     * @throws TypeException
     */
    private function compileOrFail(Adapter $adapter, string $path, string $class, string $classFile): void
    {
        $target = new FileAdapter($this->options['target']);
        try {
            $lexer = new Lexer($adapter->getContents($path));
            $parser = new Parser($lexer->tokenize());
            $compiler = new Compiler($parser->parse($path, $class));
            $compiled = $compiler->compile();
            if ($target->putContents($classFile, $compiled) === false) {
                throw new RuntimeException(sprintf('unable to write compiled template %s', $classFile));
            }
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
            if ($target->putContents($classFile, $compiled) === false) {
                throw new RuntimeException(sprintf('unable to write compiled template %s', $classFile));
            }
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

    /**
     * Render a template and return its output instead of sending it to the output buffer.
     *
     * @param Template|string $template
     * @param array $data
     * @return string
     * @throws TypeException
     * @throws Throwable
     */
    public function fetch(Template|string $template, array $data = []): string
    {
        return $this->load($template)->render($data);
    }

    /**
     * Compile a source string and return its rendered output.
     *
     * @throws TypeException
     */
    public function fetchString(string $source, array $data = []): string
    {
        return $this->loadFromString($source)->render($data);
    }

    public function exists(string $template, string $from = ''): bool
    {
        try {
            $this->resolvePath($template, $from);
            return true;
        } catch (RuntimeException) {
            return false;
        }
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
