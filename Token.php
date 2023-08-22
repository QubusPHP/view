<?php

declare(strict_types=1);

namespace Qubus\View;

use function in_array;
use function is_array;
use function is_int;

final class Token
{
    private int $type;
    private ?string $value;
    private int $line;
    private int $char;

    public const EOF          = -1;
    public const TEXT         = 0;
    public const BLOCK_BEGIN  = 1;
    public const OUTPUT_BEGIN = 2;
    public const RAW_BEGIN    = 3;
    public const BLOCK_END    = 4;
    public const OUTPUT_END   = 5;
    public const RAW_END      = 6;
    public const NAME         = 7;
    public const NUMBER       = 8;
    public const STRING       = 9;
    public const OPERATOR     = 10;
    public const CONSTANT     = 11;

    public function __construct(int $type, ?string $value, int $line, int $char)
    {
        $this->type  = $type;
        $this->value = $value;
        $this->line  = $line;
        $this->char  = $char;
    }

    public static function getTypeError($type): string
    {
        switch ($type) {
            case self::EOF:
                $name = 'end of file';
                break;
            case self::TEXT:
                $name = 'text type';
                break;
            case self::BLOCK_BEGIN:
                $name = 'block begin (either "' . Lexer::BLOCK_BEGIN . '" or "'
                . Lexer::BLOCK_BEGIN_TRIM . '")';
                break;
            case self::OUTPUT_BEGIN:
                $name = 'output begin (either "' . Lexer::OUTPUT_BEGIN . '" or "'
                . Lexer::OUTPUT_BEGIN_TRIM . '")';
                break;
            case self::RAW_BEGIN:
                $name = 'raw begin (either "' . Lexer::RAW_BEGIN . '" or "'
                . Lexer::RAW_BEGIN_TRIM . '")';
                break;
            case self::BLOCK_END:
                $name = 'block end (either "' . Lexer::BLOCK_END . '" or "'
                . Lexer::BLOCK_END_TRIM . '")';
                break;
            case self::OUTPUT_END:
                $name = 'output end (either "' . Lexer::OUTPUT_END . '" or "'
                . Lexer::OUTPUT_END_TRIM . '")';
                break;
            case self::RAW_END:
                $name = 'raw end (either "' . Lexer::RAW_END . '" or "'
                . Lexer::RAW_END_TRIM . '")';
                break;
            case self::NAME:
                $name = 'name type';
                break;
            case self::NUMBER:
                $name = 'number type';
                break;
            case self::STRING:
                $name = 'string type';
                break;
            case self::OPERATOR:
                $name = 'operator type';
                break;
            case self::CONSTANT:
                $name = 'constant type (true, false, or null)';
                break;
        }
        return $name;
    }

    public function test($type, $values = null): bool
    {
        if (null === $values && ! is_int($type)) {
            $values = $type;
            $type = self::NAME;
        }

        return ($this->type === $type) && (
            null === $values ||
            (is_array($values) && in_array($this->value, $values)) ||
            $this->value === $values
        );
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getChar(): int
    {
        return $this->char;
    }

    public function __toString(): string
    {
        return $this->getValue();
    }
}
