<?php
declare(strict_types=1);

namespace RZ\TreeWalker;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use RZ\TreeWalker\Exception\WalkerDefinitionNotFound;

/**
 * Class AbstractWalker
 *
 * @package RZ\TreeWalker
 * @Serializer\ReadOnly
 */
abstract class AbstractWalker implements WalkerInterface
{
    /**
     * @var CacheProvider
     * @Serializer\Exclude()
     */
    protected $cacheProvider;
    /**
     * @var Collection|null
     * @Serializer\Groups({"children"})
     * @Serializer\Accessor(getter="getChildren")
     * @Serializer\AccessType("public_method")
     */
    private $children = null;
    /**
     * @var int|null
     * @Serializer\Groups({"walker"})
     * @Serializer\AccessType("public_method")
     * @Serializer\Accessor(getter="count")
     * @Serializer\SerializedName("childrenCount")
     */
    private $count = null;
    /**
     * @var array<callable>
     * @Serializer\Exclude()
     */
    private $definitions = [];
    /**
     * @var array<callable>
     * @Serializer\Exclude()
     */
    private $countDefinitions = [];
    /**
     * @var WalkerInterface
     * @Serializer\Exclude()
     */
    private $root;
    /**
     * @var mixed
     * @Serializer\Groups({"walker"})
     */
    private $item;
    /**
     * @var WalkerInterface|null
     * @Serializer\Groups({"parent"})
     */
    private $parent;
    /**
     * @var WalkerContextInterface
     * @Serializer\Exclude()
     */
    private $context;
    /**
     * @var int|float
     * @Serializer\Groups({"walker"})
     */
    private $level;
    /**
     * @var int|float
     * @Serializer\Groups({"walker"})
     */
    private $maxLevel = \INF;

    /**
     * AbstractWalker constructor.
     *
     * @param WalkerInterface|null   $root
     * @param WalkerInterface|null   $parent
     * @param array                  $definitions
     * @param array                  $countDefinitions
     * @param mixed                  $item
     * @param WalkerContextInterface $context
     * @param CacheProvider          $cacheProvider
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
        CacheProvider $cacheProvider,
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
     * @param CacheProvider|null          $cacheProvider
     *
     * @return WalkerInterface
     */
    public static function build(
        $item,
        WalkerContextInterface $context = null,
        $maxLevel = \INF,
        CacheProvider $cacheProvider = null
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
            $cacheProvider ?? new ArrayCache(),
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
        if ($current->getItem() === $item) {
            return $current;
        }
        foreach ($current as $walker) {
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
        foreach ($current as $walker) {
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
     */
    public function getIterator()
    {
        return $this->count() > 0 ? $this->getChildren()->getIterator() : new \ArrayIterator();
    }

    /**
     * @inheritDoc
     * @throws \ReflectionException
     */
    public function count()
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
     *
     * @return array
     * @throws \ReflectionException
     */
    protected function getItemClassesList($item): array
    {
        $itemId = static::class . '_' . get_class($item);

        if (!$this->getCacheProvider()->contains($itemId)) {
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

            $this->getCacheProvider()->save($itemId, $classList);
        }

        return $this->getCacheProvider()->fetch($itemId);
    }

    /**
     * @return CacheProvider
     */
    protected function getCacheProvider(): CacheProvider
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
                    $this->children = (new ArrayCollection($callable($this->item)))->map(function ($item) {
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
     * @inheritDoc
     * @throws \ReflectionException
     */
    public function getDefinitionForItem($item): callable
    {
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
     */
    public function getCurrentLevel()
    {
        return $this->level;
    }
}
