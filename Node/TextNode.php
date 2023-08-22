<?php

declare(strict_types=1);

namespace Qubus\View\Node;

use Qubus\View\BaseNode;

use function strlen;

final class TextNode extends BaseNode
{
    private $data;

    public function __construct($data, $line)
    {
        parent::__construct($line);
        $this->data = $data;
    }

    public function compile($compiler, $indent = 0): void
    {
        if (! strlen($this->data)) {
            return;
        }
        $compiler->addTraceInfo($this, $indent);
        $compiler->raw('echo ', $indent);
        $compiler->repr($this->data);
        $compiler->raw(";\n");
    }
}
