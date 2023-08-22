<?php

declare(strict_types=1);

namespace Qubus\View\Expression;

final class ConcatExpression extends BinaryExpression
{
    public function operator(): string
    {
        return '.';
    }
}
