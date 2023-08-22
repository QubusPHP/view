<?php

declare(strict_types=1);

namespace Qubus\Tests\View;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Qubus\View\Native\Exception\FunctionDoesNotExistException;
use Qubus\View\Native\Exception\InvalidTemplateNameException;
use Qubus\View\Native\Exception\TemplateNotFoundException;
use Qubus\View\Native\Exception\ViewException;
use Qubus\View\Native\NativeLoader;
use TypeError;

class NativeTest extends TestCase
{
    /**
     * @throws InvalidTemplateNameException|ViewException
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

    public function invalidTemplateNameProvider(): array
    {
        return [
            [':bar'],
            ['::bar'],
            ['foo:bar'],
            ['foo'],
        ];
    }

    /**
     * @dataProvider invalidTemplateNameProvider
     * @throws ViewException
     */
    public function testInvalidTemplateName($name)
    {
        $this->expectException(InvalidTemplateNameException::class);
        $engine = new NativeLoader();
        $result = $engine->render($name);
    }

    /**
     * @throws InvalidTemplateNameException
     */
    public function testUndefinedBlock()
    {
        $this->expectException(ViewException::class);
        $engine = new NativeLoader(['test' => __DIR__ . '/templates/invalid']);
        $engine->render('test::undefined-block');
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
}
