# Tree Walker

[![Build Status](https://travis-ci.org/rezozero/tree-walker.svg?branch=master)](https://travis-ci.org/rezozero/tree-walker) ![License](http://img.shields.io/:license-mit-blue.svg?style=flat) [![Packagist](https://img.shields.io/packagist/v/rezozero/tree-walker.svg?style=flat)](https://packagist.org/packages/rezozero/tree-walker)

**Creates a configurable tree walker using different definitions for each node based on its PHP class or interface.**

`WalkerInterface` implements `\IteratorAggregate` and `\Countable` in order to use it seamlessly in your PHP code and Twig templates. Each `WalkerInterface` will carry your *node* item and its children.

## Usage in Twig

Here is an example of a **recursive** navigation item template using our `WalkerInterface`:
```twig
{# nav-item.html.twig #}
<li class="nav-item">
    <span>{{ item.title }}</span>
    {# Always a good idea to check walker item count before going further #}
    {% if walker and walker|length %}
        <div class="dropdown-menu nav-children">
            <ul role="menu">
                {% for walkerNode in walker %}
                    {% include 'nav-item.html.twig' with {
                        'walker': walkerNode,
                        'item' : walkerNode.item,
                    } only %}
                {% endfor %}
            </ul>
        </div>
    {% endif %}
</li>
```

## Configure your Walker

1. Create a `WalkerContextInterface` instance to hold every service your `\Closure` definitions will use to fetch each tree node children. For example: a *Doctrine repository*, a *QueryBuilder*, even your *PDO* instance.
2. Create a custom *Walker* class **extending** `AbstractWalker`.   
You’ll notice that `AbstractWalker` is very strict and prevents overriding its *constructor* in order to abstract all `WalkerInterface` instantiations from your business logic. **All your custom logic must be included in `definitions` and `countDefinitions`.**
3. Add `definitions` and `countDefinitions` from your custom *Walker*. A *definition* `\Closure` must return an `array` (or an *iterable* object) of your items. A *countDefinition* `\Closure` must return an `int` representing your items number. *CountDefinitions* are optional: `AbstractWalker::count()` method will fallback on using `AbstractWalker::getChildren()->count()`.
4. Instantiate your custom Walker with your root item and your context object

Here is some pseudo PHP code example:

```php
<?php
use RZ\TreeWalker\WalkerInterface;
use RZ\TreeWalker\WalkerContextInterface;
use RZ\TreeWalker\AbstractWalker;

class Dummy
{
    // Current dummy identifier
    private $id;
    // Nested tree style current dummy parent identifier
    private $parentDummyId;

    public function hello(){
        return 'Hey Ho!';
    }

    public function getId(){
        return $this->id;
    }
}

class NotADummy
{
    // Nested tree style current dummy parent identifier
    private $parentDummyId;

    public function sayNothing(){
        return '…';
    }
}

class DummyWalkerContext implements WalkerContextInterface
{
    private $dummyRepository;
    private $notADummyRepository;

    public function __construct($dummyRepository, $notADummyRepository)
    {
        $this->dummyRepository = $dummyRepository;
        $this->notADummyRepository = $notADummyRepository;
    }

    public function getDummyRepository()
    {
        return $this->dummyRepository;
    }

    public function getNotADummyRepository()
    {
        return $this->notADummyRepository;
    }
}

class DummyWalker extends AbstractWalker
{
    protected function initializeDefinitions()
    {
        /*
         * All Tree-walker logic occurs here…
         * You are free to code any logic to fetch your item children, and
         * to alter it given your WalkerContextInterface such as security, request…
         */
        $this->addDefinition(Dummy::class, function (Dummy $dummy) {
            $context = $this->getContext();
            if ($context instanceof DummyWalkerContext) {
                return array_merge(
                    $context->getDummyRepository()->findByParentDummyId($dummy->getId()),
                    $context->getNotADummyRepository()->findByParentDummyId($dummy->getId())
                );
            }
            throw new \InvalidArgumentException('Context should be instance of ' . DummyWalkerContext::class);
        });
    }
}

/*
 * Some stupid recursive function to 
 * walk entire entities tree graph
 */
function everyDummySayHello(WalkerInterface $walker) {
    if ($walker->getItem() instanceof Dummy) {
        echo $walker->getItem()->hello();
    }
    if ($walker->getItem() instanceof NotADummy) {
        echo $walker->getItem()->sayNothing();
    }
    if ($walker->count() > 0) {
        foreach ($walker as $childWalker) {
            // I love recursive functions…
            everyDummySayHello($childWalker);
        }
    }
}

// -------------------------------------------------------
// Just provide some $entityManager to fetch your entities 
// from a database, a file, or your fridge…
// -------------------------------------------------------
$dummyRepository = $entityManager->getRepository(Dummy::class);
$notADummyRepository = $entityManager->getRepository(NotADummy::class);
$firstItem = $dummyRepository->findOneById(1);

// Calling an AbstractWalker constructor is forbidden, always
// use static build method
$walker = DummyWalker::build(
    $firstItem,
    new DummyWalkerContext($dummyRepository, $notADummyRepository),
    3 // max level count
);

everyDummySayHello($walker);
```
