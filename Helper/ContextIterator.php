<?php

declare(strict_types=1);

namespace Qubus\View\Helper;

use ArrayIterator;
use Countable;
use Iterator;
use Traversable;

use function count;
use function is_array;
use function iterator_count;

final class ContextIterator implements Iterator
{
    private ArrayIterator|Traversable|Countable $sequence;
    private ?int $length;
    private mixed $parent;
    private int $index;
    private int $count;
    private bool $first;
    private bool $last;

    public function __construct($sequence, $parent)
    {
        if ($sequence instanceof Traversable) {
            $this->length = $sequence instanceof Countable ?
            count($sequence) : iterator_count($sequence);
            $this->sequence = $sequence;
        } elseif (is_array($sequence)) {
            $this->length = count($sequence);
            $this->sequence = new ArrayIterator($sequence);
        } else {
            $this->length = 0;
            $this->sequence = new ArrayIterator();
        }
        $this->parent = $parent;
    }

    public function rewind(): void
    {
        $this->sequence->rewind();

        $this->index = 0;
        $this->count = $this->index + 1;
        $this->first = $this->count === 1;
        $this->last  = $this->count === $this->length;
    }

    public function key(): mixed
    {
        return $this->sequence->key();
    }

    public function valid(): bool
    {
        return $this->sequence->valid();
    }

    public function next(): void
    {
        $this->sequence->next();

        $this->index += 1;
        $this->count  = $this->index + 1;
        $this->first  = $this->count === 1;
        $this->last   = $this->count === $this->length;
    }

    public function current(): mixed
    {
        return $this->sequence->current();
    }
}
