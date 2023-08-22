<?php

declare(strict_types=1);

namespace Qubus\View\Node;

use Qubus\View\BaseNode;

use function Qubus\Support\Helpers\studly_case;

final class MacroNode extends BaseNode
{
    private $name;
    private $args;
    private $body;

    public function __construct($name, $args, $body, $line)
    {
        parent::__construct($line);
        $this->name = $name;
        $this->args = $args;
        $this->body = $body;
    }

    public function compile($compiler, $indent = 0): void
    {
        $classMethod = 'macro' . studly_case($this->name);

        $compiler->raw("\n");
        $compiler->addTraceInfo($this, $indent, false);
        $compiler->raw(
            'public function ' . $classMethod
            . '($params = [], $context = [], $macros = [],'
            . ' $imports = [], $block = null)'
            . "\n",
            $indent
        );
        $compiler->raw("{\n", $indent);

        $compiler->raw('$context = $params + [' . "\n", $indent + 1);
        $i = 0;
        foreach ($this->args as $key => $val) {
            $compiler->raw(
                "'$key' => !isset(\$params['$key']) &&"
                . " isset(\$params[$i]) ? \$params[$i] : ",
                $indent + 2
            );
            $val->compile($compiler);
            $compiler->raw(",\n");
            $i += 1;
        }
        $compiler->raw("] + \$context;\n", $indent + 1);

        $this->body->compile($compiler, $indent + 1);
        $compiler->raw("}\n", $indent);
    }
}
