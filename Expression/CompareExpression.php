<?php

declare(strict_types=1);

namespace Qubus\View\Expression;

use Qubus\View\BaseExpression;

use function str_repeat;

final class CompareExpression extends BaseExpression
{
    private $expr;
    private array $ops;

    public function __construct($expr, array $ops, int $line)
    {
        parent::__construct($line);
        $this->expr = $expr;
        $this->ops = $ops;
    }

    public function compile($compiler, $indent = 0): void
    {
        $this->expr->compile($compiler);
        $i = 0;
        foreach ($this->ops as $op) {
            if ($i) {
                $compiler->raw(' && ($tmp' . $i);
            }
            [$op, $node] = $op;
            $compiler->raw(' ' . ($op === '=' ? '==' : $op) . ' ');
            $compiler->raw('($tmp' . ++$i . ' = ');
            $node->compile($compiler);
            $compiler->raw(')');
        }
        if ($i > 1) {
            $compiler->raw(str_repeat(')', $i - 1));
        }
    }
}
