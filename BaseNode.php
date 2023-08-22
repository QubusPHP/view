<?php

declare(strict_types=1);

namespace Qubus\View;

abstract class BaseNode
{
    private int $line;

    public function __construct(int $line)
    {
        $this->line = $line;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function addTraceInfo($compiler, $indent)
    {
        return $compiler->addTraceInfo($this, $indent);
    }

    abstract public function compile($compiler, $indent = 0);
}
