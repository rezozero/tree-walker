<?php

declare(strict_types=1);

namespace RZ\TreeWalker;

use Doctrine\Common\Collections\Collection;

interface WalkerInterface extends \Countable, \ArrayAccess
{
    /**
     * @param mixed $item
     *
     * @return callable
     */
    public function getDefinitionForItem($item): callable;

    /**
     * @param string   $classname
     * @param callable $definition
     *
     * @return WalkerInterface
     */
    public function addDefinition(string $classname, callable $definition): WalkerInterface;

    /**
     * @param string   $classname
     * @param callable $countDefinition
     *
     * @return WalkerInterface
     */
    public function addCountDefinition(string $classname, callable $countDefinition): WalkerInterface;

    /**
     * @param mixed $item
     *
     * @return callable|null
     */
    public function getCountDefinitionForItem($item): ?callable;

    /**
     * @return WalkerInterface
     */
    public function getRoot(): WalkerInterface;

    /**
     * @return bool
     */
    public function isRoot(): bool;

    /**
     * @return Collection
     */
    public function getChildren(): Collection;

    /**
     * @return WalkerInterface|null Return parent walker or null if this is root walker.
     */
    public function getParent(): ?WalkerInterface;

    /**
     * @return WalkerInterface|null Return next walker in parent or null if this is root walker or the last item.
     */
    public function getNext(): ?WalkerInterface;

    /**
     * @return WalkerInterface|null Return previous walker in parent or null if this is root walker or the first item.
     */
    public function getPrevious(): ?WalkerInterface;

    /**
     * @return int|null Return walker index in parent or null if this is root walker.
     */
    public function getIndex(): ?int;

    /**
     * @return mixed
     */
    public function getItem();

    /**
     * This method must return a WalkerInterface for your given item. Null will be returned
     * if item cannot be found inside your tree.
     *
     * @param mixed $item Item to find through your tree walker.
     * @return WalkerInterface|null
     */
    public function getWalkerAtItem($item): ?WalkerInterface;

    /**
     * Traverse all Walker tree to find items instance of your given $classname and return their walkers
     * in a flat array.
     *
     * @param string $classname Full qualified class name to look for in walker tree.
     * @return array containing all found walkers for this classname. Array wil be empty if not found
     */
    public function getWalkersOfType(string $classname): array;

    /**
     * @return int|float
     * @deprecated Use getLevel
     */
    public function getCurrentLevel();

    /**
     * @return int|float
     */
    public function getLevel();

    /**
     * @return float|int
     */
    public function getMaxLevel();

    /**
     * @param string $key
     * @param mixed $data
     * @return WalkerInterface
     */
    public function addMetadata(string $key, $data);

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getMetadata(string $key, $default = null);

    /**
     * @return array
     */
    public function getAllMetadata(): array;

    /**
     * Perform a custom equality check between current walker item and
     * another object instead of comparing objects memory ID.
     *
     * For example, to compare two Doctrine objects with same ID but not the
     * same in memory.
     *
     * @param mixed $item
     * @return bool
     */
    public function isItemEqualsTo($item): bool;
}
