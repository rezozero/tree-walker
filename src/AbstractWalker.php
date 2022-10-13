<?php

declare(strict_types=1);

namespace RZ\TreeWalker;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
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

    #[
        Serializer\Exclude,
        SymfonySerializer\Ignore
    ]
    protected CacheItemPoolInterface $cacheProvider;

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

    /**
     * @var array<callable>
     */
    #[
        Serializer\Exclude,
        SymfonySerializer\Ignore
    ]
    private array $definitions = [];

    /**
     * @var array<callable>
     */
    #[
        Serializer\Exclude,
        SymfonySerializer\Ignore
    ]
    private array $countDefinitions = [];

    /**
     * @var static
     */
    #[
        Serializer\Exclude,
        SymfonySerializer\Ignore
    ]
    private WalkerInterface $root;

    /**
     * @var object|null
     */
    #[
        Serializer\Groups(["walker"]),
        SymfonySerializer\Groups(["walker"]),
    ]
    private ?object $item;

    /**
     * @var static|null
     */
    #[
        Serializer\Groups(["parent"]),
        SymfonySerializer\Groups(["parent"]),
    ]
    private ?WalkerInterface $parent = null;

    #[
        Serializer\Exclude,
        SymfonySerializer\Ignore
    ]
    private WalkerContextInterface $context;

    #[
        Serializer\Groups(["walker_level"]),
        SymfonySerializer\Groups(["walker_level"]),
    ]
    private int|float $level;

    #[
        Serializer\Groups(["walker_level"]),
        SymfonySerializer\Groups(["walker_level"]),
    ]
    private int|float $maxLevel = \INF;

    #[
        Serializer\Groups(["walker_metadata"]),
        SymfonySerializer\Groups(["walker_metadata"]),
    ]
    private ?array $metadata = null;

    /**
     * @param static|null   $root
     * @param static|null   $parent
     * @param array                  $definitions
     * @param array                  $countDefinitions
     * @param object|null            $item
     * @param WalkerContextInterface $context
     * @param CacheItemPoolInterface $cacheProvider
     * @param int|float              $level
     * @param int|float              $maxLevel
     */
    final protected function __construct(
        ?WalkerInterface $root,
        ?WalkerInterface $parent,
        array &$definitions,
        array &$countDefinitions,
        ?object $item,
        WalkerContextInterface $context,
        CacheItemPoolInterface $cacheProvider,
        int|float $level = 0,
        int|float $maxLevel = \INF
    ) {
        $this->definitions = $definitions;
        $this->countDefinitions = $countDefinitions;
        if (null === $root) {
            $this->root = $this;
        } else {
            $this->root = $root;
        }
        $this->parent = $parent;
        $this->item = $item;
        $this->context = $context;
        $this->level = $level;
        $this->maxLevel = $maxLevel;
        $this->cacheProvider = $cacheProvider;

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
     */
    public function getWalkerAtItem(?object $item): ?static
    {
        return $this->doRecursiveFindWalkerForItem($this->getRoot(), $item);
    }

    /**
     * @param static $current
     * @param object|null $item
     *
     * @return static|null
     * @throws \ReflectionException
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
     *
     * @return bool
     */
    #[
        Serializer\Exclude,
        SymfonySerializer\Ignore
    ]
    public function isItemEqualsTo(?object $item): bool
    {
        return null !== $item && null !== $this->getItem() &&
            get_class($this->getItem()) === get_class($item) &&
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
     * @throws \ReflectionException
     */
    public function getChildren(): Collection
    {
        if (null === $this->children) {
            try {
                if ($this->level < $this->maxLevel) {
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
                    $this->children = $collection->map(function ($item) use ($maxLevel) {
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
                } else {
                    $this->children = new ArrayCollection();
                }
            } catch (WalkerDefinitionNotFound $e) {
                $this->children = new ArrayCollection();
            }
        }

        return $this->children;
    }

    /**
     * @inheritDoc
     * @throws InvalidArgumentException
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
            if (key_exists($className, $this->definitions)) {
                return $this->definitions[$className];
            }
        }

        throw new WalkerDefinitionNotFound('No definition was found for ' . get_class($item));
    }

    /**
     * @param object|null $item
     * @return string[]
     * @throws InvalidArgumentException
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

        $itemId = str_replace(
            ['{', '}', '(', ')', '/', '\\', '@', ':', '"'],
            '-',
            static::class . '_' . get_class($item)
        );
        $cacheItem = $this->getCacheProvider()->getItem($itemId);

        if (!$cacheItem->isHit()) {
            $class = new \ReflectionClass($item);
            $classList = [];
            do {
                $classList[] = $class->getName();
                $interfaces = $class->getInterfaceNames();
                if (is_array($interfaces)) {
                    $classList = array_merge(array_values($classList), array_values($interfaces));
                }
                $class = $class->getParentClass();
            } while (false !== $class);

            $cacheItem->set($classList);

            $this->getCacheProvider()->save($cacheItem);
            return $classList;
        }

        $classList = $cacheItem->get();
        if (!is_array($classList)) {
            throw new \RuntimeException('Item class list should be an array of string');
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
     * @param string          $classname
     *
     * @return array
     */
    private function doRecursiveFindWalkersOfType(WalkerInterface $current, string $classname): array
    {
        $foundItems = [];
        if ($current->getItem() instanceof $classname) {
            $foundItems[] = $current;
        }
        /** @var WalkerInterface $walker */
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
     * @param mixed $offset
     * @return bool
     * @throws \ReflectionException
     */
    public function offsetExists($offset): bool
    {
        return $this->getChildren()->offsetExists($offset);
    }

    /**
     * @inheritDoc
     * @param mixed $offset
     * @return mixed
     * @throws \ReflectionException
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->getChildren()->offsetGet($offset);
    }

    /**
     * @inheritDoc
     * @param mixed $offset
     * @param mixed $value
     * @throws \RuntimeException
     * @deprecated WalkerInterface has read-only children.
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        throw new \RuntimeException('WalkerInterface has read-only children.');
    }

    /**
     * @inheritDoc
     * @param mixed $offset
     * @throws \RuntimeException
     * @deprecated WalkerInterface has read-only children.
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        throw new \RuntimeException('WalkerInterface has read-only children.');
    }

    /**
     * @inheritDoc
     * @throws InvalidArgumentException
     * @throws \ReflectionException
     */
    public function count(): int
    {
        if (null === $this->count) {
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
     * @throws \ReflectionException
     */
    public function getIndex(): ?int
    {
        if (null !== $this->getParent()) {
            /**
             * @var int $key
             * @var WalkerInterface $sibling
             */
            foreach ($this->getParent()->getChildren() as $key => $sibling) {
                if ($this->isItemEqualsTo($sibling->getItem())) {
                    return (int) $key;
                }
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
     * @throws \ReflectionException
     */
    #[
        Serializer\Exclude,
        SymfonySerializer\Ignore
    ]
    public function getNext(): ?static
    {
        if (null !== $this->getParent()) {
            /**
             * @var int $key
             * @var static $sibling
             */
            foreach ($this->getParent()->getChildren() as $key => $sibling) {
                if ($this->isItemEqualsTo($sibling->getItem())) {
                    /** @var static $next */
                    $next = $this->getParent()->getChildren()->get($key + 1);
                    return $next;
                }
            }
        }
        return null;
    }

    /**
     * @return static|null
     * @throws \ReflectionException
     */
    #[
        Serializer\Exclude,
        SymfonySerializer\Ignore
    ]
    public function getPrevious(): ?static
    {
        if (null !== $this->getParent()) {
            /**
             * @var int $key
             * @var static $sibling
             */
            foreach ($this->getParent()->getChildren() as $key => $sibling) {
                if ($this->isItemEqualsTo($sibling->getItem())) {
                    /** @var static $previous */
                    $previous = $this->getParent()->getChildren()->get($key - 1);
                    return $previous;
                }
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
        if (null !== $this->metadata && array_key_exists($key, $this->metadata)) {
            return $this->metadata[$key];
        }
        return $default;
    }
}
