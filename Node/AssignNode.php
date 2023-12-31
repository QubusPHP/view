<?php

declare(strict_types=1);

namespace Qubus\View\Node;

use Qubus\View\BaseNode;
use Qubus\View\NodeList;

use function is_string;

final class AssignNode extends BaseNode
{
    private $name;
    private array $attrs;
    private $value;

    public function __construct($name, array $attrs, $value, $line)
    {
        parent::__construct($line);
        $this->name = $name;
        $this->attrs = $attrs;
        $this->value = $value;
    }

    public function compile($compiler, $indent = 0): void
    {
        $name = "\$context['$this->name']";
        if ($this->value instanceof NodeList) {
            $compiler->raw("ob_start();\n", $indent);
            $this->value->compile($compiler);
            $compiler->raw(
                "if (!isset($name)) $name = [];\n" . "\n",
                $indent
            );
            $compiler->addTraceInfo($this, $indent);
            $compiler->raw("\$this->setAttr($name, [", $indent);
            foreach ($this->attrs as $attr) {
                is_string($attr) ?
                $compiler->repr($attr) : $attr->compile($compiler);
                $compiler->raw(', ');
            }
            $compiler->raw('], ob_get_clean());' . "\n");
        } else {
            $compiler->raw(
                "if (!isset($name)) $name = [];\n" . "\n",
                $indent
            );
            $compiler->addTraceInfo($this, $indent);
            $compiler->raw("\$this->setAttr($name, [", $indent);
            foreach ($this->attrs as $attr) {
                is_string($attr) ?
                $compiler->repr($attr) : $attr->compile($compiler);
                $compiler->raw(', ');
            }
            $compiler->raw('], ');
            $this->value->compile($compiler);
            $compiler->raw(");\n");
        }
    }
}
