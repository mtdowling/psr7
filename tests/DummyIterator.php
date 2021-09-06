<?php

namespace GuzzleHttp\Tests\Psr7;

class DummyIterator implements \Iterator
{
    private $key;

    public function __construct()
    {
        $this->key = 0;
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        return 'a';
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->key;
    }

    #[\ReturnTypeWillChange]
    public function next()
    {
        return $this->key++;
    }

    #[\ReturnTypeWillChange]
    public function rewind()
    {
    }

    #[\ReturnTypeWillChange]
    public function valid()
    {
        return true;
    }
}
