<?php

declare(strict_types=1);

namespace Qubus\View\Expression;

use Qubus\View\BaseExpression;

final class ConditionalExpression extends BaseExpression
{
    private $expr1;
    private $expr2;
    private $expr3;

    public function __construct($expr1, $expr2, $expr3, $line)
    {
        parent::__construct($line);
        $this->expr1 = $expr1;
        $this->expr2 = $expr2;
        $this->expr3 = $expr3;
    }

    public function compile($compiler, $indent = 0): void
    {
        $compiler->raw('((', $indent);
        $this->expr1->compile($compiler);
        $compiler->raw(') ? (');
        $this->expr2->compile($compiler);
        $compiler->raw(') : (');
        $this->expr3->compile($compiler);
        $compiler->raw('))');
    }
}
