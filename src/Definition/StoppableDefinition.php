<?php

declare(strict_types=1);

namespace RZ\TreeWalker\Definition;

interface StoppableDefinition
{
    public function isStoppingCollectionOnceInvoked(): bool;
}
