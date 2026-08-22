<?php

declare(strict_types=1);

namespace Qubus\View\Helper;

use Countable;
use InvalidArgumentException;
use Iterator;
use ReturnTypeWillChange;

use function abs;
use function mt_rand;
use function mt_srand;

final class RangeIterator implements Countable, Iterator
{
    private float|int $lower;
    private float|int $upper;
    private float|int $step;
    private float|int $current;

    public function __construct(float|int $lower, float|int $upper, float|int $step = 1)
    {
        if ($step == 0) {
            throw new InvalidArgumentException('Range step must not be zero.');
        }

        $this->lower = $lower;
        $this->upper = $upper;
        $this->step = abs($step);
    }

    public function length(): float|int
    {
        return (int) (abs($this->upper - $this->lower) / $this->step) + 1;
    }

    public function count(): int
    {
        return (int) $this->length();
    }

    public function includes($n): bool
    {
        if ($this->upper >= $this->lower) {
            return $n >= $this->lower && $n <= $this->upper;
        } else {
            return $n <= $this->lower && $n >= $this->upper;
        }
    }

    public function random($seed = null): int
    {
        if (isset($seed)) {
            mt_srand($seed);
        }

        return $this->upper >= $this->lower ?
        mt_rand($this->lower, $this->upper) :
        mt_rand($this->upper, $this->lower);
    }

    public function rewind(): void
    {
        $this->current = $this->lower;
    }

    public function key(): int|float
    {
        return $this->current;
    }

    public function valid(): bool
    {
        if ($this->upper >= $this->lower) {
            return $this->current >= $this->lower &&
            $this->current <= $this->upper;
        } else {
            return $this->current <= $this->lower &&
            $this->current >= $this->upper;
        }
    }

    #[ReturnTypeWillChange]
    public function next(): RangeIterator
    {
        $this->current += $this->upper >= $this->lower ? $this->step : -$this->step;
        return $this;
    }

    public function current(): int|float
    {
        return $this->current;
    }
}
