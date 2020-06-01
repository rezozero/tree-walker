<?php
declare(strict_types=1);

namespace RZ\TreeWalker\Definition;

/**
 * A definition class must be invokable.
 *
 * @package RZ\TreeWalker\Definition
 */
final class ZeroChildrenDefinition
{
    use ContextualDefinitionTrait;

    public function __invoke()
    {
        return [];
    }
}
