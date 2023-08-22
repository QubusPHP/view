<?php

declare(strict_types=1);

namespace Qubus\View\Expression;

use Qubus\View\BaseExpression;

use function is_array;

final class AttributeExpression extends BaseExpression
{
    private $node;
    private $attr;
    private $args;

    public function __construct($node, $attr, $args, int $line)
    {
        parent::__construct($line);
        $this->node = $node;
        $this->attr = $attr;
        $this->args = $args;
    }

    public function compile($compiler, $indent = 0): void
    {
        $compiler->raw('$this->getAttr(', $indent);
        $this->node->compile($compiler);
        $compiler->raw(', ');
        $this->attr->compile($compiler);
        if (is_array($this->args)) {
            $compiler->raw(', [');
            foreach ($this->args as $arg) {
                $arg->compile($compiler);
                $compiler->raw(', ');
            }
            $compiler->raw(']');
        } else {
            $compiler->raw(', false');
        }
        $compiler->raw(')');
    }
}
