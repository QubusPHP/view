<?php

declare(strict_types=1);

namespace Qubus\Tests\View;

use Error;
use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Qubus\View\Loader;
use RuntimeException;
use Qubus\View\SyntaxErrorException;

use function ob_get_level;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function rmdir;
use function symlink;
use function sys_get_temp_dir;
use function unlink;
use function bin2hex;

final class CompilerEnhancementTest extends TestCase
{
    public function testMultiplicativeOperatorsHaveStandardLeftAssociativity(): void
    {
        Assert::assertSame('0', $this->compiler()->fetchString('{{ 20 * 6 % 4 }}'));
        Assert::assertSame('10', $this->compiler()->fetchString('{{ 20 / 4 * 2 }}'));
    }

    public function testGeneratorLoopsAreNotConsumedByLengthDetection(): void
    {
        $values = (static function (): iterable {
            yield 'a';
            yield 'b';
        })();

        $output = $this->compiler()->fetchString(
            '{% for value in values %}{{ loop.index }}:{{ value }}:'
            . '{{ loop.first }}:{{ loop.last }};{% else %}empty{% endfor %}',
            ['values' => $values]
        );

        Assert::assertSame('0:a:1:;1:b::1;', $output);
    }

    public function testDescendingRangesAndLoopMetadataAreCorrect(): void
    {
        $output = $this->compiler()->fetchString(
            '{% for value in range(3, 1) %}{{ value }}:{{ loop.count }}/{{ loop.length }};{% endfor %}'
        );

        Assert::assertSame('3:1/3;2:2/3;1:3/3;', $output);
    }

    public function testRenderCleansOutputBuffersAndWrapsHelperErrors(): void
    {
        $loader = new Loader([
            'source' => __DIR__ . '/actual',
            'target' => __DIR__ . '/cache',
            'mode' => Loader::RECOMPILE_ALWAYS,
            'exception_handler' => false,
            'helpers' => ['explodeNow' => static fn (): never => throw new Error('boom')],
        ]);
        $template = $loader->loadFromString('before{{ explodeNow() }}');
        $level = ob_get_level();

        try {
            $template->render();
            self::fail('Expected helper failure.');
        } catch (RuntimeException $exception) {
            Assert::assertSame($level, ob_get_level());
            Assert::assertInstanceOf(Error::class, $exception->getPrevious());
            Assert::assertStringContainsString('boom', $exception->getMessage());
        }
    }

    public function testRecursiveIncludesAreRejectedAndTemplateCanRenderAgain(): void
    {
        $loader = $this->compiler();

        try {
            $loader->fetch('includes.recursive');
            self::fail('Expected a circular template exception.');
        } catch (RuntimeException $exception) {
            Assert::assertStringContainsString('circular template reference', $exception->getMessage());
        }

        Assert::assertSame('ok', $loader->fetchString('ok'));
    }

    public function testExistsAndExtensionValidation(): void
    {
        $loader = $this->compiler();
        Assert::assertTrue($loader->exists('output'));
        Assert::assertFalse($loader->exists('../outside'));

        $this->expectException(InvalidArgumentException::class);
        new Loader([
            'source' => __DIR__ . '/actual',
            'target' => __DIR__ . '/cache',
            'extension' => '../php',
        ]);
    }

    public function testSymlinkCannotEscapeACompilerSourceDirectory(): void
    {
        $root = sys_get_temp_dir() . '/scaffold-view-' . bin2hex(random_bytes(6));
        $source = $root . '/source';
        $target = $root . '/cache';
        $outside = $root . '/outside';
        mkdir($source, 0777, true);
        mkdir($target, 0777, true);
        mkdir($outside, 0777, true);
        file_put_contents($outside . '/secret.html', 'secret');

        if (!symlink($outside . '/secret.html', $source . '/leak.html')) {
            self::markTestSkipped('Symbolic links are not available.');
        }

        try {
            $loader = new Loader([
                'source' => $source,
                'target' => $target,
                'exception_handler' => false,
            ]);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('outside the source directory');
            $loader->fetch('leak');
        } finally {
            unlink($source . '/leak.html');
            unlink($outside . '/secret.html');
            rmdir($source);
            rmdir($target);
            rmdir($outside);
            rmdir($root);
        }
    }

    public function testUnclosedCommentsAreSyntaxErrors(): void
    {
        $this->expectException(SyntaxErrorException::class);
        $this->expectExceptionMessage('unclosed comment');
        $this->compiler()->fetchString('before {# never closed');
    }

    private function compiler(): Loader
    {
        return new Loader([
            'source' => __DIR__ . '/actual',
            'target' => __DIR__ . '/cache',
            'mode' => Loader::RECOMPILE_ALWAYS,
            'exception_handler' => false,
        ]);
    }
}
