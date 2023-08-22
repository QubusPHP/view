<?php

declare(strict_types=1);

namespace Qubus\Tests\View;

use DirectoryIterator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Qubus\View\Lexer;
use Qubus\View\TokenStream;

use function file_get_contents;
use function is_readable;
use function realpath;

class LexerTest extends TestCase
{
    public function tokenProvider(): array
    {
        $paths = [];

        $dir = new DirectoryIterator(realpath(__DIR__ . '/actual'));

        foreach ($dir as $file) {
            if ($file->isFile()) {
                $tokenFile = realpath(__DIR__ . '/tokens/' . $file->getBasename('.html') . '.php');
                if (is_readable($tokenFile)) {
                    $paths[] = [$file->getPathname(), $tokenFile];
                }
            }
        }

        return $paths;
    }

    /**
     * @dataProvider tokenProvider
     */
    public function testTokenizeReturnsTokenStream($actual, $expected)
    {
        $lexer = new Lexer(file_get_contents($actual));

        $tokenStream = $lexer->tokenize();

        Assert::assertTrue($tokenStream instanceof TokenStream);

        Assert::assertEquals(include $expected, $tokenStream->getTokens());
    }
}
