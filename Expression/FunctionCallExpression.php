<?php

declare(strict_types=1);

namespace Qubus\View\Expression;

use Qubus\View\BaseExpression;

final class FunctionCallExpression extends BaseExpression
{
    private $node;
    private array $args;

    public function __construct($node, array $args, int $line)
    {
        parent::__construct($line);
        $this->node = $node;
        $this->args = $args;
    }

    public function compile($compiler, $indent = 0): void
    {
        $compiler->raw('$this->helper(');
        $this->node->repr($compiler);
        foreach ($this->args as $arg) {
            $compiler->raw(', ');
            $arg->compile($compiler);
        }
        $compiler->raw(')');
    }
}
