<?php

declare(strict_types=1);

namespace Qubus\View\Native;

use Qubus\Exception\Exception;
use Qubus\View\Native\Exception\InvalidTemplateNameException;
use Qubus\View\Native\Exception\ViewException;
use Throwable;

use function Qubus\Security\Helpers\esc_html;
use function Qubus\Security\Helpers\esc_js;
use function Qubus\Security\Helpers\esc_url;
use function Qubus\Security\Helpers\purify_html;
use function Qubus\Support\Helpers\concat_ws;
use function Qubus\Support\Helpers\truncate_string;

final class TemplateContext
{
    private ?string $parentTemplate = null;

    /** @var array<string, mixed> */
    private array $parentParams = [];

    /** @var array<string, string> */
    private array $stacks = [];

    /** @var list<string> */
    private array $rendering = [];

    /**
     * Constructor for the template context.
     *
     * @param TemplateEngine $engine   The templating engine.
     * @param string         $name     The template name.
     * @param array          $params   The template parameters.
     * @param array          $blocks   Child template blocks
     */
    public function __construct(
        private readonly TemplateEngine $engine,
        private readonly string $name,
        private array $params = [],
        private array $blocks = [],
        array $stacks = [],
        array $rendering = []
    ) {
        $this->parentParams = $params;
        $this->stacks = $stacks;
        $this->rendering = $rendering;
    }

    /**
     * Invoke the template and return the generated content.
     *
     * @return TemplateResult The result of the template.
     * @throws InvalidTemplateNameException
     * @throws Throwable
     * @throws ViewException If an error is encountered rendering the template.
     */
    public function __invoke(): TemplateResult
    {
        if (in_array($this->name, $this->rendering, true)) {
            throw new ViewException(sprintf(
                'Circular template reference detected: %s.',
                implode(' -> ', [...$this->rendering, $this->name])
            ));
        }

        $this->rendering[] = $this->name;

        $content = $this->capture(function (): void {
            $templatePath = $this->engine->getTemplatePath($this->name);

            foreach ($this->params as $parameter => $value) {
                if (
                    is_string($parameter)
                    && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $parameter)
                    && !in_array($parameter, ['this', 'GLOBALS', 'templatePath', 'parameter', 'value'], true)
                ) {
                    ${$parameter} = $value;
                }
            }

            include $templatePath;
        });

        if ($this->parentTemplate !== null) {
            $parentContext = new self(
                engine: $this->engine,
                name: $this->parentTemplate,
                params: $this->parentParams,
                blocks: $this->blocks,
                stacks: $this->stacks,
                rendering: $this->rendering
            );

            return $parentContext();
        }

