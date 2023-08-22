<?php

declare(strict_types=1);

namespace Qubus\View\Native;

use LogicException;
use Qubus\View\Native\Exception\FunctionDoesNotExistException;
use Qubus\View\Native\Exception\InvalidTemplateNameException;
use Qubus\View\Native\Exception\TemplateNotFoundException;
use Qubus\View\Native\Exception\ViewException;

final class NativeLoader implements TemplateEngine
{
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
     * @param array  $namespaces The template namespaces to register.
     * @param array  $functions  The functions to register.
     * @param string $extension  The file extension of the templates.
     */
    public function __construct(
        private array $namespaces = [],
        private array $functions = [],
        private string $extension = 'phtml'
    ) {
        $this->functions = array_merge($functions, [
            'strip' => '\Qubus\Security\Helpers\strip_tags__',
            'trim' => '\Qubus\Security\Helpers\trim__',
            'upper' => '\strtoupper',
            'lower' => '\strtolower',
            'ucfirst' => '\ucfirst',
            'lcfirst' => '\lcfirst',
            'ucwords' => '\ucwords',
        ]);
    }

    /**
     * @throws ViewException
     * @throws InvalidTemplateNameException
     */
    public function render(string $template, array $data = []): ?string
    {
        $context = new TemplateContext($this, $template, $data, []);

        return $context()->getContent();
    }

    /**
     * {@inheritDoc}
     */
    public function exists(string $name): bool
    {
        try {
            $this->getTemplatePath($name);
        } catch (TemplateNotFoundException $exception) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getTemplatePath(string $name): string
    {
        if (1 !== preg_match_all('/([^:]+)::(.+)/', $name, $matches)) {
            throw new InvalidTemplateNameException('Templates must follow the namespace::template convention.');
        }

        $namespace = $matches[1][0];
        $template  = $matches[2][0];

        if (!isset($this->namespaces[$namespace])) {
            throw new TemplateNotFoundException(sprintf('The %s namespace has not been registered.', $namespace));
        }

        $templatePath  = rtrim($this->namespaces[$namespace], '/') . '/';
        $templatePath .= ltrim($template, '/');
        $templatePath .= '.' . $this->extension;

        if (!file_exists($templatePath)) {
            throw new TemplateNotFoundException(sprintf('There is no template at the path: %s.', $templatePath));
        }

        return $templatePath;
    }

    /**
     * {@inheritDoc}
     * @throws FunctionDoesNotExistException
     */
    public function callFunction(string|callable $name, array $arguments = []): mixed
    {
        if (!isset($this->functions[$name]) || !is_callable($this->functions[$name])) {
            throw new FunctionDoesNotExistException(
                sprintf(
                    'The %s function does not exist or is not callable.',
                    $name
                )
            );
        }

        return call_user_func_array($this->functions[$name], $arguments);
    }

    /**
     * Apply multiple functions to variable.
     */
    public function batch(string $var, string $functions): mixed
    {
        foreach (explode('|', $functions) as $function) {
            if (isset($this->functions[$function])) {
                $var = call_user_func($this->functions[$function], $var);
            } elseif (is_callable($this->functions[$function])) {
                $var = $this->functions[$function]($var);
            } else {
                throw new LogicException(
                    sprintf(
                        'The batch function could not find the `%s` function.',
                        $this->functions[$function]
                    )
                );
            }
        }

        return $var;
    }
}
