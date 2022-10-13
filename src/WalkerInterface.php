<?php

declare(strict_types=1);

namespace RZ\TreeWalker;

use Doctrine\Common\Collections\Collection;

interface WalkerInterface extends \Countable, \ArrayAccess
{
    /**
     * @param object|null $item
     * @return callable
     */
    public function getDefinitionForItem(?object $item): callable;

    /**
     * @param string   $classname
     * @param callable $definition
     *
     * @return static
     */
    public function addDefinition(string $classname, callable $definition): static;

    /**
     * @param string   $classname
     * @param callable $countDefinition
     *
     * @return static
     */
    public function addCountDefinition(string $classname, callable $countDefinition): static;

    /**
     * @param object|null $item
     *
     * @return callable|null
     */
    public function getCountDefinitionForItem(?object $item): ?callable;

    /**
     * @return static
     */
    public function getRoot(): static;

    /**
     * @return bool
     */
    public function isRoot(): bool;

    /**
     * @return Collection<static>
     * @psalm-return Collection<static>
     */
    public function getChildren(): Collection;

    /**
     * @return static|null Return parent walker or null if this is root walker.
     */
    public function getParent(): ?static;

    /**
     * @return static|null Return next walker in parent or null if this is root walker or the last item.
     */
    public function getNext(): ?static;

    /**
     * @return static|null Return previous walker in parent or null if this is root walker or the first item.
     */
    public function getPrevious(): ?static;

    /**
     * @return int|null Return walker index in parent or null if this is root walker.
     */
    public function getIndex(): ?int;

    /**
     * @return object|null
     */
    public function getItem(): ?object;

    /**
     * This method must return a WalkerInterface for your given item. Null will be returned
     * if item cannot be found inside your tree.
     *
     * @param object|null $item Item to find through your tree walker.
     * @return static|null
     */
    public function getWalkerAtItem(?object $item): ?static;

    /**
     * Traverse all Walker tree to find items instance of your given $classname and return their walkers
     * in a flat array.
     *
     * @param string $classname Full qualified class name to look for in walker tree.
     * @return array<static> containing all found walkers for this classname. Array wil be empty if not found
     * @psalm-return array<static>
     */
    public function getWalkersOfType(string $classname): array;

    /**
     * @return int|float
     * @deprecated Use getLevel
     */
    public function getCurrentLevel(): float|int;

    /**
     * @return int|float
     */
    public function getLevel(): float|int;

    /**
     * @return float|int
     */
    public function getMaxLevel(): float|int;

    /**
     * @param string $key
     * @param mixed $data
     * @return static
     */
    public function addMetadata(string $key, mixed $data): static;

    /**
     * @param string|null $key
     * @param mixed|null $default
     * @return mixed|array
     */
    public function getMetadata(?string $key = null, mixed $default = null): mixed;

    /**
     * Perform a custom equality check between current walker item and
     * another object instead of comparing objects memory ID.
     *
     * For example, to compare two Doctrine objects with same ID but not the
     * same in memory.
     *
     * @param object|null $item
     * @return bool
     */
    public function isItemEqualsTo(?object $item): bool;
}
