<?php

namespace ClassKernel\Base\Object;

use ClassKernel\Base\Object\Interfaces;
use ClassKernel\Base\Object\Interfaces\Common;
use Exception;
use ClassKernel\Base\Register;

class Mediator implements Interfaces\Mediator
{
    /**
     * @var \ClassKernel\Base\BlueObject
     */
    protected $_blueObject;

    /**
     * @var array
     */
    protected $_dependencies = [];

    /**
     * @var array
     */
    protected $_data = [];

    /**
     * @var array
     */
    protected $_originalData = [];

    /**
     * @var boolean
     */
    protected $_dataChanged = false;

    /**
     * if there was some errors in object, that variable will be set on true
     *
     * @var bool
     */
    protected $_hasErrors = false;

    /**
     * will contain list of all errors that was occurred in object
     *
     * 0 => ['error_key' => 'error information']
     *
     * @var array
     */
    protected $_errorsList = [];

    /**
     * store all new added data keys, to remove them when in eg. restore original data
     * @var array
     */
    protected $_newKeys = [];

    /**
     * contains all object for dependency injection
     *
     * @var array
     */
    protected $_dependentObjects = [];

    /**
     * list of dependent object interfaces
     *
     * @var array
     */
    protected $_dependencyInterfaces = [];

    /**
     * default constructor options
     *
     * @var array
     */
    protected $_options = [
        'data'                  => null,
        'type'                  => null,
        'validation'            => [],
        'preparation'           => [],
        'integer_key_prefix'    => 'integer_key_',
        'ini_section'           => false,
        'dependencies'          => [
            'array_access'  => ['ClassKernel\Base\Object\ArrayAccess', []],
            'call'          => ['ClassKernel\Base\Object\Call', []],
            'error'         => ['ClassKernel\Base\Object\Error', []],
            'export'        => ['ClassKernel\Base\Object\Export', []],
            'import'        => ['ClassKernel\Base\Object\Import', []],
            'magic'         => ['ClassKernel\Base\Object\Magic', []],
            'original'      => ['ClassKernel\Base\Object\Original', []],
            'preparation'   => ['ClassKernel\Base\Object\Preparation', []],
            'validation'    => ['ClassKernel\Base\Object\Validation', []],
            'xml'           => ['ClassKernel\Base\Object\Xml', []],
            'std'           => ['ClassKernel\Base\Object\Std', []],
        ]
    ];

    public function __construct(array $config)
    {
        $this->_options                 = array_merge($this->_options, $config[0]);
        $this->_dependencyInterfaces    = $config[1];
        $this->_data                    = $config[0]['data'];
    }

    public function getBlueObject()
    {
        return $this->_blueObject;
    }

    public function setBlueObject($blueObject)
    {
        $this->_blueObject = $blueObject;
        return $this;
    }

    public function getDataChanged()
    {
        return $this->_dataChanged;
    }

    public function setDataChanged($changed)
    {
        $this->_dataChanged = $changed;
        return $this;
    }

    public function getData()
    {
        return $this->_data;
    }

    public function setData(array $data)
    {
        $this->_data = $data;
        return $this;
    }

    public function getOriginalData()
    {
        return $this->_data;
    }

    public function setOriginalData($data)
    {
        $this->_data = $data;
        return $this;
    }

    public function getDependencies()
    {
        return $this->_dependencies;
    }

    public function setDependencies($dependencies)
    {
        $this->_dependencies = $dependencies;
        return $this;
    }

    public function hasErrors()
    {
        return $this->_hasErrors;
    }

    public function setHasErrors()
    {
        $this->_hasErrors = true;
        return $this;
    }

    public function clearHasErrors()
    {
        $this->_hasErrors = false;
        return $this;
    }

    public function getErrors()
    {
        $this->setHasErrors();
        return $this->_hasErrors = true;
    }

    public function getOption($option = null)
    {
        if ($option) {
            return $this->_options[$option];
        }
        return $this->_options;
    }

    public function setOption($option, $value)
    {
        $this->_options[$option] = $value;

        return $this;
    }

    /**
     * create exception message and set it in object
     *
     * @param Exception $exception
     * @return $this
     */
    public function addException(Exception $exception)
    {
        $this->setHasErrors();
        $this->_errorsList[$exception->getCode()] = [
            'message'   => $exception->getMessage(),
            'line'      => $exception->getLine(),
            'file'      => $exception->getFile(),
            'trace'     => $exception->getTraceAsString(),
        ];

        return $this;
    }

    /**
     * add error for BlueObject
     *
     * @param string $error
     * @param null|string $key
     * @return $this
     */
    public function addError($error, $key = null)
    {
        $this->setHasErrors();

        if ($key) {
            $this->_errorsList[$key] = $error;
        } else {
            $this->_errorsList[] = $error;
        }

        return $this;
    }

    /**
     * allow to call object with create new instance if is required
     *
     * @param string $dependency
     * @return mixed|false
     */
    public function getDependency($dependency)
    {
        if (!array_key_exists($dependency, $this->_dependentObjects)) {
            $object = $this->_options['dependencies'][$dependency][0];

            try {
                switch (true) {
                    case is_string($object):
                        /** @var \Zend\Di\Di $di */
                        $di = Register::getObject('Zend\Di\Di');
                        $di->instanceManager()->setParameters(
                            $object,
                            $this->_options['dependencies'][$dependency][1]
                        );

                        $object = $di->get($object);
                        break;

                    case is_object($object):
                        break;

                    default:
                        throw new \LogicException('Unknown dependency type.');
                        break;
                }

                $instance = $this->_dependencyInterfaces[$dependency];
                if (!$object instanceof $instance) {
                    throw new \LogicException(
                        'Invalid interface for dependency. Should be: ' . $instance
                    );
                }
                if (!$object instanceof Common) {
                    throw new \LogicException(
                        'Invalid interface for dependency. Should be: ClassKernel\Base\Object\Interfaces\Common'
                    );
                }

                $object->setMediator($this);
                $this->_dependentObjects[$dependency] = $object;
            } catch (Exception $e) {
                $this->_hasErrors = true;
                $this->addException($e);

                return false;
            }
        }

        return $this->_dependentObjects[$dependency];
    }
}
