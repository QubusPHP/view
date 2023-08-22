<?php

declare(strict_types=1);

namespace Qubus\View\Expression;

final class PosExpression extends UnaryExpression
{
    public function operator(): string
    {
        return '+';
    }
}
