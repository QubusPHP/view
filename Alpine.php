<?php

declare(strict_types=1);

namespace Qubus\View;

use InvalidArgumentException;
use JsonSerializable;

use function array_is_list;
use function array_map;
use function htmlspecialchars;
use function implode;
use function is_array;
use function is_int;
use function is_object;
use function is_scalar;
use function json_encode;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function trim;

use const ENT_HTML5;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const JSON_HEX_AMP;
use const JSON_HEX_APOS;
use const JSON_HEX_QUOT;
use const JSON_HEX_TAG;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Server-side builders for Alpine directives and safely hydrated state.
 */
final class Alpine
{
    private const array DIRECTIVE_ALIASES = [
        'data' => 'x-data',
        'init' => 'x-init',
        'show' => 'x-show',
        'text' => 'x-text',
        'html' => 'x-html',
        'model' => 'x-model',
        'modelable' => 'x-modelable',
        'for' => 'x-for',
        'transition' => 'x-transition',
        'effect' => 'x-effect',
        'ignore' => 'x-ignore',
        'ref' => 'x-ref',
        'cloak' => 'x-cloak',
        'teleport' => 'x-teleport',
        'if' => 'x-if',
        'id' => 'x-id',
    ];

    private const int JSON_FLAGS = JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
    | JSON_UNESCAPED_SLASHES
    | JSON_THROW_ON_ERROR;

    /**
     * Build one or more Alpine directive attributes.
     *
     * Short aliases such as `data`, `on:click`, and `bind:class` are accepted,
     * as are Alpine's `x-*`, `@event`, and `:attribute` forms.
     */
    public static function attributes(array $directives): HtmlString
    {
        $attributes = [];

        foreach ($directives as $name => $value) {
            if (is_int($name)) {
                $name = (string) $value;
                $value = null;
            }

            $name = self::normalizeDirective((string) $name);

            if ($name === 'x-data' && $value === []) {
                $value = (object) [];
            }

            if ($value === null) {
                $attributes[] = $name;
                continue;
            }

            if (is_array($value) || is_object($value) || $value instanceof JsonSerializable) {
                $value = self::json($value);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (!is_scalar($value)) {
                throw new InvalidArgumentException('Alpine directive values must be scalar or JSON serializable.');
            }

            $attributes[] = $name . '="' . self::escapeAttribute((string) $value) . '"';
        }

        return new HtmlString(implode(' ', $attributes));
    }

    /** Build an x-data attribute from inline state or a component expression. */
    public static function data(mixed $state = []): HtmlString
    {
        return self::attributes(['data' => $state]);
    }

    /** Build an x-data attribute referencing a registered Alpine.data provider. */
    public static function component(string $name, array $arguments = []): HtmlString
    {
        if (!preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*(?:\.[A-Za-z_$][A-Za-z0-9_$]*)*$/D', $name)) {
            throw new InvalidArgumentException('Alpine component names must be valid JavaScript identifiers.');
        }

        if ($arguments === []) {
            return self::attributes(['data' => $name]);
        }

        $encoded = array_is_list($arguments)
        ? implode(', ', array_map(self::json(...), $arguments))
        : self::json($arguments);

        return self::attributes(['data' => $name . '(' . $encoded . ')']);
    }

    /**
     * Register server-provided state as an Alpine store when Alpine initializes.
     */
    public static function store(string $name, mixed $state, ?string $nonce = null): HtmlString
    {
        if (trim($name) === '' || str_contains($name, "\0")) {
            throw new InvalidArgumentException('Alpine store names cannot be empty or contain null bytes.');
        }

        $nonceAttribute = $nonce === null ? '' : ' nonce="' . self::escapeAttribute($nonce) . '"';
        $script = 'document.addEventListener("alpine:init",function(){Alpine.store('
        . self::json($name) . ',' . self::json($state) . ');});';

        return new HtmlString('<script' . $nonceAttribute . '>' . $script . '</script>');
    }

    /** Build an Alpine script tag without coupling applications to a CDN/version. */
    public static function script(string $source, ?string $nonce = null, array $attributes = []): HtmlString
    {
        $source = trim($source);
        if (
            $source === ''
            || str_contains($source, "\0")
            || preg_match('/[\x00-\x1F\x7F]/', $source)
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $source)
                && !preg_match('/^https?:/i', $source)
        ) {
            throw new InvalidArgumentException('The Alpine script source must be a relative, HTTP, or HTTPS URL.');
        }

        $allowed = ['async', 'crossorigin', 'defer', 'integrity', 'referrerpolicy', 'type'];
        $tagAttributes = ['src' => $source, 'defer' => null];
        if ($nonce !== null) {
            $tagAttributes['nonce'] = $nonce;
        }

        foreach ($attributes as $name => $value) {
            $name = strtolower((string) $name);
            if (!in_array($name, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported Alpine script attribute: ' . $name . '.');
            }
            if ($value === false) {
                unset($tagAttributes[$name]);
                continue;
            }
            $tagAttributes[$name] = $value === true ? null : $value;
        }

        $rendered = [];
        foreach ($tagAttributes as $name => $value) {
            $rendered[] = $value === null
            ? $name
            : $name . '="' . self::escapeAttribute((string) $value) . '"';
        }

        return new HtmlString('<script ' . implode(' ', $rendered) . '></script>');
    }

    /** Build the conventional x-cloak rule, optionally with a CSP nonce. */
    public static function cloakStyle(?string $nonce = null): HtmlString
    {
        $nonceAttribute = $nonce === null ? '' : ' nonce="' . self::escapeAttribute($nonce) . '"';
        return new HtmlString('<style' . $nonceAttribute . '>[x-cloak]{display:none!important}</style>');
    }

    private static function normalizeDirective(string $name): string
    {
        $name = trim($name);
        $lower = strtolower($name);

        if (isset(self::DIRECTIVE_ALIASES[$lower])) {
            return self::DIRECTIVE_ALIASES[$lower];
        }
        if (str_starts_with($lower, 'on:')) {
            $name = 'x-on:' . substr($name, 3);
        } elseif (str_starts_with($lower, 'bind:')) {
            $name = 'x-bind:' . substr($name, 5);
        } elseif (str_starts_with($lower, 'transition.')) {
            $name = 'x-' . $name;
        }

        if (
            !preg_match(
                '/^(?:x-[A-Za-z][A-Za-z0-9-]*(?::[A-Za-z0-9_:-]+)?(?:\.[A-Za-z0-9_-]+)*'
                . '|@[A-Za-z0-9_:-]+(?:\.[A-Za-z0-9_-]+)*'
                . '|:[A-Za-z_:][A-Za-z0-9_.:-]*)$/D',
                $name
            )
        ) {
            throw new InvalidArgumentException('Invalid Alpine directive name: ' . $name . '.');
        }

        return $name;
    }

    private static function json(mixed $value): string
    {
        return json_encode($value, self::JSON_FLAGS);
    }

    private static function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', true);
    }
}
