<?php

declare(strict_types=1);

namespace Qubus\View\Node;

use Qubus\View\BaseNode;

use function Qubus\Support\Helpers\studly_case;

final class BlockNode extends BaseNode
{
    private $name;
    private $body;

    public function __construct($name, $body, $line)
    {
        parent::__construct($line);
        $this->name = $name;
        $this->body = $body;
    }

    public function compile($compiler, $indent = 0): void
    {
        $classMethod = 'block' . studly_case($this->name);

        $compiler->raw("\n");
        $compiler->addTraceInfo($this, $indent, false);
        $compiler->raw(
            'public function ' . $classMethod
            . '($context, $blocks = [], $macros = [],'
            . ' $imports = [])' . "\n",
            $indent
        );
        $compiler->raw("{\n", $indent);
        $this->body->compile($compiler, $indent + 1);
        $compiler->raw("}\n", $indent);
    }
}
