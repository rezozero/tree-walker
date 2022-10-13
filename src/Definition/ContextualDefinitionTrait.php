<?php

declare(strict_types=1);

namespace RZ\TreeWalker\Definition;

use RZ\TreeWalker\WalkerContextInterface;

trait ContextualDefinitionTrait
{
    private WalkerContextInterface $context;

    public function __construct(WalkerContextInterface $context)
    {
        $this->context = $context;
    }

    protected function getContext(): WalkerContextInterface
    {
        return $this->context;
    }
}
