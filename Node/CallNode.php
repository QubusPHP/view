<?php

declare(strict_types=1);

namespace Qubus\View\Node;

use Qubus\View\BaseNode;

final class CallNode extends BaseNode
{
    private $module;
    private $name;
    private $args;
    private $block;

    public function __construct($module, $name, $args, $block, $line)
    {
        parent::__construct($line);
        $this->module = $module;
        $this->name = $name;
        $this->args = $args;
        $this->block = $block;
    }

    public function compile($compiler, $indent = 0): void
    {
        $compiler->raw(
            'echo $this->expandMacro(\'' . $this->module . '\', \'' . $this->name
            . '\', [',
            $indent
        );

        foreach ($this->args as $key => $val) {
            $compiler->raw("'$key' => ");
            $val->compile($compiler);
            $compiler->raw(',');
        }

        $compiler->raw('], $context, $macros, $imports, function($context) {' . "\n");
        if (isset($this->block)) {
            $this->block->compile($compiler, $indent + 1);
        }
        $compiler->raw('});' . "\n", $indent);
    }
}
