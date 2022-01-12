<?php

declare(strict_types=1);

namespace RZ\TreeWalker;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Psr\Cache\CacheItemPoolInterface;
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
     * @var CacheItemPoolInterface
     * @Serializer\Exclude()
     * @SymfonySerializer\Ignore()
     */
    protected CacheItemPoolInterface $cacheProvider;
    /**
     * @var Collection|null
     * @Serializer\Groups({"children"})
     * @SymfonySerializer\Groups({"children"})
     * @Serializer\Accessor(getter="getChildren")
     * @Serializer\AccessType("public_method")
     */
    private ?Collection $children = null;
    /**
     * @var int|null
     * @Serializer\Groups({"children_count"})
     * @SymfonySerializer\Groups({"children_count"})
     * @Serializer\AccessType("public_method")
     * @Serializer\Accessor(getter="count")
     * @Serializer\SerializedName("childrenCount")
     * @SymfonySerializer\SerializedName(serializedName="childrenCount")
     */
    private ?int $count = null;
    /**
     * @var array<callable>
     * @Serializer\Exclude()
     * @SymfonySerializer\Ignore()
     */
    private array $definitions = [];
    /**
     * @var array<callable>
     * @Serializer\Exclude()
     * @SymfonySerializer\Ignore()
     */
    private array $countDefinitions = [];
    /**
     * @var WalkerInterface
     * @Serializer\Exclude()
     * @SymfonySerializer\Ignore()
     */
    private WalkerInterface $root;
    /**
     * @var mixed
     * @Serializer\Groups({"walker"})
     * @SymfonySerializer\Groups({"walker"})
     */
    private $item;
    /**
     * @var WalkerInterface|null
     * @Serializer\Groups({"parent"})
     * @SymfonySerializer\Groups({"parent"})
     */
    private ?WalkerInterface $parent = null;
    /**
     * @var WalkerContextInterface
     * @Serializer\Exclude()
     * @SymfonySerializer\Ignore()
     */
    private WalkerContextInterface $context;
    /**
     * @var int|float
     * @Serializer\Groups({"walker_level"})
     * @SymfonySerializer\Groups({"walker_level"})
     */
    private $level;
    /**
     * @var int|float
     * @Serializer\Groups({"walker_level"})
     * @SymfonySerializer\Groups({"walker_level"})
     */
    private $maxLevel = \INF;
    /**
     * @var array|null
     * @Serializer\Groups({"walker_metadata"})
     * @SymfonySerializer\Groups({"walker_metadata"})
     */
    private ?array $metadata = null;

    /**
     * @param WalkerInterface|null   $root
     * @param WalkerInterface|null   $parent
     * @param array                  $definitions
     * @param array                  $countDefinitions
     * @param mixed                  $item
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
        $item,
        WalkerContextInterface $context,
        CacheItemPoolInterface $cacheProvider,
        $level = 0,
        $maxLevel = \INF
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

    /**
     * @return void
     */
    abstract protected function initializeDefinitions();

    /**
     * @param mixed                       $item
     * @param WalkerContextInterface|null $context
     * @param int|float                   $maxLevel
     * @param CacheItemPoolInterface|null $cacheProvider
     *
     * @return WalkerInterface
     */
    public static function build(
        $item,
        ?WalkerContextInterface $context = null,
        $maxLevel = \INF,
        ?CacheItemPoolInterface $cacheProvider = null
    ): WalkerInterface {
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
    public function getWalkerAtItem($item): ?WalkerInterface
    {
        return $this->doRecursiveFindWalkerForItem($this->getRoot(), $item);
    }

    /**
     * @inheritDoc
     */
    public function getRoot(): WalkerInterface
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
     * @param WalkerInterface $current
     * @param mixed           $item
     *
     * @return WalkerInterface|null
     */
    private function doRecursiveFindWalkerForItem(WalkerInterface $current, &$item): ?WalkerInterface
    {
        if ($current->isItemEqualsTo($item)) {
            return $current;
        }
        foreach ($current->getChildren() as $walker) {
            if (null !== $innerWalker = $this->doRecursiveFindWalkerForItem($walker, $item)) {
                return $innerWalker;
            }
        }

        return null;
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
        foreach ($current->getChildren() as $walker) {
            $foundItems = array_merge($foundItems, $this->doRecursiveFindWalkersOfType($walker, $classname));
        }

        return $foundItems;
    }

    /**
     * @inheritDoc
     */
    public function addDefinition(string $classname, callable $definition): WalkerInterface
    {
        $this->definitions[$classname] = $definition;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addCountDefinition(string $classname, callable $countDefinition): WalkerInterface
    {
        $this->countDefinitions[$classname] = $countDefinition;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getItem()
    {
        return $this->item;
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
    public function offsetUnset($offset)
    {
        throw new \RuntimeException('WalkerInterface has read-only children.');
    }

    /**
     * @inheritDoc
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
     * @throws \ReflectionException
     */
    public function getCountDefinitionForItem($item): ?callable
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
     * @param mixed $item
     * @return array
     * @throws \ReflectionException
     */
    protected function getItemClassesList($item): array
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

        return $cacheItem->get();
    }

    /**
     * @return CacheItemPoolInterface
     */
    protected function getCacheProvider(): CacheItemPoolInterface
    {
        return $this->cacheProvider;
    }

    /**
     * @return Collection
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
                    /*
                     * Call invokable definition with current item and current Walker
                     * if you need to add metadata to your Walker after fetching its children.
                     */
                    $this->children = $collection->map(function ($item) {
                        return new static(
                            $this->getRoot(),
                            $this,
                            $this->definitions,
                            $this->countDefinitions,
                            $item,
                            $this->context,
                            $this->cacheProvider,
                            $this->level + 1,
                            $this->maxLevel
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
     * @return WalkerInterface|null
     */
    public function getParent(): ?WalkerInterface
    {
        return $this->parent;
    }

    /**
     * @return int|null
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
     * @return WalkerInterface|null
     */
    public function getNext(): ?WalkerInterface
    {
        if (null !== $this->getParent()) {
            /**
             * @var int $key
             * @var WalkerInterface $sibling
             */
            foreach ($this->getParent()->getChildren() as $key => $sibling) {
                if ($this->isItemEqualsTo($sibling->getItem())) {
                    return $this->getParent()->getChildren()->get($key + 1);
                }
            }
        }
        return null;
    }

    /**
     * @return WalkerInterface|null
     */
    public function getPrevious(): ?WalkerInterface
    {
        if (null !== $this->getParent()) {
            /**
             * @var int $key
             * @var WalkerInterface $sibling
             */
            foreach ($this->getParent()->getChildren() as $key => $sibling) {
                if ($this->isItemEqualsTo($sibling->getItem())) {
                    return $this->getParent()->getChildren()->get($key - 1);
                }
            }
        }
        return null;
    }

    /**
     * @param mixed $item
     *
     * @return bool
     */
    public function isItemEqualsTo($item): bool
    {
        return get_class($this->getItem()) === get_class($item) &&
            $this->getItem() === $item;
    }

    /**
     * @inheritDoc
     * @throws \ReflectionException
     */
    public function getDefinitionForItem($item): callable
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
     * @return WalkerContextInterface
     */
    public function getContext(): WalkerContextInterface
    {
        return $this->context;
    }

    /**
     * @return int|float
     * @deprecated Use getLevel
     */
    public function getCurrentLevel()
    {
        return $this->getLevel();
    }

    /**
     * @return int|float
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * @return float|int
     */
    public function getMaxLevel()
    {
        return $this->maxLevel;
    }

    /**
     * @param string $key
     * @param mixed $data
     * @return $this|WalkerInterface
     */
    public function addMetadata(string $key, $data)
    {
        if (null === $this->metadata) {
            $this->metadata = [];
        }
        $this->metadata[$key] = $data;
        return $this;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getMetadata(string $key, $default = null)
    {
        if (null !== $this->metadata && array_key_exists($key, $this->metadata)) {
            return $this->metadata[$key];
        }
        return $default;
    }

    /**
     * @return array
     */
    public function getAllMetadata(): array
    {
        return $this->metadata ?? [];
    }
}
