<?php

declare(strict_types=1);

namespace Qubus\View\Node;

use Qubus\View\BaseNode;

final class IfNode extends BaseNode
{
    private $tests;
    private $else;

    public function __construct($tests, $else, $line)
    {
        parent::__construct($line);
        $this->tests = $tests;
        $this->else = $else;
    }

    public function compile($compiler, $indent = 0): void
    {
        $compiler->addTraceInfo($this, $indent);
        $idx = 0;
        foreach ($this->tests as $test) {
            $compiler->raw(($idx++ ? "} else" : '') . 'if (', $indent);
            $test[0]->compile($compiler);
            $compiler->raw(") {\n");
            $test[1]->compile($compiler, $indent + 1);
        }
        if (null !== $this->else) {
            $compiler->raw("} else {\n", $indent);
            $this->else->compile($compiler, $indent + 1);
        }
        $compiler->raw("}\n", $indent);
    }
}
