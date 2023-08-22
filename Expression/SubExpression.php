<?php

declare(strict_types=1);

namespace Qubus\View\Expression;

final class SubExpression extends BinaryExpression
{
    public function operator(): string
    {
        return '-';
    }
}
