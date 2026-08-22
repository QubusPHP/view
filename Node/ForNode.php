<?php

declare(strict_types=1);

namespace Qubus\View\Node;

use Qubus\View\BaseNode;

final class ForNode extends BaseNode
{
    private $seq;
    private $key;
    private $value;
    private $body;
    private $else;

    public function __construct($seq, $key, $value, $body, $else, $line)
    {
        parent::__construct($line);
        $this->seq = $seq;
        $this->key = $key;
        $this->value = $value;
        $this->body = $body;
        $this->else = $else;
    }

    public function compile($compiler, $indent = 0): void
    {
        $compiler->addTraceInfo($this, $indent);

        $iterator = $compiler->temporary('iterator');

        $compiler->pushContext('loop', $indent);
        if ($this->key) {
            $compiler->pushContext($this->key, $indent);
        }
        $compiler->pushContext($this->value, $indent);

        $compiler->raw($iterator . ' = $this->iterate($context, ', $indent);
        $this->seq->compile($compiler);
        $compiler->raw(");\n");

        $else = false;
        if (null !== $this->else) {
            $compiler->raw('if (' . $iterator . '->length() > 0) {' . "\n", $indent);
            $else = true;
        }

        $compiler->raw(
            'foreach (($context[\'loop\'] = ' . $iterator,
            $else ? $indent + 1 : $indent
        );

        if ($this->key) {
            $compiler->raw(
                ') as $context[\'' . $this->key
                . '\'] => $context[\'' . $this->value . '\']) {' . "\n"
            );
        } else {
            $compiler->raw(
                ') as $context[\'' . $this->value . '\']) {' . "\n"
            );
        }

        $this->body->compile($compiler, $else ? $indent + 2 : $indent + 1);

        $compiler->raw("}\n", $else ? $indent + 1 : $indent);

        if ($else) {
            $compiler->raw("} else {\n", $indent);
            $this->else->compile($compiler, $indent + 1);
            $compiler->raw("}\n", $indent);
        }

        $compiler->popContext('loop', $indent);
        if ($this->key) {
            $compiler->popContext($this->key, $indent);
        }
        $compiler->popContext($this->value, $indent);
    }
}
