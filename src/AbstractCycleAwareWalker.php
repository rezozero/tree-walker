<?php

declare(strict_types=1);

namespace RZ\TreeWalker;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Serializer\Annotation as SymfonySerializer;

abstract class AbstractCycleAwareWalker extends AbstractWalker
{
    #[
        Serializer\Exclude,
        SymfonySerializer\Ignore
    ]
    private array $itemIds = [];

    /**
     * Prevent Walker to collect duplicate objects and enter into
     * infinite loop.
     *
     * @inheritDoc
     * @throws \ReflectionException
     */
    public function getChildren(): Collection
    {
        $root = $this->getRoot();
        if ($root->registerItem($this->getItem())) {
            return parent::getChildren();
        } else {
            return new ArrayCollection();
        }
    }

    /**
     * @param object|null $item
     * @return bool Return TRUE if item is not already registered into Walker
     */
    protected function registerItem(?object $item): bool
    {
        if (null === $item) {
            return false;
        }
        $itemId = \spl_object_id($item);
        if (!in_array($itemId, $this->itemIds)) {
            $this->itemIds[] = $itemId;
            return true;
        }
        return false;
    }
}
