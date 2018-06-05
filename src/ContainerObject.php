<?php
/**
 * trait object to store data or models and allows to easily access to object
 *
 * @package     BlueContainer
 * @subpackage  Base
 * @author      Michał Adamiak    <chajr@bluetree.pl>
 * @copyright   bluetree-service
 * @link https://github.com/bluetree-service/container/wiki/ContainerObject ContainerObject class documentation
 */
namespace BlueContainer;

use BlueData\Data\Xml;
use stdClass;
use DOMException;
use DOMElement;
use Zend\Serializer\Serializer;
use Zend\Serializer\Exception\ExceptionInterface;
use Exception;
use Closure;
use ReflectionFunction;

trait ContainerObject
{
    /**
     * text value for skipped object
     * 
     * @var string
     */
    protected $_skippedObject = ': {;skipped_object;}';

    /**
     * contains name of undefined data
     * 
     * @var string
     */
    protected $_defaultDataName = 'default';

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
     * array with main object data
     * @var array
     */
    protected $_DATA = [];

    /**
     * keeps data before changes (set only if some data in $_DATA was changed)
     * @var
     */
    protected $_originalDATA = [];

    /**
     * store all new added data keys, to remove them when in eg. restore original data
     * @var array
     */
    protected $_newKeys = [];

    /**
     * @var array
     */
    protected static $_cacheKeys = [];