        return new TemplateResult($content, $this->blocks, $this->stacks);
    }

    /**
     * Define a parent template.
     *
     * @param string $template The name of the parent template.
     * @param array  $params   Parameters to add to the parent template context
     *
     * @throws ViewException If a parent template has already been defined.
     */
    public function parent(string $template, array $params = []): void
    {
        if (null !== $this->parentTemplate) {
            throw new ViewException('A parent template has already been defined.');
        }

        $this->parentTemplate = $template;
        $this->parentParams = array_merge($this->parentParams, $params);
    }

    /**
     * Insert a template.
     *
     * @param string $template The name of the template.
     * @param array $params Parameters to add to the template context
     * @throws InvalidTemplateNameException
     * @throws Throwable
     * @throws ViewException
     */
    public function insert(string $template, array $params = []): void
    {
        echo $this->fetch($template, $params);
    }

    /**
     * @param string $template
     * @param array $params
     * @return string
     * @throws InvalidTemplateNameException
     * @throws Throwable
     * @throws ViewException
     */
    public function fetch(string $template, array $params = []): string
    {
        $context = new self(
            engine: $this->engine,
            name: $template,
            params: array_replace($this->params, $params),
            blocks: $this->blocks,
            stacks: $this->stacks,
            rendering: $this->rendering
        );

        $result = $context();

        $this->blocks = $result->getBlocks();
        $this->stacks = $result->getStacks();

        return $result->getContent();
    }

    /**
     * Render a block.
     *
     * @param string $name The name of the block.
     * @param callable|null $callback
     * @param string|null $default
     * @throws Throwable
     */
    public function block(string $name, ?callable $callback = null, ?string $default = null): void
    {
        if ($callback !== null) {
            $this->blocks[$name] = $this->capture($callback, [$this->params]);
        }

        if (isset($this->blocks[$name])) {
            echo $this->blocks[$name];
            return;
        }

        if ($default !== null) {
            echo $default;
            return;
        }

        throw new ViewException(sprintf('The %s block has not been defined.', $name));
    }

    public function hasBlock(string $name): bool
    {
        return isset($this->blocks[$name]);
    }

    /**
     * @param string $name
     * @param callable|string $content
     * @return void
     * @throws Throwable
     */
    public function push(string $name, callable|string $content): void
    {
        $value = is_callable($content) ? $this->capture($content) : $content;

        $this->stacks[$name] = ($this->stacks[$name] ?? '') . $value;
    }

    /**
     * Add content to the beginning of a named stack.
     *
     * @throws Throwable
     */
    public function prepend(string $name, callable|string $content): void
    {
        $value = is_callable($content) ? $this->capture($content) : $content;

        $this->stacks[$name] = $value . ($this->stacks[$name] ?? '');
    }

    public function hasStack(string $name): bool
    {
        return isset($this->stacks[$name]);
    }

    public function stack(string $name, string $default = ''): void
    {
        echo $this->stacks[$name] ?? $default;
    }

    /**
     * @param string $template
     * @param array $params
     * @param callable|null $slot
     * @return void
     * @throws Throwable
     */
    public function component(string $template, array $params = [], ?callable $slot = null): void
    {
        if ($slot !== null) {
            $params['slot'] = $this->capture($slot);
        }

        $this->insert($template, $params);
    }

    /**
     * Escaping for HTML output.
     *
     * @param string $string HTML element to escape.
     * @param string|null $functions Functions to run the string through.
     * @return string Escaped HTML output.
     * @throws Exception
     */
    public function esc(string $string, ?string $functions = null): string
    {
        if (null !== $functions) {
            $string = (string) $this->engine->batch($string, $functions);
        }

        return esc_html($string);
    }

    public function raw(mixed $value): string
    {
        return (string) $value;
    }

    /**
     * Escaping for inline JavaScript.
     *
     * Example usage:
     *
     *      $escJs = \json_encode("Joshua's \"code\"");
     *      $attribute = $this->>escJs("alert($escJs);");
     *      echo '<input type="button" value="push" onclick="'.$attribute.'" />';
     *
     * @param string $string The string to be escaped.
     * @return string Escaped inline javascript.
     * @throws Exception
     */
    public function escJs(string $string): string
    {
        return esc_js($string);
    }

    /**
     * Escaping for url.
     *
     * @param string $url The url to be escaped.
     * @param array $scheme Optional. An array of acceptable schemes.
     * @param bool $encode Whether url params should be encoded.
     * @return string The escaped $url.
     * @throws Exception
     */
    public function escUrl(string $url, array $scheme = [], bool $encode = false): string
    {
        return esc_url($url, $scheme, $encode);
    }

    /**
     * Makes content safe to print on screen.
     *
     * This function should only be used on output, except uploading
     * images, never use this function on input. All inputted data should be
     * accepted and then purified on output for optimal results. For output of images,
     * make sure to escape with esc_url().
     *
     * @param array|string|null $string $string Text to purify.
     * @param bool $isImage
     * @return string
     */
    public function purify(array|null|string $string, bool $isImage = false): string
    {
        return purify_html($string, $isImage);
    }

    /**
     * Truncates a string to the given length. It will optionally preserve
     * HTML tags if $isHtml is set to true.
     *
     * @param string  $string        The string to truncate.
     * @param int     $limit         The number of characters to truncate.
     * @param string  $continuation  The string to use to denote it was truncated.
     * @param bool    $isHtml        Whether the string has HTML.
     * @return string The truncated string.
     */
    public function truncate(string $string, int $limit, string $continuation = '...', bool $isHtml = false): string
    {
        return truncate_string($string, $limit, $continuation, $isHtml);
    }

    /**
     * Concatenation with separator.
     *
     * @param string $string1    Left string.
     * @param string $string2    Right string.
     * @param string $separator  Delimiter to use between strings. Default: comma.
     * @param string ...$strings List of strings.
     * @return string Concatenated string.
     */
    public function concat(string $string1, string $string2, string $separator = ',', string ...$strings): string
    {
        return concat_ws($string1, $string2, $separator, ...$strings);
    }

    /**
     * Delegate a method call to the templating engine to see if a function has
     * been defined.
     *
     * @param string $name      The method name being called.
     * @param array  $arguments The arguments provided to the method.
     *
     * @return mixed The function result.
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->engine->callFunction($name, $arguments);
    }

    /**
     * Get output from callable
     *
     * @param callable $callback The callback to get the output from.
     * @param array $arguments
     * @return string
     * @throws Throwable
     */
    private function capture(callable $callback, array $arguments = []): string
    {
        $level = ob_get_level();
        ob_start();

        try {
            $callback(...$arguments);
            return (string) ob_get_clean();
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $e;
        }
    }
}
