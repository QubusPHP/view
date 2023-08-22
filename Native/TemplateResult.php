<?php

declare(strict_types=1);

namespace Qubus\View\Native;

final class TemplateResult
{
    /**
     * Constructor for the template result.
     *
     * @param string $content The template content.
     * @param array  $blocks  The template blocks.
     */
    public function __construct(private string $content, private array $blocks = [])
    {
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
}
