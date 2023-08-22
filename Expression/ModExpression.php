<?php

declare(strict_types=1);

namespace Qubus\View\Expression;

final class ModExpression extends BinaryExpression
{
    public function operator(): string
    {
        return '';
    }

    public function compile($compiler, $indent = 0): void
    {
        $compiler->raw('fmod(', $indent);
        $this->left->compile($compiler);
        $compiler->raw(', ');
        $this->right->compile($compiler);
        $compiler->raw(')');
    }
}