    /**
     * @var boolean
     */
    protected $_dataChanged = false;

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
    ];

    /**
     * name of key prefix for xml node
     * if array key was integer
     *
     * @var string
     */
    protected $_integerKeyPrefix;

    /**
     * separator for data to return as string
     * 
     * @var string
     */
    protected $_separator = ', ';

    /**
     * store list of rules to validate data
     * keys are searched using regular expression
     * 
     * @var array
     */
    protected $_validationRules = [];

    /**
     * list of callbacks to prepare data before insert into object
     * 
     * @var array
     */
    protected $_dataPreparationCallbacks = [];

    /**
     * list of callbacks to prepare data before return from object
     * 
     * @var array
     */
    protected $_dataRetrieveCallbacks = [];

    /**
     * for array access numeric keys, store last used numeric index
     * used only in case when object is used as array
     * 
     * @var int
     */
    protected $_integerKeysCounter = 0;

    /**
     * allow to turn off/on data validation
     * 
     * @var bool
     */
    protected $_validationOn = true;

    /**
     * allow to turn off/on data preparation
     * 
     * @var bool
     */
    protected $_getPreparationOn = true;

    /**
     * allow to turn off/on data retrieve
     * 
     * @var bool
     */
    protected $_setPreparationOn = true;

    /**
     * inform append* methods that data was set in object creation
     * 
     * @var bool
     */
    protected $_objectCreation = true;

    /**
     * csv variable delimiter
     * 
     * @var string
     */
    protected $_csvDelimiter = ';';

    /**
     * csv enclosure
     * 
     * @var string
     */
    protected $_csvEnclosure = '"';

    /**
     * csv escape character
     * 
     * @var string
     */
    protected $_csvEscape = '\\';

    /**
     * csv line delimiter (single object element)
     * 
     * @var string
     */
    protected $_csvLineDelimiter = "\n";

    /**
     * allow to process [section] as array key
     * 
     * @var bool
     */
    protected $_processIniSection;

    /**
     * create new Blue Object, optionally with some data
     * there are some types we can give to convert data to Blue Object
     * like: json, xml, serialized or stdClass default is array
     *
     * @param array|null $options
     */
    public function __construct($options = [])
    {
        $this->_options             = array_merge($this->_options, $options);
        $data                       = $this->_options['data'];
        $this->_integerKeyPrefix    = $this->_options['integer_key_prefix'];
        $this->_processIniSection   = $this->_options['ini_section'];

        $this->_beforeInitializeObject($data);
        $this->putValidationRule($this->_options['validation'])
            ->putPreparationCallback($this->_options['preparation'])
            ->initializeObject($data);

        switch (true) {
            case $this->_options['type'] === 'json':
                $this->appendJson($data);
                break;

            case $this->_options['type'] === 'xml':
                $this->appendXml($data);
                break;

            case $this->_options['type'] === 'simple_xml':
                $this->appendSimpleXml($data);
                break;

            case $this->_options['type'] === 'serialized':
                $this->appendSerialized($data);
                break;

            case $this->_options['type'] === 'csv':
                $this->appendCsv($data);
                break;

            case $this->_options['type'] === 'ini':
                $this->appendIni($data);
                break;

            case $data instanceof stdClass:
                $this->appendStdClass($data);
                break;

            case is_array($data):
                $this->appendArray($data);
                break;

            default:
                break;
        }

        $this->afterInitializeObject();
        $this->_objectCreation = false;
    }

    /**
     * return from DATA value for given object attribute
     *
     * @param string $key
     * @return mixed
     */

    public function __get($key)
    {
        $key = $this->_convertKeyNames($key);
        return $this->toArray($key);
    }

    /**
     * save into DATA value given as object attribute
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        $key = $this->_convertKeyNames($key);
        $this->_putData($key, $value);
    }

    /**
     * check that variable exists in DATA table
     *
     * @param string $key
     * @return bool
     */
    public function __isset($key)
    {
        $key = $this->_convertKeyNames($key);
        return $this->has($key);
    }

    /**
     * remove given key from DATA
     *
     * @param $key
     */
    public function __unset($key)
    {
        $key = $this->_convertKeyNames($key);
        $this->destroy($key);
    }

    /**
     * allow to access DATA keys by using special methods
     * like getSomeData() will return $_DATA['some_data'] value or
     * setSomeData('val') will create $_DATA['some_data'] key with 'val' value
     * all magic methods handle data put and return preparation
     *
     * @param string $method
     * @param array $arguments
     * @return $this|bool|mixed
     */
    public function __call($method, $arguments)
    {
        switch (true) {
            case substr($method, 0, 3) === 'get':
                $key = $this->_convertKeyNames(substr($method, 3));
                return $this->get($key);

            case substr($method, 0, 3) === 'set':
                $key = $this->_convertKeyNames(substr($method, 3));
                return $this->set($key, $arguments[0]);

            case substr($method, 0, 3) === 'has':
                $key = $this->_convertKeyNames(substr($method, 3));
                return $this->has($key);

            case substr($method, 0, 3) === 'not':
                $key = $this->_convertKeyNames(substr($method, 3));
                $val = $this->get($key);
                return $this->_compareData($arguments[0], $key, $val, '!==');

            case substr($method, 0, 5) === 'unset' || substr($method, 0, 5) === 'destroy':
                $key = $this->_convertKeyNames(substr($method, 5));
                return $this->destroy($key);

            case substr($method, 0, 5) === 'clear':
                $key = $this->_convertKeyNames(substr($method, 5));
                return $this->clear($key);

            case substr($method, 0, 7) === 'restore':
                $key = $this->_convertKeyNames(substr($method, 7));
                return $this->restore($key);

            case substr($method, 0, 2) === 'is':
                $key = $this->_convertKeyNames(substr($method, 2));
                $val = $this->get($key);
                return $this->_compareData($arguments[0], $key, $val, '===');

            default:
                $this->_errorsList['wrong_method'] = get_class($this) . ' - ' . $method;
                $this->_hasErrors = true;
                return false;
        }
    }

    /**
     * compare given data with possibility to use callable functions to check data
     * 
     * @param mixed $dataToCheck
     * @param string $key
     * @param mixed $originalData
     * @param string $comparator
     * @return bool
     */
    protected function _compareData($dataToCheck, $key, $originalData, $comparator)
    {
        if (is_callable($dataToCheck)) {
            return call_user_func_array($dataToCheck, [$key, $originalData, $this]);
        }

        return $this->_comparator($originalData, $dataToCheck, $comparator);
    }

    /**
     * return object data as string representation
     *
     * @return string
     */
    public function __toString()
    {
        $this->_prepareData();
        return implode($this->_separator, $this->toArray());
    }

    /**
     * return boolean information that object has some error
     *
     * @return bool
     */
    public function checkErrors()
    {
        return $this->_hasErrors;
    }

    /**
     * return single error by key, ora list of all errors
     *
     * @param string $key
     * @return mixed
     */
    public function returnObjectError($key = null)
    {
        return $this->_genericReturn($key, 'error_list');
    }

    /**
     * remove single error, or all object errors
     *
     * @param string|null $key
     * @return Object
     */
    public function removeObjectError($key = null)
    {
        $this->_genericDestroy($key, 'error_list');
        $this->_hasErrors = false;
        return $this;
    }

    /**
     * return serialized object data
     *
     * @param boolean $skipObjects
     * @return string
     */
    public function serialize($skipObjects = false)
    {
        $this->_prepareData();
        $temporaryData  = $this->toArray();
        $data           = '';

        if ($skipObjects) {
            $temporaryData = $this->traveler(
                'self::_skipObject',
                null,
                $temporaryData,
                true
            );
        }

        try {
            $data = Serializer::serialize($temporaryData);
        } catch (ExceptionInterface $exception) {
            $this->_addException($exception);
        }

        return $data;
    }

    /**
     * allow to set data from serialized string with keep original data
     * 
     * @param string $string
     * @return $this
     */
    public function unserialize($string)
    {
        return $this->appendSerialized($string);
    }

    /**
     * return data for given key if exist in object, or all object data
     *
     * @param null|string $key
     * @return mixed
     * @deprecated
     */
    public function getData($key = null)
    {
        return $this->toArray($key);
    }

    /**
     * return data for given key if exist in object
     * or null if key was not found
     * 
     * @param string|null $key
     * @return mixed
     */
    public function get($key = null)
    {
        $this->_prepareData($key);
        $data = null;

        if (is_null($key)) {
            $data = $this->_DATA;
        } elseif (array_key_exists($key, $this->_DATA)) {
            $data = $this->_DATA[$key];
        }

        if ($this->_getPreparationOn) {
            return $this->_dataPreparation($key, $data, $this->_dataRetrieveCallbacks);
        }
        return $data;
    }

    /**
     * set some data in object
     * can give pair key=>value or array of keys
     *
     * @param string|array $key
     * @param mixed $data
     * @return $this
     * @deprecated
     */
    public function setData($key, $data = null)
    {
        return $this->set($key, $data);
    }

    /**
     * set some data in object
     * can give pair key=>value or array of keys
     * 
     * @param string|array $key
     * @param mixed $data
     * @return $this
     */
    public function set($key, $data = null)
    {
        if (is_array($key)) {
            $this->appendArray($key);
        } else {
            $this->appendData($key, $data);
        }

        return $this;
    }

    /**
     * return original data for key, before it was changed
     * that method don't handle return data preparation
     *
     * @param null|string $key
     * @return mixed
     */
    public function returnOriginalData($key = null)
    {
        $this->_prepareData($key);

        $mergedData = array_merge($this->_DATA, $this->_originalDATA);
        $data       = $this->_removeNewKeys($mergedData);

        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        return null;
    }

    /**
     * check if data with given key exist in object, or object has some data
     * if key wast given
     *
     * @param null|string $key
     * @return bool
     * @deprecated
     */
    public function hasData($key = null)
    {
        return $this->has($key);
    }

    /**
     * check if data with given key exist in object, or object has some data
     * 
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        if (array_key_exists($key, $this->_DATA)) {
            return true;
        }

        return false;
    }

    /**
     * check that given data and data in object with given operator
     * use the same operator like in PHP (eg ===, !=, <, ...)
     * possibility to compare with origin data
     * that method don't handle return data preparation
     *
     * if return null, comparator symbol was wrong
     *
     * @param mixed $dataToCheck
     * @param array|string|\Closure $operator
     * @param string|null $key
     * @param boolean $origin
     * @return bool|null
     */
    public function compareData($dataToCheck, $key = null, $operator = '===', $origin = null)
    {
        if ($origin) {
            $mergedData = array_merge($this->_DATA, $this->_originalDATA);
            $data       = $this->_removeNewKeys($mergedData);
        } else {
            $data = $this->_DATA;
        }

        if ($dataToCheck instanceof Container) {
            $dataToCheck = $dataToCheck->toArray();
        }

        if (is_callable($operator)) {
            return call_user_func_array($operator, [$key, $dataToCheck, $data, $this]);
        }

        switch (true) {
            case is_null($key):
                return $this->_comparator($dataToCheck, $data, $operator);
            // no break, always will return boolean value

            case array_key_exists($key, $data):
                return $this->_comparator($dataToCheck, $data[$key], $operator);
            // no break, always will return boolean value

            default:
                return false;
            // no break, always will return boolean value
        }
    }

    /**
     * allow to compare data with given operator
     * 
     * @param mixed $dataOrigin
     * @param mixed $dataCheck
     * @param string $operator
     * @return bool|null
     */
    protected function _comparator($dataOrigin, $dataCheck, $operator)
    {
        switch ($operator) {
            case '===':
                return $dataOrigin === $dataCheck;
            // no break, always will return boolean value

            case '!==':
                return $dataOrigin !== $dataCheck;
            // no break, always will return boolean value

            case '==':
                return $dataOrigin == $dataCheck;
            // no break, always will return boolean value

            case '!=':
            case '<>':
                return $dataOrigin != $dataCheck;
            // no break, always will return boolean value

            case '<':
                return $dataOrigin < $dataCheck;
            // no break, always will return boolean value

            case '>':
                return $dataOrigin > $dataCheck;
            // no break, always will return boolean value

            case '<=':
                return $dataOrigin <= $dataCheck;
            // no break, always will return boolean value

            case '>=':
                return $dataOrigin >= $dataCheck;
            // no break, always will return boolean value

            default:
                return null;
            // no break, always will return boolean value
        }
    }

    /**
     * destroy key entry in object data, or whole keys
     * automatically set data to original array
     *
     * @param string|null $key
     * @return $this
     * @deprecated
     */
    public function unsetData($key = null)
    {
        return $this->destroy($key);
    }

    /**
     * destroy key entry in object data, or whole keys
     * automatically set data to original array
     * 
     * @param string|null $key
     * @return $this
     */
    public function destroy($key = null)
    {
        if (is_null($key)) {
            $this->_dataChanged  = true;
            $mergedData          = array_merge($this->_DATA, $this->_originalDATA);
            $this->_originalDATA = $this->_removeNewKeys($mergedData);
            $this->_DATA         = [];

        } elseif (array_key_exists($key, $this->_DATA)) {
            $this->_dataChanged = true;

            if (!array_key_exists($key, $this->_originalDATA)
                && !array_key_exists($key, $this->_newKeys)
            ) {
                $this->_originalDATA[$key] = $this->_DATA[$key];
            }

            unset ($this->_DATA[$key]);
        }

        return $this;
    }

    /**
     * set object key data to null
     *
     * @param string $key
     * @return $this
     * @deprecated
     */
    public function clearData($key)
    {
        return $this->clear($key);
    }

    /**
     * set object key data to null
     * 
     * @param string $key
     * @return $this
     */
    public function clear($key)
    {
        $this->_putData($key, null);
        return $this;
    }

    /**
     * replace changed data by original data
     * set data changed to false only if restore whole data
     *
     * @param string|null $key
     * @return $this
     * @deprecated
     */
    public function restoreData($key = null)
    {
        return $this->restore($key);
    }

    /**
     * replace changed data by original data
     * set data changed to false only if restore whole data
     *
     * @param string|null $key
     * @return $this
     */
    public function restore($key = null)
    {
        if (is_null($key)) {
            $mergedData         = array_merge($this->_DATA, $this->_originalDATA);
            $this->_DATA        = $this->_removeNewKeys($mergedData);
            $this->_dataChanged = false;
            $this->_newKeys     = [];
        } else {
            if (array_key_exists($key, $this->_originalDATA)) {
                $this->_DATA[$key] = $this->_originalDATA[$key];
            }
        }

        return $this;
    }

    /**
     * all data stored in object became original data
     *
     * @return $this
     */
    public function replaceDataArrays()
    {
        $this->_originalDATA = [];
        $this->_dataChanged  = false;
        $this->_newKeys      = [];
        return $this;
    }

    /**
     * return object as string
     * each data value separated by coma
     *
     * @param string $separator
     * @return string
     */
    public function toString($separator = null)
    {
        if (!is_null($separator)) {
            $this->_separator = $separator;
        }

        $this->_prepareData();
        return $this->__toString();
    }

    /**
     * return current separator
     * 
     * @return string
     */
    public function returnSeparator()
    {
        return $this->_separator;
    }

    /**
     * allow to change default separator
     * 
     * @param string $separator
     * @return Object
     */
    public function changeSeparator($separator)
    {
        $this->_separator = $separator;
        return $this;
    }

    /**
     * return data as json string
     *
     * @return string
     */
    public function toJson()
    {
        $this->_prepareData();
        return json_encode($this->toArray());
    }

    /**
     * return object data as xml representation
     *
     * @param bool $addCdata
     * @param string|boolean $dtd
     * @param string $version
     * @return string
     */
    public function toXml($addCdata = true, $dtd = false, $version = '1.0')
    {
        $this->_prepareData();

        $xml    = new Xml(['version' => $version]);
        $root   = $xml->createElement('root');
        $xml    = $this->_arrayToXml($this->toArray(), $xml, $addCdata, $root);

        $xml->appendChild($root);

        if ($dtd) {
            $dtd = "<!DOCTYPE root SYSTEM '$dtd'>";
        }

        $xml->formatOutput = true;
        $xmlData = $xml->saveXmlFile(false, true);

        if ($xml->hasErrors()) {
            $this->_hasErrors       = true;
            $this->_errorsList[]    = $xml->getError();
            return false;
        }

        return $dtd . $xmlData;
    }

    /**
     * return object as stdClass
     * 
     * @return stdClass
     */
    public function toStdClass()
    {
        $this->_prepareData();
        $data = new stdClass();

        foreach ($this->toArray() as $key => $val) {
            $data->$key = $val;
        }

        return $data;
    }

    /**
     * return data for given key if exist in object, or all object data
     *
     * @param string|null $key
     * @return mixed
     */
    public function toArray($key = null)
    {
        return $this->get($key);
    }

    /**
     * return information that some data was changed in object
     *
     * @return bool
     */
    public function dataChanged()
    {
        return $this->_dataChanged;
    }

    /**
     * check that data for given key was changed
     *
     * @param string $key
     * @return bool
     */
    public function keyDataChanged($key)
    {
        $data           = $this->toArray($key);
        $originalData   = $this->returnOriginalData($key);

        return $data !== $originalData;
    }

    /**
     * allow to use given method or function for all data inside of object
     *
     * @param array|string|\Closure $function
     * @param mixed $methodAttributes
     * @param mixed $data
     * @param bool $recursive
     * @return array|null
     */
    public function traveler(
        $function,
        $methodAttributes = null,
        $data = null,
        $recursive = false
    ) {
        if (!$data) {
            $data =& $this->_DATA;
        }

        foreach ($data as $key => $value) {
            $isRecursive = is_array($value) && $recursive;

            if ($isRecursive) {
                $data[$key] = $this->_recursiveTraveler($function, $methodAttributes, $value);
            } else {
                $data[$key] = $this->_callUserFunction($function, $key, $value, $methodAttributes);
            }
        }

        return $data;
    }

    /**
     * allow to change some data in multi level arrays
     *
     * @param mixed $methodAttributes
     * @param mixed $data
     * @param array|string|\Closure $function
     * @return mixed
     */
    protected function _recursiveTraveler($function, $methodAttributes, $data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->_recursiveTraveler($function, $methodAttributes, $value);
            } else {
                $data[$key] = $this->_callUserFunction($function, $key, $value, $methodAttributes);
            }
        }

        return $data;
    }

    /**
     * run given function, method or closure on given data
     *
     * @param array|string|\Closure $function
     * @param string $key
     * @param mixed $value
     * @param mixed $attributes
     * @return mixed
     */
    protected function _callUserFunction($function, $key, $value, $attributes)
    {
        if (is_callable($function)) {
            return call_user_func_array($function, [$key, $value, $this, $attributes]);
        }

        return $value;
    }

    /**
     * allow to join two blue objects into one
     *
     * @param \BlueContainer\Container $object
     * @return $this
     */
    public function mergeBlueObject(Container $object)
    {
        $newData = $object->toArray();

        foreach ($newData as $key => $value) {
            $this->appendData($key, $value);
        }

        $this->_dataChanged = true;
        return $this;
    }

    /**
     * remove all new keys from given data
     *
     * @param array $data
     * @return array
     */
    protected function _removeNewKeys(array $data)
    {
        foreach ($this->_newKeys as $key) {
            unset($data[$key]);
        }
        return $data;
    }

    /**
     * clear some data after creating new object with data
     * 
     * @return $this
     */
    protected function _afterAppendDataToNewObject()
    {
        $this->_dataChanged     = false;
        $this->_newKeys         = [];

        return $this;
    }

    /**
     * apply given json data as object data
     *
     * @param string $data
     * @return $this
     */
    public function appendJson($data)
    {
        $jsonData = json_decode($data, true);

        if (is_null($jsonData)) {
            $this->_errorsList['json_decode'] = 'Json cannot be decoded.';
            $this->_hasErrors = true;
            return $this;
        }

        $this->appendArray($jsonData);

        if ($this->_objectCreation) {
            return $this->_afterAppendDataToNewObject();
        }
        return $this;
    }

    /**
     * apply given xml data as object data
     *
     * @param $data string
     * @return $this
     */
    public function appendSimpleXml($data)
    {
        $loadedXml      = simplexml_load_string($data);
        $jsonXml        = json_encode($loadedXml);
        $jsonData       = json_decode($jsonXml, true);

        $this->appendArray($jsonData);

        if ($this->_objectCreation) {
            return $this->_afterAppendDataToNewObject();
        }
        return $this;
    }

    /**
     * apply given xml data as object data
     * also handling attributes
     *
     * @param $data string
     * @return $this
     */
    public function appendXml($data)
    {
        $xml                        = new Xml();
        $xml->preserveWhiteSpace    = false;
        $bool                       = @$xml->loadXML($data);

        if (!$bool) {
            $this->_errorsList['xml_load_error']    = $data;
            $this->_hasErrors                       = true;
            return $this;
        }

        try {
            $temp = $this->_xmlToArray($xml->documentElement);
            $this->appendArray($temp);
        } catch (DOMException $exception) {
            $this->_addException($exception);
        }

        if ($this->_objectCreation) {
            return $this->_afterAppendDataToNewObject();
        }
        return $this;
    }

    /**
     * recurrent function to travel on xml nodes and set their data as object data
     *
     * @param DOMElement $data
     * @return array
     */
    protected function _xmlToArray(DOMElement $data)
    {
        $temporaryData = [];

        /** @var $node DOMElement */
        foreach ($data->childNodes as $node) {
            $nodeName           = $this->_stringToIntegerKey($node->nodeName);
            $nodeData           = [];
            $unSerializedData   = [];

            if ($node->hasAttributes() && $node->getAttribute('serialized_object')) {
                try {
                    $unSerializedData = Serializer::unserialize($node->nodeValue);
                } catch (ExceptionInterface $exception) {
                    $this->_addException($exception);
                }

                $temporaryData[$nodeName] = $unSerializedData;
                continue;
            }

            if ($node->hasAttributes()) {
                foreach ($node->attributes as $key => $value) {
                    $nodeData['@attributes'][$key] = $value->nodeValue;
                }
            }

            if ($node->hasChildNodes()) {
                $childNodesData = [];

                /** @var $childNode DOMElement */
                foreach ($node->childNodes as $childNode) {
                    if ($childNode->nodeType === 1) {
                        $childNodesData = $this->_xmlToArray($node);
                    }
                }

                if (!empty($childNodesData)) {
                    $temporaryData[$nodeName] = $childNodesData;
                    continue;
                }
            }

            if (!empty($nodeData)) {
                $temporaryData[$nodeName] = array_merge(
                    [$node->nodeValue],
                    $nodeData
                );
            } else {
                $temporaryData[$nodeName] = $node->nodeValue;
            }
        }

        return $temporaryData;
    }

    /**
     * remove prefix from integer array key
     *
     * @param string $key
     * @return string|integer
     */
    protected function _stringToIntegerKey($key)
    {
        return str_replace($this->_integerKeyPrefix, '', $key);
    }

    /**
     * return set up integer key prefix value
     * 
     * @return string
     */
    public function returnIntegerKeyPrefix()
    {
        return $this->_integerKeyPrefix;
    }

    /**
     * allow to set array in object or some other value 
     *
     * @param array $arrayData
     * @return $this
     */
    public function appendArray(array $arrayData)
    {
        foreach ($arrayData as $dataKey => $data) {
            $this->_putData($dataKey, $data);
        }

        if ($this->_objectCreation) {
            return $this->_afterAppendDataToNewObject();
        }
        return $this;
    }

    /**
     * allow to set some mixed data type as given key
     *
     * @param array|string $key
     * @param mixed $data
     * @return $this
     */
    public function appendData($key, $data)
    {
        $this->_putData($key, $data);

        if ($this->_objectCreation) {
            return $this->_afterAppendDataToNewObject();
        }
        return $this;
    }

    /**
     * get class variables and set them as data
     *
     * @param stdClass $class
     * @return $this
     */
    public function appendStdClass(stdClass $class)
    {
        $this->appendArray(get_object_vars($class));

        if ($this->_objectCreation) {
            return $this->_afterAppendDataToNewObject();
        }
        return $this;
    }

    /**
     * set data from serialized string as object data
     * if data is an object set one variable where key is an object class name
     *
     * @param mixed $data
     * @return $this
     */
    public function appendSerialized($data)
    {
        try {
            $data = Serializer::unserialize($data);
        } catch (ExceptionInterface $exception) {
            $this->_addException($exception);
        }

        if (is_object($data)) {
            $name = $this->_convertKeyNames(get_class($data));
            $this->appendData($name, $data);
        } elseif (is_array($data)) {
            $this->appendArray($data);
        } else {
            $this->appendData($this->_defaultDataName, $data);
        }

        if ($this->_objectCreation) {
            return $this->_afterAppendDataToNewObject();
        }
        return $this;
    }

    /**
     * allow to set ini data into object
     * 
     * @param string $data
     * @return $this
     */
    public function appendIni($data)
    {
        $array = parse_ini_string($data, $this->_processIniSection, INI_SCANNER_RAW);

        if ($array === false) {
            $this->_hasErrors = true;
            $this->_errorsList[] = 'parse_ini_string';
            return $this;
        }

        $this->appendArray($array);
        return $this;
    }

    /**
     * return information about ini section processing
     * 
     * @return bool
     */
    public function returnProcessIniSection()
    {
        return $this->_processIniSection;
    }

    /**
     * enable or disable ini section processing
     * 
     * @param bool $bool
     * @return $this
     */
    public function processIniSection($bool)
    {
        $this->_processIniSection = $bool;
        return $this;
    }

    /**
     * export object as ini string
     * 
     * @return string
     */
    public function toIni()
    {
        $ini = '';

        foreach ($this->toArray() as $key => $iniRow) {
            $this->_appendIniData($ini, $key, $iniRow);
        }

        return $ini;
    }

    /**
     * append ini data to string
     * 
     * @param string $ini
     * @param string $key
     * @param mixed $iniRow
     */
    protected function _appendIniData(&$ini, $key, $iniRow)
    {
        if ($this->_processIniSection && is_array($iniRow)) {
            $ini .= '[' . $key . ']' . "\n";
            foreach ($iniRow as $rowKey => $rowData) {
                $ini .= $rowKey . ' = ' . $rowData . "\n";
            }
        } else {
            $ini .= $key . ' = ' . $iniRow . "\n";
        }
    }

    /**
     * allow to set csv data into object
     * 
     * @param string $data
     * @return $this
     */
    public function appendCsv($data)
    {
        $counter    = 0;
        $rows       = str_getcsv($data, $this->_csvLineDelimiter);

        foreach ($rows as $row) {
            $rowData = str_getcsv(
                $row,
                $this->_csvDelimiter,
                $this->_csvEnclosure,
                $this->_csvEscape
            );

            $this->_putData($this->_integerKeyPrefix . $counter, $rowData);

            $counter++;
        }

        if ($this->_objectCreation) {
            return $this->_afterAppendDataToNewObject();
        }

        return $this;
    }

    /**
     * @return string
     */
    public function returnCsvDelimiter()
    {
        return $this->_csvDelimiter;
    }

    /**
     * @return string
     */
    public function returnCsvEnclosure()
    {
        return $this->_csvEnclosure;
    }

    /**
     * @return string
     */
    public function returnCsvEscape()
    {
        return $this->_csvEscape;
    }

    /**
     * @return string
     */
    public function returnCsvLineDelimiter()
    {
        return $this->_csvLineDelimiter;
    }

    /**
     * change delimiter for csv row data (give only one character)
     * 
     * @param string $char
     * @return $this
     */
    public function changeCsvDelimiter($char)
    {
        $this->_csvDelimiter = $char;
        return $this;
    }

    /**
     * change enclosure for csv row data (give only one character)
     * 
     * @param string $char
     * @return $this
     */
    public function changeCsvEnclosure($char)
    {
        $this->_csvEnclosure = $char;
        return $this;
    }

    /**
     * change data escape for csv row data (give only one character)
     *
     * @param string $char
     * @return $this
     */
    public function changeCsvEscape($char)
    {
        $this->_csvEscape = $char;
        return $this;
    }

    /**
     * change data row delimiter (give only one character)
     *
     * @param string $char
     * @return $this
     */
    public function changeCsvLineDelimiter($char)
    {
        $this->_csvLineDelimiter = $char;
        return $this;
    }

    /**
     * export object as csv data
     * 
     * @return string
     */
    public function toCsv()
    {
        $csv = '';

        foreach ($this->toArray() as $csvRow) {
            if (is_array($csvRow)) {
                $data = implode($this->_csvDelimiter, $csvRow);
            } else {
                $data = $csvRow;
            }

            $csv .= $data . $this->_csvLineDelimiter;
        }

        return rtrim($csv, $this->_csvLineDelimiter);
    }

    /**
     * check that given data for key is valid and set in object if don't exist or is different
     *
     * @param string $key
     * @param mixed $data
     * @return $this
     */
    protected function _putData($key, $data)
    {
        $bool = $this->_validateDataKey($key, $data);
        if (!$bool) {
            return $this;
        }

        $hasData = $this->has($key);
        if ($this->_setPreparationOn) {
            $data = $this->_dataPreparation(
                $key,
                $data,
                $this->_dataPreparationCallbacks
            );
        }

        if (!$hasData
            || ($hasData && $this->_comparator($this->_DATA[$key], $data, '!=='))
        ) {
            $this->_changeData($key, $data, $hasData);
        }

        return $this;
    }

    /**
     * insert single key=>value pair into object, with key conversion
     * and set _dataChanged to true
     * also set original data for given key in $this->_originalDATA
     * 
     * @param string $key
     * @param mixed $data
     * @param bool $hasData
     * @return $this
     */
    protected function _changeData($key, $data, $hasData)
    {
        if (!array_key_exists($key, $this->_originalDATA)
            && $hasData
            && !array_key_exists($key, $this->_newKeys)
        ) {
            $this->_originalDATA[$key] = $this->_DATA[$key];
        } else {
            $this->_newKeys[$key] = $key;
        }

        $this->_dataChanged = true;
        $this->_DATA[$key]  = $data;

        return $this;
    }

    /**
     * search validation rule for given key and check data
     * 
     * @param string $key
     * @param mixed $data
     * @return bool
     */
    protected function _validateDataKey($key, $data)
    {
        $dataOkFlag = true;

        if (!$this->_validationOn) {
            return $dataOkFlag;
        }

        foreach ($this->_validationRules as $ruleKey => $ruleValue) {
            if (!preg_match($ruleKey, $key)) {
                continue;
            }

            $bool = $this->_validateData($ruleValue, $key, $data);
            if (!$bool) {
                $dataOkFlag = false;
            }
        }

        return $dataOkFlag;
    }

    /**
     * check data with given rule and set error information
     * allow to use method or function (must return true or false)
     * 
     * @param string|array|string|\Closure $rule
     * @param string $key
     * @param mixed $data
     * @return bool
     */
    protected function _validateData($rule, $key, $data)
    {
        if (
            (is_callable($rule) && call_user_func_array($rule, [$key, $data, $this]))
            || @preg_match($rule, $data)
        ) {
            return true;
        }

        if ($rule instanceof Closure) {
            $reflection = new ReflectionFunction($rule);
            $rule       = $reflection->__toString();
        }

        $this->_errorsList[] = [
            'message'   => 'validation_mismatch',
            'key'       => $key,
            'data'      => $data,
            'rule'      => $rule,
        ];
        $this->_hasErrors = true;

        return false;
    }

    /**
     * convert given object data key (given as came case method)
     * to proper construction
     *
     * @param string $key
     * @return string
     */
    protected function _convertKeyNames($key)
    {
        if (array_key_exists($key, self::$_cacheKeys)) {
            return self::$_cacheKeys[$key];
        }

        $convertedKey = strtolower(
            preg_replace('/(.)([A-Z0-9])/', "$1_$2", $key)
        );
        self::$_cacheKeys[$key] = $convertedKey;
        return $convertedKey;
    }

    /**
     * recursive method to create structure xml structure of object DATA
     *
     * @param $data
     * @param Xml $xml
     * @param boolean $addCdata
     * @param Xml|DOMElement $parent
     * @return Xml
     */
    protected function _arrayToXml($data, Xml $xml, $addCdata, $parent)
    {
        foreach ($data as $key => $value) {
            $key        = str_replace(' ', '_', $key);
            $attributes = [];
            $data       = '';

            if (is_object($value)) {
                try {
                    $data = Serializer::serialize($value);
                } catch (ExceptionInterface $exception) {
                    $this->_addException($exception);
                }

                $value = [
                    '@attributes' => ['serialized_object' => true],
                    $data
                ];
            }

            try {
                $isArray = is_array($value);

                if ($isArray && array_key_exists('@attributes', $value)) {
                    $attributes = $value['@attributes'];
                    unset ($value['@attributes']);
                }

                if ($isArray) {
                    $parent = $this->_convertArrayDataToXml(
                        $value,
                        $addCdata,
                        $xml,
                        $key,
                        $parent,
                        $attributes
                    );
                    continue;
                }

                $element = $this->_appendDataToNode($addCdata, $xml, $key, $value);
                $parent->appendChild($element);

            } catch (DOMException $exception) {
                $this->_addException($exception);
            }
        }

        return $xml;
    }

    /**
     * convert array DATA value to xml format and return as xml object
     *
     * @param array|string $value
     * @param string $addCdata
     * @param Xml $xml
     * @param string|integer $key
     * @param DOMElement $parent
     * @param array $attributes
     * @return DOMElement
     */
    protected function _convertArrayDataToXml(
        $value,
        $addCdata,
        Xml $xml,
        $key,
        $parent,
        array $attributes
    ) {
        $count      = count($value) === 1;
        $isNotEmpty = !empty($attributes);
        $exist      = array_key_exists(0, $value);

        if ($count && $isNotEmpty && $exist) {
            $children = $this->_appendDataToNode(
                $addCdata,
                $xml,
                $key,
                $value[0]
            );
        } else {
            $children = $xml->createElement(
                $this->_integerToStringKey($key)
            );
            $this->_arrayToXml($value, $xml, $addCdata, $children);
        }
        $parent->appendChild($children);

        foreach ($attributes as $attributeKey => $attributeValue) {
            $children->setAttribute($attributeKey, $attributeValue);
        }

        return $parent;
    }
    /**
     * append data to node
     *
     * @param string $addCdata
     * @param Xml $xml
     * @param string|integer $key
     * @param string $value
     * @return DOMElement
     */
    protected function _appendDataToNode($addCdata, Xml $xml, $key, $value)
    {
        if ($addCdata) {
            $cdata      = $xml->createCDATASection($value);
            $element    = $xml->createElement(
                $this->_integerToStringKey($key)
            );
            $element->appendChild($cdata);
        } else {
            $element = $xml->createElement(
                $this->_integerToStringKey($key),
                $value
            );
        }

        return $element;
    }

    /**
     * if array key is number, convert it to string with set up _integerKeyPrefix
     *
     * @param string|integer $key
     * @return string
     */
    protected function _integerToStringKey($key)
    {
        if (is_numeric($key)) {
            $key = $this->_integerKeyPrefix . $key;
        }

        return $key;
    }

    /**
     * replace object by string
     *
     * @param string $key
     * @param mixed $value
     * @return string
     */
    protected function _skipObject($key, $value)
    {
        if (is_object($value)) {
            return $key . $this->_skippedObject;
        }

        return $value;
    }

    /**
     * set regular expression for key find and validate data
     * 
     * @param string $ruleKey
     * @param string $ruleValue
     * @return $this
     */
    public function putValidationRule($ruleKey, $ruleValue = null)
    {
        return $this->_genericPut($ruleKey, $ruleValue, 'validation');
    }

    /**
     * remove validation rule from list
     * 
     * @param string|null $key
     * @return $this
     */
    public function removeValidationRule($key = null)
    {
        return $this->_genericDestroy($key, 'validation');
    }

    /**
     * return validation rule or all rules set in object
     * 
     * @param null|string $rule
     * @return mixed
     */
    public function returnValidationRule($rule = null)
    {
        return $this->_genericReturn($rule, 'validation');
    }

    /**
     * common put data method for class data lists
     * 
     * @param string|array $key
     * @param mixed $value
     * @param string $type
     * @return $this
     */
    protected function _genericPut($key, $value, $type)
    {
        $listName = $this->_getCorrectList($type);

        if (is_array($key)) {
            $this->$listName = array_merge($this->$listName, $key);
        } else {
            $list       = &$this->$listName;
            $list[$key] = $value;
        }

        return $this;
    }

    /**
     * common destroy data method for class data lists
     * 
     * @param string $key
     * @param string $type
     * @return $this
     */
    protected function _genericDestroy($key, $type)
    {
        $listName = $this->_getCorrectList($type);

        if ($key) {
            $list = &$this->$listName;
            unset ($list[$key]);
        }
        $this->$listName = [];

        return $this;
    }

    /**
     * common return data method for class data lists
     * 
     * @param string $key
     * @param string $type
     * @return mixed|null
     */
    protected function _genericReturn($key, $type)
    {
        $listName = $this->_getCorrectList($type);

        switch (true) {
            case !$key:
                return $this->$listName;

            case array_key_exists($key, $this->$listName):
                $list = &$this->$listName;
                return $list[$key];

            default:
                return null;
        }
    }

    /**
     * return name of data list variable for given data type
     * 
     * @param string $type
     * @return null|string
     */
    protected function _getCorrectList($type)
    {
        switch ($type) {
            case 'error_list':
                $type = '_errorsList';
                break;

            case 'validation':
                $type = '_validationRules';
                break;

            case 'preparation_callback':
                $type = '_dataPreparationCallbacks';
                break;

            case 'return_callback':
                $type = '_dataRetrieveCallbacks';
                break;
        }

        return $type;
    }

    /**
     * return data formatted by given function
     *
     * @param string $key
     * @param mixed $data
     * @param array $rulesList
     * @return mixed
     */
    protected function _dataPreparation($key, $data, array $rulesList)
    {
        foreach ($rulesList as $ruleKey => $function) {

            switch (true) {
                case is_null($key):
                    $data = $this->_prepareWholeData($ruleKey, $data, $function);
                    break;

                case preg_match($ruleKey, $key) && !is_null($key):
                    $data = $this->_callUserFunction($function, $key, $data, null);
                    break;

                default:
                    break;
            }
        }

        return $data;
    }

    /**
     * allow to use return preparation on all data in object
     *
     * @param string $ruleKey
     * @param array $data
     * @param array|string|\Closure $function
     * @return array
     */
    protected function _prepareWholeData($ruleKey, array $data, $function)
    {
        foreach ($data as $key => $value) {
            if (preg_match($ruleKey, $key)) {
                $data[$key] = $this->_callUserFunction($function, $key, $value, null);
            }
        }

        return $data;
    }

    /**
     * set regular expression for key find and validate data
     * 
     * @param string $ruleKey
     * @param callable $ruleValue
     * @return $this
     */
    public function putPreparationCallback($ruleKey, callable $ruleValue = null)
    {
        return $this->_genericPut($ruleKey, $ruleValue, 'preparation_callback');
    }

    /**
     * remove validation rule from list
     * 
     * @param string|null $key
     * @return $this
     */
    public function removePreparationCallback($key = null)
    {
        return $this->_genericDestroy($key, 'preparation_callback');
    }

    /**
     * return validation rule or all rules set in object
     * 
     * @param null|string $rule
     * @return mixed
     */
    public function returnPreparationCallback($rule = null)
    {
        return $this->_genericReturn($rule, 'preparation_callback');
    }

    /**
     * set regular expression for key find and validate data
     * 
     * @param string $ruleKey
     * @param callable $ruleValue
     * @return $this
     */
    public function putReturnCallback($ruleKey, callable $ruleValue = null)
    {
        return $this->_genericPut($ruleKey, $ruleValue, 'return_callback');
    }

    /**
     * remove validation rule from list
     * 
     * @param string|null $key
     * @return $this
     */
    public function removeReturnCallback($key = null)
    {
        return $this->_genericDestroy($key, 'return_callback');
    }

    /**
     * return validation rule or all rules set in object
     * 
     * @param null|string $rule
     * @return mixed
     */
    public function returnReturnCallback($rule = null)
    {
        return $this->_genericReturn($rule, 'return_callback');
    }

    /**
     * check that data for given key exists
     * 
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * return data for given key
     * 
     * @param string $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->toArray($offset);
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
        if (is_null($offset)) {
            $offset = $this->_integerToStringKey($this->_integerKeysCounter++);
        }

        $this->_putData($offset, $value);
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
        $this->destroy($offset);
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
        current($this->_DATA);
        return $this->toArray($this->key());
    }

    /**
     * return the current element in an array
     * 
     * @return mixed
     */
    public function key()
    {
        return key($this->_DATA);
    }

    /**
     * advance the internal array pointer of an array
     * handle data preparation
     * 
     * @return mixed
     */
    public function next()
    {
        next($this->_DATA);
        return $this->toArray($this->key());
    }

    /**
     * rewind the position of a file pointer
     * 
     * @return mixed
     */
    public function rewind()
    {
        return reset($this->_DATA);
    }

    /**
     * checks if current position is valid
     * 
     * @return bool
     */
    public function valid()
    {
        return key($this->_DATA) !== null;
    }

    /**
     * allow to stop data validation
     * 
     * @return $this
     */
    public function stopValidation()
    {
        $this->_validationOn = false;
        return $this;
    }

    /**
     * allow to start data validation
     * 
     * @return $this
     */
    public function startValidation()
    {
        $this->_validationOn = true;
        return $this;
    }

    /**
     * allow to stop data preparation before add tro object
     * 
     * @return $this
     */
    public function stopOutputPreparation()
    {
        $this->_getPreparationOn = false;
        return $this;
    }

    /**
     * allow to start data preparation before add tro object
     * 
     * @return $this
     */
    public function startOutputPreparation()
    {
        $this->_getPreparationOn = true;
        return $this;
    }

    /**
     * allow to stop data preparation before return them from object
     * 
     * @return $this
     */
    public function stopInputPreparation()
    {
        $this->_setPreparationOn = false;
        return $this;
    }

    /**
     * allow to start data preparation before return them from object
     * 
     * @return $this
     */
    public function startInputPreparation()
    {
        $this->_setPreparationOn = true;
        return $this;
    }

    /**
     * create exception message and set it in object
     * 
     * @param Exception $exception
     * @return $this
     */
    protected function _addException(Exception $exception)
    {
        $this->_hasErrors = true;
        $this->_errorsList[$exception->getCode()] = [
            'message'   => $exception->getMessage(),
            'line'      => $exception->getLine(),
            'file'      => $exception->getFile(),
            'trace'     => $exception->getTraceAsString(),
        ];

        return $this;
    }

    /**
     * can be overwritten by children objects to start with some special operations
     * as parameter take data given to object by reference
     *
     * @param mixed $data
     */
    public function initializeObject(&$data)
    {
        
    }

    /**
     * can be overwritten by children objects to start with some special
     * operations
     */
    public function afterInitializeObject()
    {
        
    }

    /**
     * can be overwritten by children objects to make some special process on
     * data before return
     * 
     * @param string|null $key
     */
    protected function _prepareData($key = null)
    {
        
    }

    /**
     * can be overwritten by children objects to start with some special operations
     * as parameter take data given to object by reference
     *
     * @param mixed $data
     */
    protected function _beforeInitializeObject($data)
    {

    }
}
