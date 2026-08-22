<?php

declare(strict_types=1);

namespace Qubus\View\Adapter;

use RuntimeException;

use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function is_readable;
use function realpath;
use function rename;
use function sprintf;
use function tempnam;
use function unlink;
use function dirname;
use function chmod;
use function umask;

use const LOCK_EX;

final class FileAdapter implements Adapter
{
    private string|array $source;

    public function __construct(string|array $source)
    {
        if (!is_array($source)) {
            $path = realpath($source);
            if (!$path) {
                throw new RuntimeException(sprintf('source directory %s not found', $source));
            }
            $paths = [$path];
        } else {
            $paths = [];
            foreach ($source as $path) {
                if ($absPath = realpath($path)) {
                    $paths[] = $absPath;
                } else {
                    throw new RuntimeException(sprintf('source directory %s not found', $path));
                }
            }
        }
        $this->source = $paths;
    }

    public function isReadable(string $path): bool
    {
        return is_readable($this->getStreamUrl($path));
    }

    public function lastModified(string $path): int
    {
        return filemtime($this->getStreamUrl($path));
    }

    public function getContents(string $path): string
    {
        return file_get_contents($this->getStreamUrl($path));
    }

    public function putContents(string $path, string $contents): int|bool
    {
        $destination = $this->getStreamUrl($path);
        $temporary = tempnam(dirname($destination), '.scaffold-');
        if ($temporary === false) {
            return false;
        }

        $written = file_put_contents($temporary, $contents, LOCK_EX);
        if ($written !== false) {
            chmod($temporary, 0666 & ~umask());
        }
        if ($written === false || !rename($temporary, $destination)) {
            if (is_file($temporary)) {
                unlink($temporary);
            }
            return false;
        }

        return $written;
    }

    public function getStreamUrl(string $path): string
    {
        foreach ($this->source as $source) {
            if (is_file($source . '/' . $path)) {
                return $source . '/' . $path;
            }
        }
        return $path;
    }
}
