<?php

declare(strict_types=1);

namespace Qubus\View\Native;

final readonly class TemplateResult
{
    /**
     * Constructor for the template result.
     *
     * @param string $content The template content.
     * @param array  $blocks  The template blocks.
     * @param array $stacks Push scripts and stylesheets into named stacks.
     */
    public function __construct(
        private string $content,
        private array $blocks = [],
        private array $stacks = []
    ) {
    }

    /**
     * Get the content of the result.
     *
     * @return string The content of the template result.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get the blocks of the result.
     *
     * @return array The blocks of the template result.
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    public function getStacks(): array
    {
        return $this->stacks;
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
