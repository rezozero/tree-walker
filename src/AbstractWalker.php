<?php

declare(strict_types=1);

namespace RZ\TreeWalker;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use RuntimeException;
use RZ\TreeWalker\Definition\StoppableDefinition;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Serializer\Annotation as SymfonySerializer;
use RZ\TreeWalker\Exception\WalkerDefinitionNotFound;

/**
 * @package RZ\TreeWalker
 */
abstract class AbstractWalker implements WalkerInterface
{
    use IteratorAggregateTrait;

    /**
     * @var static
     */
    #[
        Serializer\Exclude,
        SymfonySerializer\Ignore
    ]
    private WalkerInterface $root;

    /**
     * @var Collection<static>|null
     */
    #[
        Serializer\Groups(["children"]),
        SymfonySerializer\Groups(["children"]),
        Serializer\Accessor(getter: "getChildren"),
        Serializer\AccessType(type: "public_method")
    ]
    private ?Collection $children = null;

    #[
        Serializer\Groups(["children_count"]),
        SymfonySerializer\Groups(["children_count"]),
        Serializer\Accessor(getter: "count"),
        Serializer\AccessType(type: "public_method"),
        Serializer\SerializedName("childrenCount"),
        SymfonySerializer\SerializedName(serializedName: "childrenCount")
    ]
    private ?int $count = null;

    #[
        Serializer\Groups(["walker_metadata"]),
        SymfonySerializer\Groups(["walker_metadata"]),
    ]
    private ?array $metadata = null;

    /**
     * @param static|null $root
     * @param static|null $parent
     * @param array $definitions
     * @param array $countDefinitions
     * @param object|null $item
     * @param WalkerContextInterface $context
     * @param CacheItemPoolInterface $cacheProvider
     * @param int|float $level
     * @param int|float $maxLevel
     */
    final protected function __construct(
        ?WalkerInterface $root,
        #[Serializer\Groups(["walker_parent"])]
        #[SymfonySerializer\Groups(["walker_parent"])]
        private readonly ?WalkerInterface $parent,
        #[Serializer\Exclude]
        #[SymfonySerializer\Ignore]
        private array &$definitions,
        #[Serializer\Exclude]
        #[SymfonySerializer\Ignore]
        private array &$countDefinitions,
        #[Serializer\Groups(["walker"])]
        #[SymfonySerializer\Groups(["walker"])]
        private readonly ?object $item,
        #[Serializer\Exclude]
        #[SymfonySerializer\Ignore]
        private readonly WalkerContextInterface $context,
        #[Serializer\Exclude]
        #[SymfonySerializer\Ignore]
        private readonly CacheItemPoolInterface $cacheProvider,
        #[Serializer\Groups(["walker_level"])]
        #[SymfonySerializer\Groups(["walker_level"])]
        private readonly int|float $level = 0,
        #[Serializer\Groups(["walker_level"])]
        #[SymfonySerializer\Groups(["walker_level"])]
        private readonly int|float $maxLevel = \INF
    ) {
        if (null === $root) {
            $this->root = $this;
        } else {
            $this->root = $root;
        }
        $this->initializeDefinitions();
    }

    abstract protected function initializeDefinitions(): void;

    /**
     * @param object|null                 $item
     * @param WalkerContextInterface|null $context
     * @param int|float                   $maxLevel
     * @param CacheItemPoolInterface|null $cacheProvider
     *
     * @return static
     */
    public static function build(
        ?object $item,
        ?WalkerContextInterface $context = null,
        int|float $maxLevel = \INF,
        ?CacheItemPoolInterface $cacheProvider = null
    ): static {
        $definitions = [];
        $countDefinitions = [];

        return new static(
            null,
            null,
            $definitions,
            $countDefinitions,
            $item,
            $context ?? new EmptyWalkerContext(),
            $cacheProvider ?? new ArrayAdapter(),
            0,
            $maxLevel
        );
    }

    /**
     * @inheritDoc
     * @throws InvalidArgumentException
     */
    public function getWalkerAtItem(?object $item): ?static
    {
        return $this->doRecursiveFindWalkerForItem($this->getRoot(), $item);
    }

    /**
     * @param static $current
     * @param object|null $item
     * @return static|null
     * @throws InvalidArgumentException
     */
    private function doRecursiveFindWalkerForItem(WalkerInterface $current, ?object &$item): ?static
    {
        if ($current->isItemEqualsTo($item)) {
            return $current;
        }
        /** @var static $walker */
        foreach ($current->getChildren() as $walker) {
            if (null !== $innerWalker = $this->doRecursiveFindWalkerForItem($walker, $item)) {
                return $innerWalker;
            }
        }

        return null;
    }

    /**
     * @param object|null $item
     * @return bool
     */
    #[
        Serializer\Exclude,
        SymfonySerializer\Ignore
    ]
    public function isItemEqualsTo(?object $item): bool
    {
        return null !== $item && null !== $this->getItem() &&
            \get_class($this->getItem()) === \get_class($item) &&
            $this->getItem() === $item;
    }

    /**
     * @inheritDoc
     */
    public function getItem(): ?object
    {
        return $this->item;
    }

    /**
     * @inheritDoc
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getChildren(): Collection
    {
        if (null !== $this->children) {
            return $this->children;
        }

        return $this->children = $this->doGetChildren();
    }

    /**
     * @throws InvalidArgumentException
     */
    private function doGetChildren(): Collection
    {
        if ($this->level >= $this->maxLevel) {
            return new ArrayCollection();
        }

        try {
            $callable = $this->getDefinitionForItem($this->item);
            $collection = (new ArrayCollection($callable($this->item, $this)))->filter(function ($item) {
                return null !== $item;
            });

            $maxLevel = $this->maxLevel;

            /*
             * If definition is stopping collection, this item's children MUST not walk for their
             * own children. So we set max level walker to current level.
             */
            if ($callable instanceof StoppableDefinition && $callable->isStoppingCollectionOnceInvoked()) {
                $maxLevel = $this->level;
            }

            /*
             * Call invokable definition with current item and current Walker
             * if you need to add metadata to your Walker after fetching its children.
             */
            return $collection->map(function ($item) use ($maxLevel) {
                return new static(
                    $this->getRoot(),
                    $this,
                    $this->definitions,
                    $this->countDefinitions,
                    $item,
                    $this->context,
                    $this->cacheProvider,
                    $this->level + 1,
                    $maxLevel
                );
            });
        } catch (WalkerDefinitionNotFound $e) {
            return new ArrayCollection();
        }
    }

    /**
     * @inheritDoc
     * @throws InvalidArgumentException
     * @throws WalkerDefinitionNotFound
     * @throws RuntimeException
     */
    #[
        Serializer\Exclude,
        SymfonySerializer\Ignore
    ]
    public function getDefinitionForItem(?object $item): callable
    {
        if (null === $item) {
            throw new WalkerDefinitionNotFound('Cannot walk a NULL item.');
        }

        $classList = $this->getItemClassesList($item);

        foreach ($classList as $className) {
            if (\array_key_exists($className, $this->definitions)) {
                return $this->definitions[$className];
            }
        }

        throw new WalkerDefinitionNotFound('No definition was found for ' . get_class($item));
    }

    /**
     * @param object|null $item
     * @return string[]
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    #[
        Serializer\Exclude,
        SymfonySerializer\Ignore
    ]
    protected function getItemClassesList(?object $item): array
    {
        if (null === $item) {
            return [];
        }

        $itemId = \str_replace(
            ['{', '}', '(', ')', '/', '\\', '@', ':', '"'],
            '-',
            static::class . '_' . \get_class($item)
        );
        $cacheItem = $this->getCacheProvider()->getItem($itemId);

        if (!$cacheItem->isHit()) {
            $class = new \ReflectionClass($item);
            $classList = [];
            do {
                $classList[] = $class->getName();
                $interfaces = $class->getInterfaceNames();
                $classList = \array_merge(\array_values($classList), \array_values($interfaces));
                $class = $class->getParentClass();
            } while (false !== $class);

            $cacheItem->set($classList);

            $this->getCacheProvider()->save($cacheItem);
            return $classList;
        }

        $classList = $cacheItem->get();
        if (!is_array($classList)) {
            throw new RuntimeException('Item class list should be an array of string');
        }
        return $classList;
    }

    /**
     * @return CacheItemPoolInterface
     */
    #[
        Serializer\Exclude,
        SymfonySerializer\Ignore
    ]
    protected function getCacheProvider(): CacheItemPoolInterface
    {
        return $this->cacheProvider;
    }

    /**
     * @inheritDoc
     */
    public function getRoot(): static
    {
        return $this->root;
    }

    /**
     * @inheritDoc
     */
    public function isRoot(): bool
    {
        return $this === $this->getRoot();
    }

    /**
     * @inheritDoc
     */
    public function getWalkersOfType(string $classname): array
    {
        return $this->doRecursiveFindWalkersOfType($this->getRoot(), $classname);
    }

    /**
     * @param WalkerInterface $current
     * @param string $classname
     * @return array
     */
    private function doRecursiveFindWalkersOfType(WalkerInterface $current, string $classname): array
    {
        $foundItems = [];
        if ($current->getItem() instanceof $classname) {
            $foundItems[] = $current;
        }
        foreach ($current->getChildren() as $walker) {
            $foundItems = array_merge($foundItems, $this->doRecursiveFindWalkersOfType($walker, $classname));
        }

        return $foundItems;
    }

    /**
     * @inheritDoc
     */
    public function addDefinition(string $classname, callable $definition): static
    {
        $this->definitions[$classname] = $definition;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addCountDefinition(string $classname, callable $countDefinition): static
    {
        $this->countDefinitions[$classname] = $countDefinition;

        return $this;
    }

    /**
     * @inheritDoc
     * @param int $offset
     * @return bool
     * @throws InvalidArgumentException
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->getChildren()->offsetExists($offset);
    }

    /**
     * @inheritDoc
     * @param int $offset
     * @return mixed
     * @throws InvalidArgumentException
     */
    #[\ReturnTypeWillChange]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->getChildren()->offsetGet($offset);
    }

    /**
     * @inheritDoc
     * @param mixed $offset
     * @param mixed $value
     * @throws RuntimeException
     * @deprecated WalkerInterface has read-only children.
     */
    #[\ReturnTypeWillChange]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new RuntimeException('WalkerInterface has read-only children.');
    }

    /**
     * @inheritDoc
     * @param mixed $offset
     * @throws RuntimeException
     * @deprecated WalkerInterface has read-only children.
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset(mixed $offset): void
    {
        throw new RuntimeException('WalkerInterface has read-only children.');
    }

    /**
     * @inheritDoc
     * @throws InvalidArgumentException
     * @throws \ReflectionException
     */
    public function count(): int
    {
        if (null !== $this->count) {
            return $this->count;
        }

        if ($this->level < $this->maxLevel) {
            $callable = $this->getCountDefinitionForItem($this->item);
            if (null !== $callable) {
                $this->count = $callable($this->item);
            } else {
                $this->count = $this->getChildren()->count();
            }
        } else {
            $this->count = 0;
        }

        return $this->count;
    }

    /**
     * @inheritDoc
     * @throws InvalidArgumentException
     */
    public function getCountDefinitionForItem(?object $item): ?callable
    {
        $classList = $this->getItemClassesList($item);

        foreach ($classList as $className) {
            if (key_exists($className, $this->countDefinitions)) {
                return $this->countDefinitions[$className];
            }
        }

        return null;
    }

    /**
     * @return int|null
     * @throws InvalidArgumentException
     */
    public function getIndex(): ?int
    {
        if (null === $this->getParent()) {
            return null;
        }

        foreach ($this->getParent()->getChildren() as $key => $sibling) {
            if ($this->isItemEqualsTo($sibling->getItem())) {
                return (int) $key;
            }
        }

        return null;
    }

    /**
     * @return static|null
     */
    public function getParent(): ?static
    {
        return $this->parent;
    }

    /**
     * @return static|null
     * @throws InvalidArgumentException
     */
    #[
        Serializer\Exclude,
        SymfonySerializer\Ignore
    ]
    public function getNext(): ?static
    {
        if (null === $this->getParent()) {
            return null;
        }

        /**
         * @var int $key
         * @var static $sibling
         */
        foreach ($this->getParent()->getChildren() as $key => $sibling) {
            if ($this->isItemEqualsTo($sibling->getItem())) {
                return $this->getParent()->getChildren()->get($key + 1);
            }
        }

        return null;
    }

    /**
     * @return static|null
     * @throws InvalidArgumentException
     */
    #[
        Serializer\Exclude,
        SymfonySerializer\Ignore
    ]
    public function getPrevious(): ?static
    {
        if (null === $this->getParent()) {
            return null;
        }

        /**
         * @var int $key
         * @var static $sibling
         */
        foreach ($this->getParent()->getChildren() as $key => $sibling) {
            if ($this->isItemEqualsTo($sibling->getItem())) {
                return $this->getParent()->getChildren()->get($key - 1);
            }
        }

        return null;
    }

    #[
        Serializer\Exclude,
        SymfonySerializer\Ignore
    ]
    public function getContext(): WalkerContextInterface
    {
        return $this->context;
    }

    /**
     * @return int|float
     * @deprecated Use getLevel
     */
    public function getCurrentLevel(): int|float
    {
        return $this->getLevel();
    }

    /**
     * @return int|float
     */
    public function getLevel(): int|float
    {
        return $this->level;
    }

    /**
     * @return float|int
     */
    public function getMaxLevel(): int|float
    {
        return $this->maxLevel;
    }

    /**
     * @param string $key
     * @param mixed $data
     * @return static
     */
    public function addMetadata(string $key, mixed $data): static
    {
        if (null === $this->metadata) {
            $this->metadata = [];
        }
        $this->metadata[$key] = $data;
        return $this;
    }

    /**
     * @param string|null $key
     * @param mixed|null $default
     * @return mixed|array
     */
    public function getMetadata(?string $key = null, mixed $default = null): mixed
    {
        if (null === $key) {
            return $this->metadata ?? [];
        }
        if (null !== $this->metadata && \array_key_exists($key, $this->metadata)) {
            return $this->metadata[$key];
        }
        return $default;
    }
}
