<?php

declare(strict_types=1);

namespace RZ\TreeWalker\Definition;

use RZ\TreeWalker\WalkerContextInterface;

trait ContextualDefinitionTrait
{
    public function __construct(private readonly WalkerContextInterface $context)
    {
    }

    protected function getContext(): WalkerContextInterface
    {
        return $this->context;
    }
}
