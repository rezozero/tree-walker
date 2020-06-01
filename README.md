# Tree Walker

[![Build Status](https://travis-ci.org/rezozero/tree-walker.svg?branch=master)](https://travis-ci.org/rezozero/tree-walker) ![License](http://img.shields.io/:license-mit-blue.svg?style=flat) [![Packagist](https://img.shields.io/packagist/v/rezozero/tree-walker.svg?style=flat)](https://packagist.org/packages/rezozero/tree-walker)

**Creates a configurable tree walker using different definitions for each node based on its PHP class or interface.**

`WalkerInterface` implements `\IteratorAggregate` and `\Countable` in order to use it seamlessly in your PHP code and Twig templates. Each `WalkerInterface` will carry your *node* item and its children.

## Usage in Twig

### Walk forward
Here is an example of a **recursive** navigation item template using our `WalkerInterface`:
```twig
{# nav-item.html.twig #}
<li class="nav-item">
    <span>{{ item.title }}</span>
    {# 
     # Walker object must be your general navigation WalkerInterface 
     # and current page must be inside navigation graph.
     #
     # getWalkerAtItem method looks for current page in your Walker
     # and returns walker interface for current page.
     #}
    {# Always a good idea to check walker item count before going further #}
    {% if walker and walker|length %}
        <div class="dropdown-menu nav-children">
            <ul role="menu">
                {% for subWalker in walker %}
                    {% include 'nav-item.html.twig' with {
                        'walker': subWalker,
                        'item' : subWalker.item,
                    } only %}
                {% endfor %}
            </ul>
        </div>
    {% endif %}
</li>
```

### Walk backward
You can *reverse* walk (aka *moon walking*) to display a page breadcrumbs for example:

```twig
{# page.html.twig #}

{% macro walkBreadcrumbs(pageWalker) %}
    {% if pageWalker.parent %}
        {% set pageWalker = pageWalker.parent %}
        {# Recursive magic here … #}
        {{ _self.walkBreadcrumbs(pageWalker) }}
        {# Call macro itself before displaying to keep ancestors first #}
        {% if pageWalker.item is not Neutral %}
            <li class="breadcrumbs-item">
                <a href="{{ path(pageWalker.item) }}">{{ pageWalker.item.title }}</a>
            </li>
        {% endif %}
    {% endif %}
{% endmacro %}

<ul class="breadcrumbs">
    {# 
     # walker object must be your general navigation WalkerInterface 
     # and current page must be inside navigation graph.
     #
     # getWalkerAtItem method looks for current page in your Walker
     # and returns walker interface for current page.
     #}
    {% set pageWalker = walker.getWalkerAtItem(page) %}
    
    {# Recursive magic here … #}
    {{ _self.walkBreadcrumbs(pageWalker.getParent) }}
    
    <li class="breadcrumbs-item">{{ page.title }}</li>
</ul>
```

## Configure your Walker

1. Create a `WalkerContextInterface` instance to hold every service your `callable` definitions will use to fetch each tree node children. For example: a *Doctrine repository*, a *QueryBuilder*, even your *PDO* instance.
2. Create a custom *Walker* class **extending** `AbstractWalker`.   
You’ll notice that `AbstractWalker` is very strict and prevents overriding its *constructor* in order to abstract all `WalkerInterface` instantiations from your business logic. **All your custom logic must be included in `definitions` and `countDefinitions`.**
3. Add `definitions` and `countDefinitions` from your custom *Walker*. A *definition* `callable` must return an `array` (or an *iterable* object) of your items. A *countDefinition* `callable` must return an `int` representing your items number. *CountDefinitions* are optional: `AbstractWalker::count()` method will fallback on using `AbstractWalker::getChildren()->count()`.
4. Instantiate your custom Walker with your root item and your context object

Here is some pseudo PHP code example:

```php
<?php
use RZ\TreeWalker\WalkerInterface;
use RZ\TreeWalker\WalkerContextInterface;
use RZ\TreeWalker\AbstractWalker;
use RZ\TreeWalker\Definition\ContextualDefinitionTrait;

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

final class DummyChildrenDefinition
{
    use ContextualDefinitionTrait;

    public function __invoke(Dummy $dummy){
        if ($this->context instanceof DummyWalkerContext) {
            return array_merge(
                $this->context->getDummyRepository()->findByParentDummyId($dummy->getId()),
                $this->context->getNotADummyRepository()->findByParentDummyId($dummy->getId())
            );
        }
        throw new \InvalidArgumentException('Context should be instance of ' . DummyWalkerContext::class);
    }
}

final class DummyWalker extends AbstractWalker
{
    protected function initializeDefinitions()
    {
        /*
         * All Tree-walker logic occurs here…
         * You are free to code any logic to fetch your item children, and
         * to alter it given your WalkerContextInterface such as security, request…
         */
        $this->addDefinition(Dummy::class, new DummyChildrenDefinition($this->getContext()));
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

## Serialization groups

Any walker interface can be serialized with *jms/serializer* since they extends `AbstractWalker` class.
You should add serialization groups to ensure you do not fall into an infinite loop:

- `walker`: serializes flat members with no recursivity
- `children`: triggers walker children serialization until max level is reached.
- `parent`: triggers reverse walker parents serialization until root is reached.

Obviously, **do not use** `children` and `parent` groups at the same time…
