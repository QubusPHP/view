<?php

declare(strict_types=1);

namespace Qubus\View\Native;

use Qubus\View\Native\Exception\InvalidTemplateNameException;
use Qubus\View\Native\Exception\TemplateNotFoundException;
use Qubus\View\Renderer;

interface TemplateEngine extends Renderer
{
    /**
     * Check to see if a template exists.
     *
     * @param string $name The service name.
     * @return bool True if the container has the service, false otherwise.
     * @throws InvalidTemplateNameException If the template name is invalid.
     */
    public function exists(string $name): bool;

    /**
     * Convert a template name to a file path.
     *
     * @param string $name The template name.
     * @return string The file path of the template.
     * @throws InvalidTemplateNameException If the template name is invalid.
     * @throws TemplateNotFoundException    If the template namespace does not exist.
     */
    public function getTemplatePath(string $name): string;

    /**
     * Call a function that has been registered with the templating engine.
     *
     * @param string $name      The function name.
     * @param array  $arguments The arguments to supply to the function.
     * @return mixed The function result.
     */
    public function callFunction(string $name, array $arguments = []): mixed;
}
