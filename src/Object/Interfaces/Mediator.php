<?php
namespace ClassKernel\Base\Object\Interfaces;

interface Mediator
{
    /**
     * @return \ClassKernel\Base\BlueObject
     */
    public function getBlueObject();

    public function getDependency($dependency);

    public function setBlueObject($blueObject);

    public function getDataChanged();

    public function setDataChanged($changed);

    public function getData();

    public function setData(array $data);

    public function getOriginalData();

    public function setOriginalData($data);

    public function getDependencies();

    public function setDependencies($dependencies);

    public function hasErrors();

    public function setHasErrors();

    public function clearHasErrors();

    public function getErrors();

    public function getOption($option = null);

    public function setOption($option, $value);
}
