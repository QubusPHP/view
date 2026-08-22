<?php

declare(strict_types=1);

namespace Qubus\View;

use Stringable;

/**
 * HTML that has already been safely constructed and must not be escaped again.
 */
final readonly class HtmlString implements Stringable
{
    public function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
