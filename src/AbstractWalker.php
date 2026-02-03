<?php

declare(strict_types=1);

namespace RZ\TreeWalker;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use RZ\TreeWalker\Definition\StoppableDefinition;
use RZ\TreeWalker\Exception\WalkerDefinitionNotFound;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Serializer\Attribute as Serializer;

abstract class AbstractWalker implements WalkerInterface
{
    use IteratorAggregateTrait;

    /**
     * @var static
     */
    #[Serializer\Ignore]
    private WalkerInterface $root;

    /**
     * @var Collection<int, static>|null
     */
    #[Serializer\Groups(['children'])]
    private ?Collection $children = null;

    /**
     * @var int<0,max>|null
     */
    #[Serializer\Groups(['children_count'])]
    #[Serializer\SerializedName(serializedName: 'childrenCount')]
    private ?int $count = null;

    #[Serializer\Groups(['walker_metadata'])]
    private ?array $metadata = null;

    /**
     * @param static|null     $root
     * @param static|null     $parent
     * @param array<callable> $definitions
     * @param array<callable> $countDefinitions
     */
    final protected function __construct(
        ?WalkerInterface $root,
        #[Serializer\Groups(['walker_parent'])]
        private readonly ?WalkerInterface $parent,
        #[Serializer\Ignore]
        private array &$definitions,
        #[Serializer\Ignore]
        private array &$countDefinitions,
        #[Serializer\Groups(['walker'])]
        private readonly ?object $item,
        #[Serializer\Ignore]
        private readonly WalkerContextInterface $context,
        #[Serializer\Ignore]
        private readonly CacheItemPoolInterface $cacheProvider,
        #[Serializer\Groups(['walker_level'])]
        private readonly int|float $level = 0,
        #[Serializer\Groups(['walker_level'])]
        private readonly int|float $maxLevel = \INF,
    ) {
        if (null === $root) {
            $this->root = $this;
        } else {
            $this->root = $root;
        }
        $this->initializeDefinitions();
    }

    abstract protected function initializeDefinitions(): void;

    public static function build(
        ?object $item,
        ?WalkerContextInterface $context = null,
        int|float $maxLevel = \INF,
        ?CacheItemPoolInterface $cacheProvider = null,
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
     * @throws InvalidArgumentException
     */
    public function getWalkerAtItem(?object $item): ?static
    {
        return $this->doRecursiveFindWalkerForItem($this->getRoot(), $item);
    }

    /**
     * @param static $current
     *
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

    #[Serializer\Ignore]
    public function isItemEqualsTo(?object $item): bool
    {
        return null !== $item && null !== $this->getItem()
            && $this->getItem()::class === $item::class
            && $this->getItem() === $item;
    }

    public function getItem(): ?object
    {
        return $this->item;
    }

    /**
     * @return Collection<int, static>
     *
     * @psalm-return Collection<int, static>
     *
     * @throws \RuntimeException
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
     * @return Collection<int, static>
     *
     * @psalm-return Collection<int, static>
     *
     * @throws InvalidArgumentException
     */
    private function doGetChildren(): Collection
    {
        if ($this->level >= $this->maxLevel) {
            return new ArrayCollection();
        }

        try {
            $callable = $this->getDefinitionForItem($this->item);
            /** @var ArrayCollection<int, object> $collection */
            $collection = (new ArrayCollection($callable($this->item, $this)))->filter(fn ($item) => null !== $item);

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
            return $collection->map(fn ($item) => new static(
                $this->getRoot(),
                $this,
                $this->definitions,
                $this->countDefinitions,
                $item,
                $this->context,
                $this->cacheProvider,
                $this->level + 1,
                $maxLevel
            ));
        } catch (WalkerDefinitionNotFound) {
            return new ArrayCollection();
        }
    }

    /**
     * @return callable(object|null, WalkerInterface=): array<object|null>
     *
     * @throws InvalidArgumentException
     * @throws WalkerDefinitionNotFound
     * @throws \RuntimeException
     */
    #[Serializer\Ignore]
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

        throw new WalkerDefinitionNotFound('No definition was found for '.$item::class);
    }

