<?php
/**
 * create basically object to store data or models and allows to easily access to object
 *
 * @package     BlueContainer
 * @subpackage  Data
 * @author      Michał Adamiak    <chajr@bluetree.pl>
 * @copyright   bluetree-service
 * @link https://github.com/bluetree-service/container/wiki/ContainerObject Object class documentation
 */
namespace BlueContainer;

use Serializable;
use ArrayAccess;
use Iterator;

class Container implements Serializable, ArrayAccess, Iterator
{
    use ContainerObject;
}
