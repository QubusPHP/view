<?php

declare(strict_types=1);

namespace Qubus\View\Expression;

use Qubus\View\BaseExpression;

abstract class BinaryExpression extends BaseExpression
{
    protected $left;
    protected $right;

    public function __construct($left, $right, int $line)
    {
        parent::__construct($line);
        $this->left = $left;
        $this->right = $right;
    }

    public function getLeftOperand()
    {
        return $this->left;
    }

    public function getRightOperand()
    {
        return $this->right;
    }

    abstract public function operator(): string;

    public function compile($compiler, $indent = 0): void
    {
        $op = $this->operator($compiler);
        $compiler->raw('(', $indent);
        $this->left->compile($compiler);
        $compiler->raw(' ' . $op . ' ');
        $this->right->compile($compiler);
        $compiler->raw(')');
    }
}
