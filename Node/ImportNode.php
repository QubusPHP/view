<?php

declare(strict_types=1);

namespace Qubus\View\Node;

use Qubus\View\BaseNode;

final class ImportNode extends BaseNode
{
    private $module;
    private $import;

    public function __construct($module, $import, $line)
    {
        parent::__construct($line);
        $this->module = $module;
        $this->import = $import;
    }

    public function compile($compiler, $indent = 0): void
    {
        $compiler->addTraceInfo($this, $indent);
        $compiler->raw("'$this->module' => ", $indent);
        $compiler->raw('$this->loadImport(');
        $this->import->compile($compiler);
        $compiler->raw("),\n");
    }
}
