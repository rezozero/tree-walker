<?php

declare(strict_types=1);

namespace RZ\TreeWalker;

use Doctrine\Common\Collections\Collection;
use Psr\Cache\InvalidArgumentException;

trait IteratorAggregateTrait
{
    abstract public function getChildren(): Collection;

    abstract public function count(): int;

    /**
     * @return array|\ArrayIterator|\Traversable
     * @throws InvalidArgumentException
     * @throws \ReflectionException
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return $this->count() > 0 ? $this->getChildren()->getIterator() : new \ArrayIterator();
    }
}
