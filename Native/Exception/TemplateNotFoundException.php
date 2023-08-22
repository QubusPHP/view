<?php

declare(strict_types=1);

namespace Qubus\View\Native\Exception;

use Qubus\Exception\IO\FileSystem\FileNotFoundException;

class TemplateNotFoundException extends FileNotFoundException
{
}
