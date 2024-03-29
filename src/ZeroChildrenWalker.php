<?php

declare(strict_types=1);

namespace RZ\TreeWalker;

use RZ\TreeWalker\Definition\ZeroChildrenDefinition;

class ZeroChildrenWalker extends AbstractCycleAwareWalker
{
    protected function initializeDefinitions(): void
    {
        $this->addDefinition('object', new ZeroChildrenDefinition($this->getContext()));
    }
}
