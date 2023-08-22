<?php

declare(strict_types=1);

namespace Qubus\View\Node;

use Qubus\View\BaseNode;
use Qubus\View\Expression\ArrayExpression;

final class IncludeNode extends BaseNode
{
    private $include;
    private $params;

    public function __construct($include, $params, $line)
    {
        parent::__construct($line);
        $this->include = $include;
        $this->params = $params;
    }

    public function compile($compiler, $indent = 0): void
    {
        $compiler->addTraceInfo($this, $indent);
        $compiler->raw('$this->loadInclude(', $indent);
        $this->include->compile($compiler);
        $compiler->raw(')->display(');

        if ($this->params instanceof ArrayExpression) {
            $this->params->compile($compiler);
            $compiler->raw(' + ');
        }

        $compiler->raw('$context);' . "\n");
    }
}
