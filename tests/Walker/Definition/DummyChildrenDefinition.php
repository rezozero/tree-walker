<?php

declare(strict_types=1);

namespace RZ\TreeWalker\Tests\Walker\Definition;

use RZ\TreeWalker\Definition\ContextualDefinitionTrait;
use RZ\TreeWalker\Tests\Mock\Dummy;
use RZ\TreeWalker\WalkerInterface;

class DummyChildrenDefinition
{
    use ContextualDefinitionTrait;

    public function __invoke(Dummy $dummy, WalkerInterface $walker): array
    {
        return [
            new Dummy($dummy->name . ' - child 1'),
            new Dummy($dummy->name . ' - child 2'),
            new Dummy($dummy->name . ' - child 3'),
        ];
    }
}
