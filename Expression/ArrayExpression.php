<?php

declare(strict_types=1);

namespace Qubus\View\Expression;

use Qubus\View\BaseExpression;

use function is_array;

final class ArrayExpression extends BaseExpression
{
    private array $elements;

    public function __construct(array $elements, int $line)
    {
        parent::__construct($line);
        $this->elements = $elements;
    }

    public function compile($compiler, $indent = 0): void
    {
        $compiler->raw('[', $indent);
        foreach ($this->elements as $node) {
            if (is_array($node)) {
                $node[0]->compile($compiler);
                $compiler->raw(' => ');
                $node[1]->compile($compiler);
            } else {
                $node->compile($compiler);
            }
            $compiler->raw(',');
        }
        $compiler->raw(']');
    }
}
