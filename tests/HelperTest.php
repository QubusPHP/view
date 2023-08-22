<?php

declare(strict_types=1);

namespace Qubus\Tests\View;

use ArrayIterator;
use IteratorAggregate;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Qubus\View\Helper;
use stdClass;

use function date;
use function in_array;
use function json_encode;
use function time;

class HelperTest extends TestCase
{
    public function testAbs()
    {
        Assert::assertEquals(10, Helper::abs(-10));
    }

    public function testBytes()
    {
        Assert::assertEquals('100', Helper::bytes(100));
        Assert::assertEquals('1 KB', Helper::bytes(1024, 0));
        Assert::assertEquals('1.0 KB', Helper::bytes(1024, 1));
    }

    public function testCapitalize()
    {
        Assert::assertEquals('Abc', Helper::capitalize('abc'));
        Assert::assertEquals('AbC', Helper::capitalize('AbC'));
        Assert::assertEquals('A1C', Helper::capitalize('a1C'));
    }

    public function testCycle()
    {
        $elements = [1, 2, 3];
        $cycler = new Helper\Cycler($elements);
        Assert::assertTrue($cycler instanceof IteratorAggregate);
        Assert::assertEquals(1, $cycler->next());
        Assert::assertEquals(2, $cycler->next());
        Assert::assertEquals(3, $cycler->next());
        Assert::assertTrue(in_array($cycler->random(), $elements));
    }

    public function testDate()
    {
        $time = time();
        $now = date('Y-m-d', $time);
        Assert::assertEquals($now, Helper::date());
        Assert::assertEquals($now, Helper::date($time));
    }

    public function testEscape()
    {
        $var = '<p data-info="foo&bar">foobar</p>';
        Assert::assertEquals('&lt;p data-info=&quot;foo&amp;bar&quot;&gt;foobar&lt;/p&gt;', Helper::escape($var));
    }

    public function testFirst()
    {
        $string = 'Hello, World!';
        Assert::assertEquals('H', Helper::first($string));

        $array = [1, 2, 3];
        Assert::assertEquals(1, Helper::first($array));

        $arrayIterator = new ArrayIterator($array);
        Assert::assertEquals(1, Helper::first($arrayIterator));

        $object = new stdClass();
        $object->foo = 'foo';
        $object->bar = 'bar';
        Assert::assertEquals('foo', Helper::first($object));

        Assert::assertEquals(42, Helper::first([], 42));
    }

    public function testFormat()
    {
        Assert::assertEquals('Hello, World!', Helper::format('Hello, %s', 'World!'));
    }

    public function testIsIterable()
    {
        Assert::assertTrue(Helper::isIterable([1, 2, 3]));
        Assert::assertTrue(Helper::isIterable(new ArrayIterator([1, 2, 3])));

        Assert::assertFalse(Helper::isIterable("hello world"));
        Assert::assertFalse(Helper::isIterable(0));
        Assert::assertFalse(Helper::isIterable(100));
        Assert::assertFalse(Helper::isIterable(3.14));
        Assert::assertFalse(Helper::isIterable(null));
        Assert::assertFalse(Helper::isIterable(true));
        Assert::assertFalse(Helper::isIterable(false));
    }

    public function testIsDivisibleBy()
    {
        Assert::assertTrue(Helper::isDivisibleBy(10, 1));
        Assert::assertTrue(Helper::isDivisibleBy(10, 2));
        Assert::assertFalse(Helper::isDivisibleBy(10, 3));
    }

    public function testIsEmpty()
    {
        Assert::assertTrue(Helper::isEmpty(null));
        Assert::assertTrue(Helper::isEmpty([]));
        Assert::assertTrue(Helper::isEmpty(new ArrayIterator()));
        Assert::assertFalse(Helper::isEmpty(new stdClass()));
    }

    public function testIsEven()
    {
        Assert::assertTrue(Helper::isEven(10));
        Assert::assertFalse(Helper::isEven(11));
        Assert::assertTrue(Helper::isEven('FooBar'));
        Assert::assertFalse(Helper::isEven('Foo Bar'));
    }

    public function testIsOdd()
    {
        Assert::assertFalse(Helper::isOdd(10));
        Assert::assertTrue(Helper::isOdd(11));
        Assert::assertFalse(Helper::isOdd('FooBar'));
        Assert::assertTrue(Helper::isOdd('Foo Bar'));
    }

    public function testJoin()
    {
        Assert::assertEquals('foobar', Helper::join(['foo', 'bar']));
        Assert::assertEquals('foobar', Helper::join(new ArrayIterator(['foo', 'bar'])));
    }

