<?php

declare(strict_types=1);

namespace RZ\TreeWalker\Tests;

use Doctrine\Common\Collections\Collection;
use Psr\Cache\InvalidArgumentException;
use RZ\TreeWalker\EmptyWalkerContext;
use RZ\TreeWalker\Tests\Mock\Dummy;
use RZ\TreeWalker\Tests\Walker\DummyWalker;

class DummyTest extends SerializerTestCase
{
    public function testBuild(): void
    {
        $firstItem = new Dummy('ancestor');
        $walker = DummyWalker::build(
            $firstItem,
            new EmptyWalkerContext(),
            3 // max level count
        );
        $children = $walker->getChildren();
        $this->assertInstanceOf(Collection::class, $children);
        $this->assertCount(3, $children);
        $this->assertInstanceOf(DummyWalker::class, $children[0]);

        $this->assertEquals('ancestor - child 1', $children[0]->getItem()->name);
        $this->assertEquals('ancestor - child 2', $children[1]->getItem()->name);
        $this->assertEquals('ancestor - child 3', $children[2]->getItem()->name);
    }

    public function testSerialize(): void
    {
        $firstItem = new Dummy('ancestor');
        $walker = DummyWalker::build(
            $firstItem,
            new EmptyWalkerContext(),
            3 // max level count
        );

        $this->assertJsonStringEqualsJsonFile(
            __DIR__ . '/fixtures/dummy-walker.json',
            $this->getSerializer()->serialize($walker, 'json', ['groups' => ['children', 'walker', 'dummy']])
        );
    }

    /**
     * @throws \ReflectionException
     * @throws InvalidArgumentException
     */
    public function testSiblings(): void
    {
        $firstItem = new Dummy('ancestor');
        $walker = DummyWalker::build(
            $firstItem,
            new EmptyWalkerContext(),
            3 // max level count
        );

        $rootChildren = $walker->getChildren();
        $firstChild = $rootChildren->first();
        $secondChild = $rootChildren[1];

        $this->assertNotFalse($firstChild);
        $this->assertNotNull($secondChild);
        $this->assertEquals(1, $firstChild->getLevel());
        $this->assertEquals(3, $firstChild->getMaxLevel());
        $this->assertEquals($walker->getMaxLevel(), $firstChild->getMaxLevel());
        $this->assertEquals($secondChild, $firstChild->getNext());
        $this->assertEquals($firstChild, $secondChild->getPrevious());
        $this->assertNull($walker->getIndex(), 'Root index should be null');
        $this->assertTrue($firstChild->isItemEqualsTo($firstChild->getItem()));

        foreach ($rootChildren as $key => $child) {
            $index = $child->getIndex();
            $this->assertNotNull($child->getParent(), sprintf('Children parent should not be null (%d)', $key));
            $this->assertEquals($walker, $child->getParent(), 'Children parent should be the walker');
            $this->assertNotNull($index, sprintf('Children index should not be null (%d)', $key));
            $this->assertEquals($key, $index, 'Children index should be the same as the key');
            $this->assertEquals($child, $walker[$key], 'Children should be accessible by index');
        }
    }
}
