<?php

declare(strict_types=1);

namespace Qubus\View\Node;

use Qubus\View\BaseNode;

final class YieldNode extends BaseNode
{
    private array $args;

    public function __construct(array $args, $line)
    {
        parent::__construct($line);
        $this->args = $args;
    }

    public function compile($compiler, $indent = 0): void
    {
        $compiler->addTraceInfo($this, $indent);
        $compiler->raw('call_user_func($block, [', $indent);

        foreach ($this->args as $key => $val) {
            $compiler->raw("'$key' => ");
            $val->compile($compiler);
            $compiler->raw(',');
        }

        $compiler->raw('] + $context);' . "\n");
    }
}
