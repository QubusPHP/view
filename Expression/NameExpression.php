<?php

declare(strict_types=1);

namespace Qubus\View\Expression;

use Qubus\View\BaseExpression;

final class NameExpression extends BaseExpression
{
    private $name;

    public function __construct($name, $line)
    {
        parent::__construct($line);
        $this->name = $name;
    }

    public function raw($compiler, $indent = 0): void
    {
        $compiler->raw($this->name, $indent);
    }

    public function repr($compiler, $indent = 0): void
    {
        $compiler->repr($this->name, $indent);
    }

    public function compile($compiler, $indent = 0): void
    {
        $compiler->raw('(isset($context[\'' . $this->name . '\']) ? ', $indent);
        $compiler->raw('$context[\'' . $this->name . '\'] : null)');
    }
}
