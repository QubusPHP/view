<?php

declare(strict_types=1);

namespace Qubus\View\Expression;

final class DivExpression extends BinaryExpression
{
    public function operator(): string
    {
        return '/';
    }
}
