<?php
namespace ClassKernel\Base\Object\Interfaces;

interface ArrayAccess
{
    public function setMediator($mediator);

    /**
     * check that data for given key exists
     *
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset);

    /**
     * return data for given key
     *
     * @param string $offset
     * @return mixed
     */
    public function offsetGet($offset);

    /**
     * set data for given key
     *
     * @param string|null $offset
     * @param mixed $value
     * @return $this
     */
    public function offsetSet($offset, $value);

    /**
     * remove data for given key
     *
     * @param string $offset
     * @return $this
     */
    public function offsetUnset($offset);

    /**
     * return the current element in an array
     * handle data preparation
     *
     * @return mixed
     */
    public function current();

    /**
     * return the current element in an array
     *
     * @return mixed
     */
    public function key();

    /**
     * advance the internal array pointer of an array
     * handle data preparation
     *
     * @return mixed
     */
    public function next();

    /**
     * rewind the position of a file pointer
     *
     * @return mixed
     */
    public function rewind();

    /**
     * checks if current position is valid
     *
     * @return bool
     */
    public function valid();
}
