<?php

declare(strict_types=1);

namespace Xparse\ElementFinder\Collection;

use Xparse\ElementFinder\Collection\Filters\StringFilter\StringFilterInterface;
use Xparse\ElementFinder\Collection\Modify\StringModify\StringModifyInterface;

/**
 * @author Ivan Shcherbak <alotofall@gmail.com>
 */
class StringCollection implements \IteratorAggregate, \Countable
{

    /**
     * @var string[]
     */
    private $items;

    /**
     * @var bool
     */
    private $validated = false;


    /**
     * @param string[] $items
     */
    public function __construct(array $items = [])
    {
        $this->items = array_values($items);
    }


    /**
     * @return int
     * @throws \Exception
     */
    final public function count(): int
    {
        return \count($this->all());
    }


    /**
     * @return null|string
     * @throws \Exception
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
     * @return null|string
     * @throws \Exception
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
     * @return string[]
     * @throws \Exception
     */
    final public function all(): array
    {
        if (!$this->validated) {
            foreach ($this->items as $key => $item) {
                if (!\is_string($item)) {
                    throw new \InvalidArgumentException(
                        sprintf('Expect string. Check %s item', $key)
                    );
                }
            }
            $this->validated = true;
        }
        return $this->items;
    }


    /**
     * @param StringModifyInterface $modifier
     * @return StringCollection
     * @throws \Exception
     */
    final public function map(StringModifyInterface $modifier): StringCollection
    {
        $items = [];
        foreach ($this->all() as $item) {
            $items[] = $modifier->modify($item);
        }
        return new StringCollection($items);
    }


    /**
     * @param StringFilterInterface $filter
     * @return StringCollection
     * @throws \Exception
     */
    final public function filter(StringFilterInterface $filter): StringCollection
    {
        $items = [];
        foreach ($this->all() as $item) {
            if ($filter->valid($item)) {
                $items[] = $item;
            }
        }
        return new StringCollection($items);
    }


    /**
     * @param string $regexp
     * @param string $to
     * @return StringCollection
     * @throws \Exception
     */
    final public function replace(string $regexp, string $to): StringCollection
    {
        $result = [];
        foreach ($this->all() as $index => $item) {
            $result[] = preg_replace($regexp, $to, $item);
        }
        return new StringCollection($result);
    }


    /**
     * @param string $regexp
     * @param int $index
     * @return StringCollection
     * @throws \Exception
     */
    final public function match(string $regexp, int $index = 1): StringCollection
    {
        $result = [];
        foreach ($this->all() as $string) {
            preg_match_all($regexp, $string, $matchedData);
            if (isset($matchedData[$index])) {
                foreach ((array)$matchedData[$index] as $matchedString) {
                    $result[] = $matchedString;
                }
            }
        }
        return new StringCollection($result);
    }


    /**
     * @param string $regexp
     * @return StringCollection
     * @throws \Exception
     */
    final public function split(string $regexp): StringCollection
    {
        $items = [];
        foreach ($this->all() as $item) {
            $data = preg_split($regexp, $item);
            foreach ($data as $string) {
                $items[] = $string;
            }
        }
        return new StringCollection($items);
    }


    /**
     * @return StringCollection
     * @throws \Exception
     */
    final public function unique(): StringCollection
    {
        return new StringCollection(array_unique($this->all()));
    }


    /**
     * @param StringCollection $collection
     * @return StringCollection
     * @throws \Exception
     */
    final public function merge(StringCollection $collection): StringCollection
    {
        return new StringCollection(array_merge($this->all(), $collection->all()));
    }


    /**
     * @param string $item
     * @return StringCollection
     * @throws \Exception
     */
    final public function add(string $item): StringCollection
    {
        $items = $this->all();
        $items[] = $item;
        return new StringCollection($items);
    }


    /**
     * @param int $index
     * @return null|string
     * @throws \Exception
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
     * @return string[]|\ArrayIterator
     * @throws \Exception
     */
    final public function getIterator()
    {
        return new \ArrayIterator($this->all());
    }
}
