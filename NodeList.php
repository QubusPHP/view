<?php

declare(strict_types=1);

namespace Qubus\View;

final class NodeList extends BaseNode
{
    private array $nodes;

    public function __construct(array $nodes, $line)
    {
        parent::__construct($line);
        $this->nodes = $nodes;
    }

    public function compile($compiler, $indent = 0): void
    {
        foreach ($this->nodes as $node) {
            $node->compile($compiler, $indent);
        }
    }
}
