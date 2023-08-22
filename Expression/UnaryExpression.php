<?php

declare(strict_types=1);

namespace Qubus\View\Expression;

use Qubus\View\BaseExpression;

abstract class UnaryExpression extends BaseExpression
{
    private $node;

    public function __construct($node, $line)
    {
        parent::__construct($line);
        $this->node = $node;
    }

    abstract public function operator(): string;

    public function compile($compiler, $indent = 0): void
    {
        $compiler->raw('(', $indent);
        $compiler->raw($this->operator());
        $compiler->raw('(');
        $this->node->compile($compiler);
        $compiler->raw('))');
    }
}
