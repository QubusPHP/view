<?php

declare(strict_types=1);

namespace Qubus\View\Node;

use Qubus\View\BaseNode;

final class ParentNode extends BaseNode
{
    private $name;

    public function __construct($name, $line)
    {
        parent::__construct($line);
        $this->name = $name;
    }

    public function compile($compiler, $indent = 0): void
    {
        $compiler->addTraceInfo($this, $indent);
        $compiler->raw(
            '$this->displayParent(\'' . $this->name
            . '\', $context, $blocks, $macros, $imports);' . "\n",
            $indent
        );
    }
}
