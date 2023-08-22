<?php

declare(strict_types=1);

namespace Qubus\View\Expression;

final class InclusionExpression extends LogicalExpression
{
    public function operator(): string
    {
        return '';
    }

    public function compile($compiler, $indent = 0): void
    {
        $compiler->raw('(in_array(', $indent);
        $this->getLeftOperand()->compile($compiler);
        $compiler->raw(', (array)');
        $this->getRightOperand()->compile($compiler);
        $compiler->raw('))');
    }
}
