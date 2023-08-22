<?php

declare(strict_types=1);

namespace Qubus\View\Expression;

final class AddExpression extends BinaryExpression
{
    public function operator(): string
    {
        return '+';
    }
}
