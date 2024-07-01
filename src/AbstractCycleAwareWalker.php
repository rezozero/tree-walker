<?php

declare(strict_types=1);

namespace RZ\TreeWalker;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Serializer\Annotation as SymfonySerializer;

abstract class AbstractCycleAwareWalker extends AbstractWalker
{
    public const MAX_CALL = 3;

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
     * @return bool Return TRUE if item has not overpassed the MAX_CALL limit
     */
    protected function registerItem(?object $item): bool
    {
        if (null === $item) {
            return false;
        }
        $itemId = \spl_object_id($item);
        if (!array_key_exists($itemId, $this->itemIds)) {
            $this->itemIds[$itemId] = 1;
            return true;
        }
        $this->itemIds[$itemId]++;
        if ($this->itemIds[$itemId] <= self::MAX_CALL) {
            return true;
        }
        return false;
    }
}
