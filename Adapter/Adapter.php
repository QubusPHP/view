<?php

declare(strict_types=1);

namespace Qubus\View\Adapter;

interface Adapter
{
    public function isReadable(string $path): bool;

    public function lastModified(string $path): int;

    public function getContents(string $path): string;

    public function putContents(string $path, string $contents): int|bool;

    public function getStreamUrl(string $path): string;
}
