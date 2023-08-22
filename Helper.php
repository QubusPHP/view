<?php

declare(strict_types=1);

namespace Qubus\View;

use Countable;
use Qubus\View\Helper\Cycler;
use Traversable;

use function abs;
use function array_keys;
use function call_user_func_array;
use function count;
use function date;
use function func_get_args;
use function htmlspecialchars;
use function htmlspecialchars_decode;
use function implode;
use function intval;
use function is_array;
use function is_numeric;
use function is_scalar;
use function is_string;
use function iterator_count;
use function iterator_to_array;
use function json_encode;
use function max;
use function nl2br;
use function number_format;
use function preg_replace;
use function Qubus\Support\Helpers\truncate_string;
use function str_repeat;
use function str_replace;
use function strip_tags;
use function strlen;
use function strtolower;
use function strtoupper;
use function strval;
use function substr;
use function time;
use function trim;
use function ucfirst;
use function ucwords;
use function urlencode;
use function wordwrap;

use const ENT_QUOTES;

final class Helper
{
    /**
     * Absolute value of a number.
     *
     * @param mixed|null $obj
     */
    public static function abs(mixed $obj = null): float|int
    {
        return abs(intval($obj));
    }

    public static function bytes(mixed $obj = null, int $decimals = 1, ?string $dec = '.', ?string $sep = ','): string
    {
        $obj = max(0, intval($obj));
        $places = strlen((string) $obj);
        if ($places <= 9 && $places >= 7) {
            $obj = number_format($obj / 1048576, $decimals, $dec, $sep);
            return "$obj MB";
        } elseif ($places >= 10) {
            $obj = number_format($obj / 1073741824, $decimals, $dec, $sep);
            return "$obj GB";
        } elseif ($places >= 4) {
            $obj = number_format($obj / 1024, $decimals, $dec, $sep);
            return "$obj KB";
        } else {
            return "$obj";
        }
    }

    /**
     * Capitalize a string
     *
     * @param mixed $var String to capitalize.
     * @return string
     */
    public static function capitalize(mixed $var): string
    {
        return ucfirst(strval($var));
    }

    public static function cycle(mixed $var = null): Cycler
    {
        $var = $var instanceof Traversable ?
        iterator_to_array($var) : (array) $var;
        return new Cycler((array) $var);
    }

    public static function date(mixed $obj = null, string $format = 'Y-m-d'): string
    {
        return date($format, $obj ?: time());
    }

    public static function dump(mixed $obj = null): never
    {
        dd($obj);
    }

    public static function esc(mixed $obj = null, bool $force = false): string
    {
        return self::escape($obj, $force);
    }

    public static function escape($obj = null, bool $force = false): string
    {
        return htmlspecialchars(strval($obj), ENT_QUOTES, 'UTF-8', $force);
    }

    public static function first(mixed $obj = null, $default = null)
    {
        if (is_string($obj)) {
            return strlen($obj) ? substr($obj, 0, 1) : $default;
        }
        $obj = $obj instanceof Traversable ?
        iterator_to_array($obj) : (array) $obj;
        $keys = array_keys($obj);
        if (count($keys)) {
            return $obj[$keys[0]];
        }
        return $default;
    }

    public static function format($obj, $args)
    {
        return call_user_func_array('sprintf', func_get_args());
    }

    public static function isIterable(mixed $obj = null): bool
    {
        return is_array($obj) || $obj instanceof Traversable;
    }

    public static function isDivisibleBy(mixed $obj = null, mixed $number = null): bool
    {
        if (! isset($number)) {
            return false;
        }
        if (! is_numeric($obj) || ! is_numeric($number)) {
            return false;
        }
        if ($number === 0) {
            return false;
        }
        return $obj % $number === 0;
    }

    public static function isEmpty(mixed $obj = null): bool|int
    {
        if (null === $obj) {
            return true;
        } elseif (is_array($obj)) {
            return empty($obj);
        } elseif (is_string($obj)) {
            return strlen($obj) === 0;
        } elseif ($obj instanceof Countable) {
            return count($obj) ? false : true;
        } elseif ($obj instanceof Traversable) {
            return iterator_count($obj);
        } else {
            return false;
        }
    }

    /**
     * Check if scalar is even number.
     *
     * @param mixed $obj
     * @return bool
     */
    public static function isEven(mixed $obj = null): bool
    {
        if (is_scalar($obj) || null === $obj) {
            $obj = is_numeric($obj) ? intval($obj) : strlen($obj);
        } elseif (is_array($obj)) {
            $obj = count($obj);
        } elseif ($obj instanceof Traversable) {
            $obj = iterator_count($obj);
        } else {
            return false;
        }
        return abs($obj % 2) === 0;
    }

    /**
     * Check if scalar is odd number.
     *
     */
    public static function isOdd(mixed $obj = null): bool
    {
        if (is_scalar($obj) || null === $obj) {
            $obj = is_numeric($obj) ? intval($obj) : strlen($obj);
        } elseif (is_array($obj)) {
            $obj = count($obj);
        } elseif ($obj instanceof Traversable) {
            $obj = iterator_count($obj);
        } else {
            return false;
        }
        return abs($obj % 2) === 1;
    }

