<?php

declare(strict_types=1);

namespace Qubus\View\Helper;

use ArrayIterator;
use IteratorAggregate;

use function ceil;
use function count;
use function mt_rand;
use function mt_srand;

final class Cycler implements IteratorAggregate
{
    private array $elements;
    private ?int $length;
    private int $idx;

    public function __construct(array $elements)
    {
        $this->elements = $elements;
        $this->length = count($this->elements);
        $this->idx = 0;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->elements);
    }

    public function next()
    {
        return $this->elements[($this->idx++) % ($this->length)];
    }

    public function random($seed = null)
    {
        if (isset($seed)) {
            mt_srand($seed);
        }

        return $this->elements[mt_rand(0, $this->length - 1)];
    }

    public function count(): int
    {
        return $this->idx;
    }

    public function cycle(): float
    {
        return ceil($this->idx / $this->length);
    }
}
