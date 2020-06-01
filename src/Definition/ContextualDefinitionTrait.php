<?php
declare(strict_types=1);

namespace RZ\TreeWalker\Definition;

use RZ\TreeWalker\WalkerContextInterface;

trait ContextualDefinitionTrait
{
    /**
     * @var WalkerContextInterface
     */
    private $context;

    /**
     * ContextualDefinitionTrait constructor.
     *
     * @param WalkerContextInterface $context
     */
    public function __construct(WalkerContextInterface $context)
    {
        $this->context = $context;
    }

    /**
     * @return WalkerContextInterface
     */
    protected function getContext(): WalkerContextInterface
    {
        return $this->context;
    }
}