    public static function join(mixed $obj = null, array|string $glue = ''): string
    {
        $obj = $obj instanceof Traversable ?
        iterator_to_array($obj) : (array) $obj;
        return implode($glue, $obj);
    }

    public static function jsonEncode(mixed $obj = null): bool|string
    {
        return json_encode($obj);
    }

    public static function keys(mixed $obj = null): ?array
    {
        if (is_array($obj)) {
            return array_keys($obj);
        } elseif ($obj instanceof Traversable) {
            return array_keys(iterator_to_array($obj));
        }
        return null;
    }

    public static function last(mixed $obj = null, mixed $default = null)
    {
        if (is_string($obj)) {
            return strlen($obj) ? substr($obj, -1) : $default;
        }
        $obj = $obj instanceof Traversable ?
        iterator_to_array($obj) : (array) $obj;
        $keys = array_keys($obj);
        if ($len = count($keys)) {
            return $obj[$keys[$len - 1]];
        }
        return $default;
    }

    public static function length(mixed $obj = null): ?int
    {
        if (is_string($obj)) {
            return strlen($obj);
        } elseif (is_array($obj) || $obj instanceof Countable) {
            return count($obj);
        } elseif ($obj instanceof Traversable) {
            return iterator_count($obj);
        } else {
            return 1;
        }
    }

    /**
     * Convert string to lowercase.
     *
     * @param mixed $obj
     * @return string
     */
    public static function lower(mixed $obj = null): string
    {
        return strtolower(strval($obj));
    }

    public static function nl2br($obj = null, $isXhtml = false): string
    {
        return nl2br(strval($obj), $isXhtml);
    }

    public static function numberFormat(
        mixed $obj = null,
        int $decimals = 0,
        ?string $decPoint = '.',
        ?string $thousandsSep = ','
    ): string {
        return number_format($obj, $decimals, $decPoint, $thousandsSep);
    }

    public static function repeat(mixed $obj, int $times = 2): string
    {
        return str_repeat(strval($obj), $times);
    }

    public static function replace(
        mixed $obj = null,
        array|string $search = '',
        array|string $replace = '',
        bool $regex = false
    ): array|string|null {
        if ($regex) {
            return preg_replace($search, $replace, strval($obj));
        } else {
            return str_replace($search, $replace, strval($obj));
        }
    }

    public static function stripTags(mixed $obj = null, mixed $allowableTags = ''): string
    {
        return strip_tags(strval($obj), $allowableTags);
    }

    public static function title(mixed $obj = null): string
    {
        return ucwords(strval($obj));
    }

    public static function trim(mixed $obj = null, string $charlist = " \t\n\r\0\x0B"): string
    {
        return trim(strval($obj), $charlist);
    }

    public static function truncate(
        mixed $string = null,
        int $limit = 255,
        string $continuation = '&hellip;',
        bool $isHtml = false
    ): string {
        return truncate_string($string, $limit, $continuation, $isHtml);
    }

    public static function unescape(mixed $obj = null): string
    {
        return htmlspecialchars_decode(strval($obj), ENT_QUOTES);
    }

    /**
     * Converts string to uppercase.
     *
     * @param mixed $obj
     * @return string
     */
    public static function upper(mixed $obj = null): string
    {
        return strtoupper(strval($obj));
    }

    /**
     * Url encodes a string.
     *
     * @param mixed $obj
     * @return string
     */
    public static function urlEncode(mixed $obj = null): string
    {
        return urlencode(strval($obj));
    }

    public static function wordWrap(
        mixed $obj = null,
        int $width = 75,
        string $break = "\n",
        bool $cut = false
    ): string {
        return wordwrap(strval($obj), $width, $break, $cut);
    }

    public static function imageTag(mixed $obj, array $options = []): string
    {
        $attr = self::htmlAttribute(['alt','width','height','border'], $options);
        return sprintf('<img src="%s" %s/>', $obj, $attr);
    }

    public static function cssTag(mixed $obj, array $options = []): string
    {
        $attr = self::htmlAttribute(['media'], $options);
        return sprintf('<link rel="stylesheet" href="%s" type="text/css" %s />', $obj, $attr);
    }

    public static function scriptTag(mixed $obj, array $options = []): string
    {
        return sprintf('<script src="%s" type="text/javascript"></script>', $obj);
    }

    protected static function htmlAttribute(array $attrs = [], array $data = []): string
    {
        $attrs = self::extract(array_merge(['id', 'class', 'title', 'style'], $attrs), $data);

        $result = [];
        foreach ($attrs as $name => $value) {
            $result[] = "{$name}=\"{$value}\"";
        }
        return implode(' ', $result);
    }

    protected static function extract(array $attrs = [], array $data = []): array
    {
        $result = [];
        if (empty($data)) {
            return [];
        }
        foreach ($data as $k => $e) {
            if (in_array($k, $attrs)) {
                $result[$k] = $e;
            }
        }
        return $result;
    }
}
