<?php

declare(strict_types=1);

namespace Qubus\View\Node;

use Qubus\View\BaseNode;

final class RawNode extends BaseNode
{
    private $expr;

    public function __construct($expr, $line)
    {
        parent::__construct($line);
        $this->expr = $expr;
    }

    public function compile($compiler, $indent = 0): void
    {
        $compiler->addTraceInfo($this, $indent);
        $compiler->raw('echo ', $indent);
        $this->expr->compile($compiler);
        $compiler->raw(";\n");
    }
}
