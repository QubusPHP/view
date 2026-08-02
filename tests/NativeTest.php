<?php

declare(strict_types=1);

namespace Qubus\Tests\View;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qubus\View\Native\Exception\FunctionDoesNotExistException;
use Qubus\View\Native\Exception\InvalidTemplateNameException;
use Qubus\View\Native\Exception\TemplateNotFoundException;
use Qubus\View\Native\Exception\ViewException;
use Qubus\View\Native\NativeLoader;
use Qubus\View\Native\TemplateResult;
use InvalidArgumentException;
use Throwable;

class NativeTest extends TestCase
{
    /**
     * @throws InvalidTemplateNameException
     * @throws ViewException
     * @throws Throwable
     */
    public function testEngine()
    {
        $engine = new NativeLoader(
            ['test' => __DIR__ . '/templates/valid'],
            ['caps' => 'strtoupper']
        );

        // Test exists()
        Assert::assertTrue($engine->exists('test::first'));
        Assert::assertFalse($engine->exists('foo::bar'));

        // Test render()
        $result = $engine->render('test::first', ['title' => 'Hello World', 'shout' => 'shout']);

        $expectedResult = <<<EOT
<html>
    <head><title>Middle Hello World</title></head>
    <body>
        SHOUT
        Partial Block
        Middle First
    </body>
</html>
EOT;

        Assert::assertEquals(
            str_replace([' ', PHP_EOL], '', $expectedResult),
            str_replace([' ', PHP_EOL], '', $result)
        );
    }

    /**
     * @throws ViewException
     * @throws InvalidTemplateNameException
     */
    public function testUnregisteredNamespace()
    {
        $this->expectException(TemplateNotFoundException::class);
        $this->expectExceptionMessage('namespace has not been registered');
        $engine = new NativeLoader();
        $engine->render('foo::bar');
    }

    /**
     * @throws ViewException
     * @throws InvalidTemplateNameException
     */
    public function testTemplateDoesNotExist()
    {
        $this->expectException(TemplateNotFoundException::class);
        $this->expectExceptionMessage('There is no template at the path');
        $engine = new NativeLoader(['foo' => __DIR__ . '/templates/invalid']);
        $engine->render('foo::bar');
    }

    public static function invalidTemplateNameProvider(): array
    {
        return [
            [':bar'],
            ['::bar'],
            ['foo:bar'],
            ['foo'],
        ];
    }

    /**
     * @throws ViewException
     */
    #[DataProvider('invalidTemplateNameProvider')]
    public function testInvalidTemplateName($name)
    {
        $this->expectException(InvalidTemplateNameException::class);
        $engine = new NativeLoader();
        $result = $engine->render($name);
    }

    /**
     * @throws InvalidTemplateNameException
     */
    public function testDoubleParent()
    {
        $this->expectException(ViewException::class);
        $engine = new NativeLoader(['test' => __DIR__ . '/templates/invalid']);
        $engine->render('test::double-parent');
    }

    /**
     * @throws ViewException
     * @throws InvalidTemplateNameException
     */
    public function testFunctionDoesNotExist()
    {
        $this->expectException(FunctionDoesNotExistException::class);
        $engine = new NativeLoader(['test' => __DIR__ . '/templates/invalid']);
        $engine->render('test::function-does-not-exist');
    }

    public function testUndefinedBlockMaintainsLegacyException(): void
    {
        $this->expectException(ViewException::class);
        $engine = new NativeLoader(['test' => __DIR__ . '/templates/invalid']);
        $engine->render('test::undefined-block');
    }

    public function testRejectsPathTraversalOutsideNamespace(): void
    {
        $this->expectException(InvalidTemplateNameException::class);
        $engine = new NativeLoader(['test' => __DIR__ . '/templates/valid']);
        $engine->render('test::../invalid/function-does-not-exist');
    }

    public function testGlobalsCustomExtensionAndDataPrecedence(): void
    {
        $engine = new NativeLoader(
            ['test' => __DIR__ . '/templates/valid'],
            extension: 'tpl',
            globals: ['site' => 'Global', 'title' => 'Default']
        );

        Assert::assertSame('Global:Page', $engine->fetch('test::custom', ['title' => 'Page']));
    }

    public function testLegacyBlockCallbackReceivesParameters(): void
    {
        $engine = new NativeLoader(['test' => __DIR__ . '/templates/valid']);

        Assert::assertSame('Hello Ada', trim($engine->render('test::legacy-callback', ['name' => 'Ada'])));
    }

    public function testStacksComponentsAndFallbackBlocksPropagateToLayout(): void
    {
        $engine = new NativeLoader(['test' => __DIR__ . '/templates/valid']);
        $result = $engine->makeContext('test::feature-page')();

        Assert::assertInstanceOf(TemplateResult::class, $result);
        Assert::assertSame('Slot,Fallback,Before,One,After', preg_replace('/\s+/', '', $result->getContent()));
        Assert::assertSame('Before,One,After', preg_replace('/\s+/', '', $result->getStacks()['scripts']));
        Assert::assertSame($result->getContent(), (string) $result);
    }

    public function testCircularInheritanceIsRejected(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('Circular template reference');
        $engine = new NativeLoader(['test' => __DIR__ . '/templates/invalid']);
        $engine->render('test::cycle-a');
    }

    public function testInvalidExtensionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new NativeLoader(extension: '../phtml');
    }
}
