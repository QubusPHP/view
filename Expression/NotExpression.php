<?php

declare(strict_types=1);

namespace Qubus\View\Expression;

final class NotExpression extends UnaryExpression
{
    public function operator(): string
    {
        return '!';
    }
}
