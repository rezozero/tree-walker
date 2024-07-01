<?php

declare(strict_types=1);

namespace RZ\TreeWalker\Tests;

use Doctrine\Common\Collections\Collection;
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
}
