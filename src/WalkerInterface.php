<?php
declare(strict_types=1);

namespace RZ\TreeWalker;

use Doctrine\Common\Collections\Collection;

interface WalkerInterface extends \IteratorAggregate, \Countable
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
