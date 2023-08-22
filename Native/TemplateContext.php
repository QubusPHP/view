<?php

declare(strict_types=1);

namespace Qubus\View\Native;

use Qubus\View\Native\Exception\InvalidTemplateNameException;
use Qubus\View\Native\Exception\ViewException;

use function Qubus\Security\Helpers\esc_html;
use function Qubus\Security\Helpers\esc_js;
use function Qubus\Security\Helpers\esc_url;
use function Qubus\Security\Helpers\purify_html;
use function Qubus\Support\Helpers\concat_ws;
use function Qubus\Support\Helpers\truncate_string;

final class TemplateContext
{
    private ?string $parentTemplate = null;
    private array $parentParams;

    /**
     * Constructor for the template context.
     *
     * @param TemplateEngine $engine   The templating engine.
     * @param string           $name     The template name.
     * @param array            $params   The template parameters.
     * @param array            $blocks   Child template blocks
     */
    public function __construct(
        private TemplateEngine $engine,
        private string $name,
        private array $params = [],
        private array $blocks = []
    ) {
        // By default, this template has no parent
        $this->parentTemplate = null;
        $this->parentParams   = $params;
    }

    /**
     * Invoke the template and return the generated content.
     *
     * @return TemplateResult The result of the template.
     * @throws ViewException If an error is encountered rendering the template.
     * @throws InvalidTemplateNameException
     */
    public function __invoke(): TemplateResult
    {
        $content = $this->getOutput(function ($params) {
            $templatePath = $this->engine->getTemplatePath($this->name);
            extract($params, EXTR_SKIP);
            include $templatePath;
        });

        if (null !== $this->parentTemplate) {
            $parentContext = new self($this->engine, $this->parentTemplate, $this->parentParams, $this->blocks);
            return $parentContext();
        }

        return new TemplateResult($content, $this->blocks);
    }

    /**
     * Get output from callable
     *
     * @param callable $callback The callback to get the output from.
     *
     * @return string
     */
    private function getOutput(callable $callback): string
    {
        ob_start();

        try {
            $callback($this->params);
        } finally {
            $output = ob_get_contents();
            ob_end_clean();
        }

        return $output;
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
     * @param array  $params   Parameters to add to the template context
     */
    public function insert(string $template, array $params = []): void
    {
        $context = new self($this->engine, $template, array_merge($this->params, $params), $this->blocks);
        try {
            $result = $context();
            $this->blocks = $result->getBlocks();
            echo $result->getContent();
        } catch (InvalidTemplateNameException | ViewException $e) {
            echo $e->getMessage();
        }
    }

    /**
     * Render a block.
     *
     * @param string $name The name of the block.
     * @throws ViewException
     */
    public function block(string $name, callable $callback = null): void
    {
        if (null !== $callback) {
            $this->blocks[$name] = $this->getOutput($callback);
        }

        if (!isset($this->blocks[$name])) {
            throw new ViewException(sprintf('The %s block has not been defined.', $name));
        }

        echo $this->blocks[$name];
    }

    /**
     * Escaping for HTML output.
     *
     * @param string $string Html element to escape.
     * @param string|null $functions Functions to run the string through.
     * @return string Escaped HTML output.
     */
    public function esc(string $string, string $functions = null): string
    {
        if (null !== $functions) {
            $string = (string) $this->engine->batch($string, $functions);
        }

        return esc_html($string);
    }

    /**
     * Escaping for inline javascript.
     *
     * Example usage:
     *
     *      $esc_js = json_encode("Joshua's \"code\"");
     *      $attribute = esc_js("alert($esc_js);");
     *      echo '<input type="button" value="push" onclick="'.$attribute.'" />';
     *
     * @param string $string The string to be escaped.
     * @return string Escaped inline javascript.
     */
    public function escJs(string $string): string
    {
        return esc_js($string);
    }

    /**
     * Escaping for url.
     *
     * @param string $url    The url to be escaped.
     * @param array  $scheme Optional. An array of acceptable schemes.
     * @param bool   $encode Whether url params should be encoded.
     * @return string The escaped $url.
     */
    public function escUrl(string $url, array $scheme = [], bool $encode = false): string
    {
        return esc_url($url, $scheme, $encode);
    }

    /**
     * Makes content safe to print on screen.
     *
     * This function should only be used on output, with the exception of uploading
     * images, never use this function on input. All inputted data should be
     * accepted and then purified on output for optimal results. For output of images,
     * make sure to escape with esc_url().
     *
     * @param string $string Text to purify.
     */
    public function purify(string $string): string
    {
        return purify_html($string);
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
        return truncate_string($string, $limit, $continuation = '...', $isHtml = false);
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
    public function concat(string $string1, string $string2, string $separator = ',', ...$strings): string
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
    public function __call(string $name, array $arguments)
    {
        return $this->engine->callFunction($name, $arguments);
    }
}
