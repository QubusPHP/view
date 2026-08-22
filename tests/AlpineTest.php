<?php

declare(strict_types=1);

namespace Qubus\Tests\View;

use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Qubus\View\Alpine;
use Qubus\View\Loader;
use Qubus\View\Native\NativeLoader;

final class AlpineTest extends TestCase
{
    public function testAttributesNormalizeDirectivesAndEscapeExpressions(): void
    {
        $attributes = (string) Alpine::attributes([
            'data' => ['open' => false, 'label' => '" onmouseover="alert(1)'],
            'on:click.prevent' => 'open = ! open',
            'bind:aria-expanded' => 'open',
            'cloak' => null,
        ]);

        Assert::assertStringContainsString('x-data="{&quot;open&quot;:false', $attributes);
        Assert::assertStringContainsString('x-on:click.prevent="open = ! open"', $attributes);
        Assert::assertStringContainsString('x-bind:aria-expanded="open"', $attributes);
        Assert::assertStringContainsString('x-cloak', $attributes);
        Assert::assertStringNotContainsString(' onmouseover="alert(1)', $attributes);
    }

    public function testEmptyDataUsesAnObjectScope(): void
    {
        Assert::assertSame('x-data="{}"', (string) Alpine::data());
    }

    public function testInvalidDirectiveNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Alpine::attributes(['x-data onmouseover' => 'attack()']);
    }

    public function testComponentStoreScriptAndCloakBuildersAreCspAware(): void
    {
        Assert::assertSame(
            'x-data="dropdown(true, &quot;Menu&quot;)"',
            (string) Alpine::component('dropdown', [true, 'Menu'])
        );

        $store = (string) Alpine::store('session', ['value' => '</script><script>alert(1)</script>'], 'n"once');
        Assert::assertStringContainsString('nonce="n&quot;once"', $store);
        Assert::assertStringNotContainsString('</script><script>', $store);
        Assert::assertStringContainsString('Alpine.store(', $store);

        Assert::assertSame(
            '<style nonce="nonce">[x-cloak]{display:none!important}</style>',
            (string) Alpine::cloakStyle('nonce')
        );
    }

    public function testScriptBuilderRejectsExecutableUrlSchemes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Alpine::script('javascript:alert(1)');
    }

    public function testCompilerRendersAlpineHelpersAsSafeHtml(): void
    {
        $loader = $this->compiler();
        $output = $loader->fetchString(
            '<div {{ alpineData(state) }} {{ alpine(["on:click" => action, "cloak" => null]) }}></div>',
            [
                'state' => ['open' => false, 'label' => '<Menu>'],
                'action' => 'open = ! open',
            ]
        );

        Assert::assertSame(
            '<div x-data="{&quot;open&quot;:false,&quot;label&quot;:&quot;\\u003CMenu\\u003E&quot;}" '
            . 'x-on:click="open = ! open" x-cloak></div>',
            $output
        );
    }

    public function testNativeEngineExposesTheSameAlpineHelpers(): void
    {
        $engine = new NativeLoader(['test' => __DIR__ . '/templates/valid']);

        $output = $engine->fetch('test::alpine', [
            'state' => ['open' => false],
            'action' => 'open = ! open',
        ]);

        Assert::assertSame(
            '<div x-data="{&quot;open&quot;:false}" x-on:click="open = ! open" x-cloak></div>',
            trim($output)
        );
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
