<?php

declare(strict_types=1);

namespace Qubus\View\Native;

use LogicException;
use InvalidArgumentException;
use Qubus\View\Native\Exception\FunctionDoesNotExistException;
use Qubus\View\Native\Exception\InvalidTemplateNameException;
use Qubus\View\Native\Exception\TemplateNotFoundException;
use Qubus\View\Native\Exception\ViewException;
use Throwable;

final class NativeLoader implements TemplateEngine
{
    /** @var array<string, string> */
    private array $namespaces = [];

    /** @var array<string, callable> */
    private array $functions = [];

    /** @var array<string, mixed> */
    private array $globals = [];

    private string $extension;

    /**
     * Constructor for the engine.
     *
     * The key of the entries into the namespaces array should be the namespace
     * and the value should be the root directory path for templates in that
     * namespace.
     *
     * The key of the entries to the functions array should be the method name
     * to hook in the template context and the value should be a callable to
     * invoke when this method is called.
     *
     * @param array<string, string> $namespaces The template namespaces to register.
     * @param array<string, callable> $functions The functions to register.
     * @param string $extension The file extension of the templates.
     * @param array<string, mixed> $globals Variables made available to every template.
     * @throws InvalidTemplateNameException
     */
    public function __construct(
        array $namespaces = [],
        array $functions = [],
        string $extension = 'phtml',
        array $globals = []
    ) {
        $extension = ltrim(trim($extension), '.');

        if (
            $extension === ''
            || str_contains($extension, "\0")
            || str_contains($extension, '/')
            || str_contains($extension, '\\')
        ) {
            throw new InvalidArgumentException('The template extension must be a file extension, not a path.');
        }

        $this->extension = $extension;

        foreach ($namespaces as $namespace => $path) {
            $this->addNamespace($namespace, $path);
        }

        $this->globals = $globals;

        $this->functions = array_replace([
            'strip' => \Qubus\Security\Helpers\strip_tags__(...),
            'trim' => \Qubus\Security\Helpers\trim__(...),
            'now' => \Qubus\Support\Helpers\now(...),
            'upper' => strtoupper(...),
            'lower' => strtolower(...),
            'ucfirst' => ucfirst(...),
            'lcfirst' => lcfirst(...),
            'ucwords' => ucwords(...),
            'sprintf' => sprintf(...),
            'wordwrap' => wordwrap(...),
        ], $functions);
    }

    /**
     * @param string $namespace
     * @param string $path
     * @return $this
     * @throws InvalidTemplateNameException
     */
    public function addNamespace(string $namespace, string $path): self
    {
        $namespace = trim($namespace);

        if (!preg_match('/^[A-Za-z0-9_.-]+$/D', $namespace)) {
            throw new InvalidTemplateNameException('Template namespace is invalid.');
        }

        $realPath = realpath($path);

        if ($realPath === false || !is_dir($realPath)) {
            throw new TemplateNotFoundException(sprintf('Template namespace path does not exist: %s.', $path));
        }

        $this->namespaces[$namespace] = rtrim($realPath, DIRECTORY_SEPARATOR);

        return $this;
    }

    public function addFunction(string $name, callable $callback): self
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Function names cannot be empty.');
        }

        $this->functions[$name] = $callback;

        return $this;
    }

    public function addGlobal(string $name, mixed $value): self
    {
        if (trim($name) === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name)) {
            throw new InvalidArgumentException('Global names must be valid PHP variable names.');
        }

        $this->globals[$name] = $value;

        return $this;
    }

    /**
     * @param string $template
     * @param array $data
     * @return string
     * @throws InvalidTemplateNameException
     * @throws ViewException
     * @throws Throwable
     */
    public function render(string $template, array $data = []): string
    {
        return $this->makeContext($template, $data)()->getContent();
    }

    /**
     * @param string $template
     * @param array $data
     * @return string
     * @throws InvalidTemplateNameException
     * @throws Throwable
     * @throws ViewException
     */
    public function fetch(string $template, array $data = []): string
    {
        return $this->render($template, $data);
    }

    /**
     * {@inheritDoc}
     */
    public function exists(string $name): bool
    {
        try {
            $this->getTemplatePath($name);
            return true;
        } catch (InvalidTemplateNameException | TemplateNotFoundException) {
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getTemplatePath(string $name): string
    {
        [$namespace, $template] = $this->parseTemplateName($name);

        if (!isset($this->namespaces[$namespace])) {
            throw new TemplateNotFoundException(sprintf('The %s namespace has not been registered.', $namespace));
        }

        $basePath = $this->namespaces[$namespace];

        $template = trim($template, '/');
        $template = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $template);

        $path = $basePath . DIRECTORY_SEPARATOR . $template . '.' . ltrim($this->extension, '.');
        $realPath = realpath($path);

        if ($realPath === false || !is_file($realPath)) {
            throw new TemplateNotFoundException(sprintf('There is no template at the path: %s.', $path));
        }

        if (!str_starts_with($realPath, $basePath . DIRECTORY_SEPARATOR)) {
            throw new InvalidTemplateNameException('Template path escapes its registered namespace.');
        }

        return $realPath;
    }

    /**
     * {@inheritDoc}
     * @throws FunctionDoesNotExistException
     */
    public function callFunction(string $name, array $arguments = []): mixed
    {
        $callback = $this->functions[$name] ?? null;

        if (!is_callable($callback)) {
            throw new FunctionDoesNotExistException(
                sprintf('The %s function does not exist or is not callable.', $name)
            );
        }

        return $callback(...$arguments);
    }

    /**
     * Apply multiple functions to variable.
     */
    public function batch(mixed $value, string $functions): mixed
    {
        foreach (array_filter(array_map('trim', explode('|', $functions))) as $function) {
            $callback = $this->functions[$function] ?? null;

            if (!is_callable($callback)) {
                throw new LogicException(sprintf('The batch function could not find `%s`.', $function));
            }

            $value = $callback($value);
        }

        return $value;
    }

    public function makeContext(string $template, array $data = [], array $blocks = []): TemplateContext
    {
        return new TemplateContext(
            engine: $this,
            name: $template,
            params: array_replace($this->globals, $data),
            blocks: $blocks
        );
    }

    /**
     * @param string $name
     * @return array
     * @throws InvalidTemplateNameException
     */
    private function parseTemplateName(string $name): array
    {
        if (str_contains($name, "\0") || !preg_match('/^([A-Za-z0-9_.-]+)::(.+)$/D', $name, $matches)) {
            throw new InvalidTemplateNameException('Templates must follow the namespace::template convention.');
        }

        return [$matches[1], $matches[2]];
    }
}
