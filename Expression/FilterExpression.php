<?php

declare(strict_types=1);

namespace Qubus\View\Expression;

use Qubus\View\BaseExpression;
use Qubus\View\BaseNode;

use function array_reverse;
use function array_unshift;
use function count;

final class FilterExpression extends BaseExpression
{
    private BaseNode $node;
    private array $filters;

    public function __construct(BaseNode $node, array $filters, int $line)
    {
        parent::__construct($line);
        $this->node = $node;
        $this->filters = $filters;
    }

    public function appendFilter($filter): FilterExpression
    {
        $this->filters[] = $filter;
        return $this;
    }

    public function prependFilter($filter): FilterExpression
    {
        array_unshift($this->filters, $filter);
        return $this;
    }

    public function compile($compiler, $indent = 0): void
    {
        $stack = [];

        for ($i = count($this->filters) - 1; $i >= 0; --$i) {
            [$name, $arguments] = $this->filters[$i];
            $compiler->raw('$this->helper(\'' . $name . '\', ');
            $stack[] = $arguments;
        }

        $this->node->compile($compiler);

        foreach (array_reverse($stack) as $i => $arguments) {
            foreach ($arguments as $arg) {
                $compiler->raw(', ');
                $arg->compile($compiler);
            }
            $compiler->raw(')');
        }
    }
}