    /**
     * @return string[]
     *
     * @throws InvalidArgumentException
     * @throws \RuntimeException
     */
    #[Serializer\Ignore]
    protected function getItemClassesList(?object $item): array
    {
        if (null === $item) {
            return [];
        }

        $itemId = \str_replace(
            ['{', '}', '(', ')', '/', '\\', '@', ':', '"'],
            '-',
            static::class.'_'.$item::class
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

        /** @var string[]|null $classList */
        $classList = $cacheItem->get();
        if (!is_array($classList)) {
            throw new \RuntimeException('Item class list should be an array of string');
        }

        return $classList;
    }

    #[Serializer\Ignore]
    protected function getCacheProvider(): CacheItemPoolInterface
    {
        return $this->cacheProvider;
    }

    public function getRoot(): static
    {
        return $this->root;
    }

    public function isRoot(): bool
    {
        return $this === $this->getRoot();
    }

    /**
     * @param class-string $classname
     *
     * @return WalkerInterface[]
     */
    public function getWalkersOfType(string $classname): array
    {
        return $this->doRecursiveFindWalkersOfType($this->getRoot(), $classname);
    }

    /**
     * @param class-string $classname
     *
     * @return WalkerInterface[]
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

    public function addDefinition(string $classname, callable $definition): static
    {
        $this->definitions[$classname] = $definition;

        return $this;
    }

    public function addCountDefinition(string $classname, callable $countDefinition): static
    {
        $this->countDefinitions[$classname] = $countDefinition;

        return $this;
    }

    /**
     * @param int $offset
     *
     * @throws InvalidArgumentException
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->getChildren()->offsetExists($offset);
    }

    /**
     * @param int $offset
     *
     * @throws InvalidArgumentException
     */
    #[\ReturnTypeWillChange]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->getChildren()->offsetGet($offset);
    }

    /**
     * @throws \RuntimeException
     *
     * @deprecated walkerInterface has read-only children
     */
    #[\ReturnTypeWillChange]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \RuntimeException('WalkerInterface has read-only children.');
    }

    /**
     * @throws \RuntimeException
     *
     * @deprecated walkerInterface has read-only children
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset(mixed $offset): void
    {
        throw new \RuntimeException('WalkerInterface has read-only children.');
    }

    /**
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
     * @return callable(object|null): int<0,max>|null
     *
     * @throws InvalidArgumentException
     */
    public function getCountDefinitionForItem(?object $item): ?callable
    {
        $classList = $this->getItemClassesList($item);

        foreach ($classList as $className) {
            if (array_key_exists($className, $this->countDefinitions)) {
                return $this->countDefinitions[$className];
            }
        }

        return null;
    }

    /**
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

    public function getParent(): ?static
    {
        return $this->parent;
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Serializer\Ignore]
    public function getNext(): ?static
    {
        if (null === $this->getParent()) {
            return null;
        }

        /**
         * @var int    $key
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
     * @throws InvalidArgumentException
     */
    #[Serializer\Ignore]
    public function getPrevious(): ?static
    {
        if (null === $this->getParent()) {
            return null;
        }

        /**
         * @var int    $key
         * @var static $sibling
         */
        foreach ($this->getParent()->getChildren() as $key => $sibling) {
            if ($this->isItemEqualsTo($sibling->getItem())) {
                return $this->getParent()->getChildren()->get($key - 1);
            }
        }

        return null;
    }

    #[Serializer\Ignore]
    public function getContext(): WalkerContextInterface
    {
        return $this->context;
    }

    /**
     * @deprecated Use getLevel
     */
    public function getCurrentLevel(): int|float
    {
        return $this->getLevel();
    }

    public function getLevel(): int|float
    {
        return $this->level;
    }

    public function getMaxLevel(): int|float
    {
        return $this->maxLevel;
    }

    public function addMetadata(string $key, mixed $data): static
    {
        if (null === $this->metadata) {
            $this->metadata = [];
        }
        $this->metadata[$key] = $data;

        return $this;
    }

    /**
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