    public function testJsonEncode()
    {
        $var = ['foo', 'bar', 1, 2, 3, ['x' => 'y']];
        Assert::assertEquals(json_encode($var), Helper::jsonEncode($var));
    }

    public function testKeys()
    {
        $hash = ['x' => 1, 'y' => 2];
        Assert::assertEquals(['x', 'y'], Helper::keys($hash));
    }

    public function testLast()
    {
        $string = 'Hello, World!';
        Assert::assertEquals('!', Helper::last($string));

        $array = [1, 2, 3];
        Assert::assertEquals(3, Helper::last($array));

        $arrayIterator = new ArrayIterator($array);
        Assert::assertEquals(3, Helper::last($arrayIterator));

        $object = new stdClass();
        $object->foo = 'foo';
        $object->bar = 'bar';
        Assert::assertEquals('bar', Helper::last($object));

        Assert::assertEquals(42, Helper::last([], 42));
    }

    public function testLength()
    {
        Assert::assertEquals(13, Helper::length('Hello, World!'));
        Assert::assertEquals(3, Helper::length([1, 2, 3]));
        Assert::assertEquals(1, Helper::length(1));
        Assert::assertEquals(1, Helper::length(new stdClass()));
    }

    public function testLower()
    {
        Assert::assertEquals('foobar', Helper::lower('FooBar'));
        Assert::assertEquals('123', Helper::lower(123));
    }

    public function testNl2br()
    {
        Assert::assertEquals("new<br>\nline", Helper::nl2br("new\nline"));
        Assert::assertEquals("new<br />\nline", Helper::nl2br("new\nline", true));
    }

    public function testNumberFormat()
    {
        Assert::assertEquals('12,059', Helper::numberFormat(12059.34));
        Assert::assertEquals('12,059.34', Helper::numberFormat(12059.34, 2));
        Assert::assertEquals('12.059,34', Helper::numberFormat(12059.34, 2, ',', '.'));
    }

    public function testRepeat()
    {
        Assert::assertEquals('xx', Helper::repeat('x'));
        Assert::assertEquals('xxx', Helper::repeat('x', 3));
        Assert::assertEquals('x', Helper::repeat('x', 1));
        Assert::assertEquals('', Helper::repeat('x', 0));
    }

    public function testReplace()
    {
        Assert::assertEquals('barbaric', Helper::replace('foobaric', 'foo', 'bar'));
        Assert::assertEquals('vaporic', Helper::replace('foobaric', '/foobar/', 'vapor', true));
    }

    public function testStripTags()
    {
        Assert::assertEquals('this is bold', Helper::stripTags('this <i>is</i> <b>bold</b>'));
        Assert::assertEquals('this <i>is</i> bold', Helper::stripTags('this <i>is</i> <b>bold</b>', '<i>'));
    }

    public function testTitle()
    {
        Assert::assertEquals('Foo-bar', Helper::title('foo-bar'));
        Assert::assertEquals('This Is The Title', Helper::title('this is the title'));
    }

    public function testTrim()
    {
        Assert::assertEquals('foobar', Helper::trim('foobar '));
        Assert::assertEquals('foobar', Helper::trim(' foobar'));
        Assert::assertEquals('foo bar', Helper::trim(' foo bar '));
    }

    public function testTruncate()
    {
        Assert::assertEquals('this is a &hellip;', Helper::truncate('this is a long word', 10));
        Assert::assertEquals('this is a lo&hellip;', Helper::truncate('this is a long word', 12));
        Assert::assertEquals('this is a ...', Helper::truncate('this is a long word', 10, '...'));
    }

    public function testUnescape()
    {
        $var = '&lt;p data-info=&quot;foo&amp;bar&quot;&gt;foobar&lt;/p&gt;';
        Assert::assertEquals('<p data-info="foo&bar">foobar</p>', Helper::unescape($var));
    }

    public function testUpper()
    {
        Assert::assertEquals('FOOBAR', Helper::upper('FooBar'));
        Assert::assertEquals('123', Helper::upper(123));
    }

    public function testUrlEncode()
    {
        Assert::assertEquals('foo+bar', Helper::urlEncode('foo bar'));
        Assert::assertEquals('%23this', Helper::urlEncode('#this'));
        Assert::assertEquals('2%3E1', Helper::urlEncode('2>1'));
    }

    public function testWordWrap()
    {
        Assert::assertEquals(
            "this is on a line\nof its own",
            Helper::wordWrap('this is on a line of its own', 17)
        );

        Assert::assertEquals(
            "this is on a line<br>of its own",
            Helper::wordWrap('this is on a line of its own', 17, '<br>')
        );
    }
}
