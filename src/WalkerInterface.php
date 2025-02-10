<?php

declare(strict_types=1);

namespace RZ\TreeWalker;

use Doctrine\Common\Collections\Collection;

interface WalkerInterface extends \Countable, \ArrayAccess
{
    public function getDefinitionForItem(?object $item): callable;

    public function addDefinition(string $classname, callable $definition): static;

    public function addCountDefinition(string $classname, callable $countDefinition): static;

    public function getCountDefinitionForItem(?object $item): ?callable;

    public function getRoot(): static;

    public function isRoot(): bool;

    /**
     * @return Collection<int, static>
     *
     * @psalm-return Collection<int, static>
     */
    public function getChildren(): Collection;

    /**
     * @return static|null return parent walker or null if this is root walker
     */
    public function getParent(): ?static;

    /**
     * @return static|null return next walker in parent or null if this is root walker or the last item
     */
    public function getNext(): ?static;

    /**
     * @return static|null return previous walker in parent or null if this is root walker or the first item
     */
    public function getPrevious(): ?static;

    /**
     * @return int|null return walker index in parent or null if this is root walker
     */
    public function getIndex(): ?int;

    public function getItem(): ?object;

    /**
     * This method must return a WalkerInterface for your given item. Null will be returned
     * if item cannot be found inside your tree.
     *
     * @param object|null $item item to find through your tree walker
     */
    public function getWalkerAtItem(?object $item): ?static;

    /**
     * Traverse all Walker tree to find items instance of your given $classname and return their walkers
     * in a flat array.
     *
     * @param string $classname full qualified class name to look for in walker tree
     *
     * @return array<static> containing all found walkers for this classname. Array wil be empty if not found
     *
     * @psalm-return array<static>
     */
    public function getWalkersOfType(string $classname): array;

    /**
     * @deprecated Use getLevel
     */
    public function getCurrentLevel(): float|int;

    public function getLevel(): float|int;

    public function getMaxLevel(): float|int;

    public function addMetadata(string $key, mixed $data): static;

    /**
     * @return mixed|array
     */
    public function getMetadata(?string $key = null, mixed $default = null): mixed;

    /**
     * Perform a custom equality check between current walker item and
     * another object instead of comparing objects memory ID.
     *
     * For example, to compare two Doctrine objects with same ID but not the
     * same in memory.
     */
    public function isItemEqualsTo(?object $item): bool;
}
