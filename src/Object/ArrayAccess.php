<?php
namespace ClassKernel\Base\Object;

use ClassKernel\Base\Object\Interfaces;
use ArrayAccess as SplArrayAccess;

class ArrayAccess implements Interfaces\ArrayAccess, Interfaces\Common, SplArrayAccess, \Iterator
{
    /**
     * for array access numeric keys, store last used numeric index
     * used only in case when object is used as array
     *
     * @var int
     */
    protected $_integerKeysCounter = 0;

    /**
     * @var \ClassKernel\Base\Object\Interfaces\Mediator
     */
    protected $_mediator;

    /**
     * set current mediator object
     *
     * @param \ClassKernel\Base\Object\Interfaces\Mediator $mediator
     * @return $this
     */
    public function setMediator($mediator)
    {
        $this->_mediator = $mediator;
        return $this;
    }

    /**
     * check that data for given key exists
     *
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->_mediator->getBlueObject()->has($offset);
    }

    /**
     * return data for given key
     *
     * @param string $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->_mediator->getBlueObject()->toArray($offset);
    }

    /**
     * set data for given key
     *
     * @param string|null $offset
     * @param mixed $value
     * @return $this
     */
    public function offsetSet($offset, $value)
    {
        /** @var Original $original */
        $original = $this->_mediator->getOption('original');

        if (is_null($offset)) {
            $offset = $original->integerToStringKey($this->_integerKeysCounter++);
        }

        $original->putData($offset, $value);
        return $this;
    }

    /**
     * remove data for given key
     *
     * @param string $offset
     * @return $this
     */
    public function offsetUnset($offset)
    {
        $this->_mediator->getBlueObject()->destroy($offset);
        return $this;
    }

    /**
     * return the current element in an array
     * handle data preparation
     *
     * @return mixed
     */
    public function current()
    {
        current($this->_mediator->getData());
        return $this->_mediator->getBlueObject()->toArray($this->key());
    }

    /**
     * return the current element in an array
     *
     * @return mixed
     */
    public function key()
    {
        return key($this->_mediator->getData());
    }

    /**
     * advance the internal array pointer of an array
     * handle data preparation
     *
     * @return mixed
     */
    public function next()
    {
        next($this->_mediator->getData());
        return $this->_mediator->getBlueObject()->toArray($this->key());
    }

    /**
     * rewind the position of a file pointer
     *
     * @return mixed
     */
    public function rewind()
    {
        return reset($this->_mediator->getData());
    }

    /**
     * checks if current position is valid
     *
     * @return bool
     */
    public function valid()
    {
        return key($this->_mediator->getData()) !== null;
    }
}
