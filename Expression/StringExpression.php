<?php

declare(strict_types=1);

namespace Qubus\View\Expression;

use Qubus\View\BaseExpression;

final class StringExpression extends BaseExpression
{
    private $value;

    public function __construct($value, $line)
    {
        parent::__construct($line);
        $this->value = $value;
    }

    public function compile($compiler, $indent = 0): void
    {
        $compiler->repr($this->value);
    }
}
