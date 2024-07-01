<?php

declare(strict_types=1);

namespace RZ\TreeWalker\Tests\Walker;

use RZ\TreeWalker\AbstractCycleAwareWalker;
use RZ\TreeWalker\Tests\Mock\Dummy;
use RZ\TreeWalker\Tests\Walker\Definition\DummyChildrenDefinition;

class DummyWalker extends AbstractCycleAwareWalker
{
    protected function initializeDefinitions(): void
    {
        $this->addDefinition(Dummy::class, new DummyChildrenDefinition($this->getContext()));
    }
}
