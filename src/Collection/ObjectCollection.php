<?php

declare(strict_types=1);

namespace Xparse\ElementFinder\Collection;

use Xparse\ElementFinder\ElementFinderInterface;

/**
 * @author Ivan Shcherbak <alotofall@gmail.com>
 */
class ObjectCollection implements \IteratorAggregate, \Countable
{

    /**
     * @var ElementFinderInterface[]
     */
    private $items;

    /**
     * @var bool
     */
    private $validated = false;


    /**
     * @param ElementFinderInterface[] $items
     * @throws \Exception
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }


    /**
     * Return number of items in this collection
     *
     * @return int
     * @throws \InvalidArgumentException
     */
    final public function count(): int
    {
        return \count($this->all());
    }


    /**
     * @return null|ElementFinderInterface
     * @throws \InvalidArgumentException
     */
    final public function last()
    {
        $items = $this->all();
        if (\count($items) === 0) {
            return null;
        }
        return end($items);
    }

    /**
     * @return null|ElementFinderInterface
     * @throws \InvalidArgumentException
     */
    final public function first()
    {
        $items = $this->all();
        if (\count($items) === 0) {
            return null;
        }
        return reset($items);
    }


    /**
     * @return ElementFinderInterface[]
     * @throws \InvalidArgumentException
     */
    final public function all(): array
    {
        if (!$this->validated) {
            foreach ($this->items as $key => $item) {
                if (!$item instanceof ElementFinderInterface) {
                    $className = ($item === null) ? \gettype($item) : \get_class($item);
                    throw new \InvalidArgumentException(
                        sprintf(
                            'Invalid object type. Expect %s given %s Check item %d',
                            ElementFinderInterface::class,
                            $className,
                            $key
                        )
                    );
                }
            }
            $this->validated = true;
        }
        return $this->items;
    }

    /**
     * @param ObjectCollection $collection
     * @return ObjectCollection
     * @throws \Exception
     */
    final public function merge(ObjectCollection $collection): ObjectCollection
    {
        return new ObjectCollection(array_merge($this->all(), $collection->all()));
    }


    /**
     * @param ElementFinderInterface $element
     * @return ObjectCollection
     * @throws \Exception
     */
    final public function add(ElementFinderInterface $element): ObjectCollection
    {
        $items = $this->all();
        $items[] = $element;
        return new ObjectCollection($items);
    }


    /**
     * @param int $index
     * @return null|ElementFinderInterface
     * @throws \InvalidArgumentException
     */
    final public function get(int $index)
    {
        $items = $this->all();
        if (array_key_exists($index, $items)) {
            return $items[$index];
        }
        return null;
    }


    /**
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return ElementFinderInterface[]|\ArrayIterator
     * @throws \InvalidArgumentException
     */
    final public function getIterator()
    {
        return new \ArrayIterator($this->all());
    }
}
