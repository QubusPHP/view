<?php

declare(strict_types=1);

namespace Qubus\Tests\View;

use DirectoryIterator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Qubus\Exception\Data\TypeException;
use Qubus\View\Loader;
use RuntimeException;

use function file_get_contents;
use function get_class;
use function is_readable;
use function realpath;
use function strlen;
use function substr;
use function trim;

class OutputTest extends TestCase
{
    protected ?Loader $scaffold = null;

    public function setUp(): void
    {
        parent::setUp();

        $this->scaffold = new Loader([
            'source' => realpath(__DIR__ . '/actual'),
            'target' => realpath(__DIR__ . '/cache'),
            'mode' => LOADER::RECOMPILE_ALWAYS,
        ]);
    }

    public function outputProvider(): array
    {
        $paths = [];

        $actual = realpath(__DIR__ . '/actual');

        $dir = new DirectoryIterator($actual);

        foreach ($dir as $file) {
            if ($file->isFile()) {
                $outputFile = realpath(__DIR__ . '/output/' . $file->getBasename());
                if (is_readable($outputFile)) {
                    $paths[] = [
                        trim(substr($file->getPathname(), strlen($actual)), '/'),
                        $outputFile,
                    ];
                }
            }
        }

        return $paths;
    }

    /**
     * @dataProvider outputProvider
     * @throws TypeException
     */
    public function testOutput($actual, $expected)
    {
        $expected = file_get_contents($expected);
        $template = $this->scaffold->load($actual);
        $actual = $template->render();
        Assert::assertEquals($expected, $actual, get_class($template));
    }

    /**
     * @throws TypeException
     */
    public function testLoadTemplateFromAbsolutePath()
    {
        $template = $this->scaffold->load('includes/absolute');
        $actual = $template->render();
        Assert::assertStringContainsString('absolute', $actual);
    }

    /**
     * @throws TypeException
     */
    public function testLoadTemplateFromRelativePathWithDotNotation()
    {
        $template = $this->scaffold->load('includes.relative');
        $actual = $template->render();
        Assert::assertStringContainsString('relative', $actual);
    }

    /**
     * @throws TypeException
     */
    public function testLoadTemplateOutsideSource()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Template /outside.html not found.');

        $template = $this->scaffold->load('/outside');
        $template->render();
    }
}
