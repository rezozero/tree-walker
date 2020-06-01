<?php
declare(strict_types=1);

namespace RZ\TreeWalker;

use Doctrine\Common\Collections\Collection;

interface WalkerInterface extends \IteratorAggregate, \Countable
{
    /**
     * @param mixed $item
     *
     * @return \Closure
     */
    public function getDefinitionForItem($item): \Closure;

    /**
     * @param string   $classname
     * @param \Closure $definition
     *
     * @return WalkerInterface
     */
    public function addDefinition(string $classname, \Closure $definition): WalkerInterface;

    /**
     * @param string   $classname
     * @param \Closure $countDefinition
     *
     * @return WalkerInterface
     */
    public function addCountDefinition(string $classname, \Closure $countDefinition): WalkerInterface;

    /**
     * @param mixed $item
     *
     * @return \Closure|null
     */
    public function getCountDefinitionForItem($item): ?\Closure;

    /**
     * @return WalkerInterface
     */
    public function getRoot(): WalkerInterface;

    /**
     * @return Collection
     */
    public function getChildren(): Collection;

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
}
