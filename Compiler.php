<?php

declare(strict_types=1);

namespace Qubus\View;

use function str_repeat;
use function str_replace;
use function substr_count;
use function var_export;

final class Compiler
{
    private string $result;
    private Module $module;
    private int $line;
    private array $trace;

    public function __construct(Module $module)
    {
        $this->result = '';
        $this->module = $module;
        $this->line   = 1;
        $this->trace  = [];
    }

    private function write($string): Compiler
    {
        $this->result .= $string;
        return $this;
    }

    public function raw($raw, $indent = 0): Compiler
    {
        $this->line += substr_count($raw, "\n");
        $this->write(str_repeat(' ', 4 * $indent) . $raw);
        return $this;
    }

    public function repr(mixed $repr, int $indent = 0): void
    {
        $this->raw(var_export($repr, true), $indent);
    }

    public function compile(): string
    {
        $this->module->compile($this);
        return $this->result;
    }

    public function pushContext($name, $indent = 0): Compiler
    {
        $this->raw('$this->pushContext($context, ', $indent);
        $this->repr($name);
        $this->raw(");\n");
        return $this;
    }

    public function popContext($name, $indent = 0): Compiler
    {
        $this->raw('$this->popContext($context, ', $indent);
        $this->repr($name);
        $this->raw(");\n");
        return $this;
    }

    public function addTraceInfo(BaseNode $node, int $indent = 0, bool $line = true): void
    {
        $this->raw(
            '/* line ' . $node->getLine() . " -> " . ($this->line + 1)
            . " */\n",
            $indent
        );
        if ($line) {
            $this->trace[$this->line] = $node->getLine();
        }
    }

    public function getTraceInfo(bool $export = false): array|string|null
    {
        if ($export) {
            return str_replace(
                ["\n", ' '],
                '',
                var_export($this->trace, true)
            );
        }
        return $this->trace;
    }
}
