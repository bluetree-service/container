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
use Laminas\Serializer\Adapter\PhpSerialize;
use Laminas\Serializer\Exception\ExceptionInterface;
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
    protected string $skippedObject = ': {;skipped_object;}';

    /**
     * contains name of undefined data
     *
     * @var string
     */
    protected string $defaultDataName = 'default';

    /**
     * if there was some errors in object, that variable will be set on true
     *
     * @var bool
     */
    protected bool $hasErrors = false;

    /**
     * will contain list of all errors that was occurred in object
     *
     * 0 => ['error_key' => 'error information']
     *
     * @var array
     */
    protected array $errorsList = [];

    /**
     * array with main object data
     * @var array
     */
    protected array $data = [];

    /**
     * keeps data before changes (set only if some data in $data was changed)
     * @var array
     */
    protected array $originalData = [];

    /**
     * store all new added data keys, to remove them when in eg. restore original data
     * @var array
     */
    protected array $newKeys = [];

    /**
     * @var array
     */
    protected static array $cacheKeys = [];

    /**
     * @var bool
     */
    protected bool $dataChanged = false;

    /**
     * default constructor options
     *
     * @var array
     */
    protected array $options = [
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
    protected string $integerKeyPrefix;

    /**
     * separator for data to return as string
     *
     * @var string
     */
    protected string $separator = ', ';

    /**
     * store list of rules to validate data
     * keys are searched using regular expression
     *
     * @var array
     */
    protected array $validationRules = [];

    /**
     * list of callbacks to prepare data before insert into object
     *
     * @var array
     */
    protected array $dataPreparationCallbacks = [];

    /**
     * list of callbacks to prepare data before return from object
     *
     * @var array
     */
    protected array $dataRetrieveCallbacks = [];

    /**
     * for array access numeric keys, store last used numeric index
     * used only in case when object is used as array
     *
     * @var int
     */
    protected int $integerKeysCounter = 0;

    /**
     * allow to turn off/on data validation
     *
     * @var bool
     */
    protected bool $validationOn = true;

    /**
     * allow to turn off/on data preparation
     *
     * @var bool
     */
    protected bool $getPreparationOn = true;

    /**
     * allow to turn off/on data retrieve
     *
     * @var bool
     */
    protected bool $setPreparationOn = true;

    /**
     * inform append* methods that data was set in object creation
     *
     * @var bool
     */
    protected bool $objectCreation = true;

    /**
     * csv variable delimiter
     *
     * @var string
     */
    protected string $csvDelimiter = ';';

    /**
     * csv enclosure
     *
     * @var string
     */
    protected string $csvEnclosure = '"';

    /**
     * csv escape character
     *
     * @var string
     */
    protected string $csvEscape = '\\';

    /**
     * csv line delimiter (single object element)
     *
     * @var string
     */
    protected string $csvLineDelimiter = "\n";

    /**
     * allow to process [section] as array key
     *
     * @var bool
     */
    protected bool $processIniSection;

    /**
     * create new Blue Object, optionally with some data
     * there are some types we can give to convert data to Blue Object
     * like: json, xml, serialized or stdClass default is array
     *
     * @param array $options
     * @throws \ReflectionException
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);
        $data = $this->options['data'];
        $this->integerKeyPrefix = $this->options['integer_key_prefix'];
        $this->processIniSection = $this->options['ini_section'];

        $this->beforeInitializeObject($data);
        $this->putValidationRule($this->options['validation'])
            ->putPreparationCallback($this->options['preparation']);

        $data = $this->initializeObject($data);

        switch (true) {
            case $this->options['type'] === 'json':
                $this->appendJson($data);
                break;

            case $this->options['type'] === 'xml':
                $this->appendXml($data);
                break;

            case $this->options['type'] === 'simple_xml':
                $this->appendSimpleXml($data);
                break;

            case $this->options['type'] === 'serialized':
                $this->appendSerialized($data);
                break;

            case $this->options['type'] === 'csv':
                $this->appendCsv($data);
                break;

            case $this->options['type'] === 'ini':
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
        $this->objectCreation = false;
    }

    /**
     * return from data value for given object attribute
     *
     * @param string $key
     * @return mixed
     */

    public function __get(string $key): mixed
    {
        $key = $this->convertKeyNames($key);
        return $this->toArray($key);
    }

    /**
     * save into data value given as object attribute
     *
     * @param string $key
     * @param mixed $value
     * @throws \ReflectionException
     */
    public function __set(string $key, mixed $value): void
    {
        $key = $this->convertKeyNames($key);
        $this->putData($key, $value);
    }

    /**
     * check that variable exists in data table
     *
     * @param string $key
     * @return bool
     */
    public function __isset(string $key): bool
    {
        $key = $this->convertKeyNames($key);
        return $this->has($key);
    }

    /**
     * remove given key from data
     *
     * @param string $key
     */
    public function __unset(string $key): void
    {
        $key = $this->convertKeyNames($key);
        $this->destroy($key);
    }

    /**
     * allow to access data keys by using special methods
     * like getSomeData() will return $data['somedata'] value or
     * setSomeData('val') will create $data['somedata'] key with 'val' value
     * all magic methods handle data put and return preparation
     *
     * @param string $method
     * @param array $arguments
     * @return $this|bool|mixed
     * @throws \ReflectionException
     */
    public function __call(string $method, array $arguments): mixed
    {
        switch (true) {
            case str_starts_with($method, 'get'):
                $key = $this->convertKeyNames(substr($method, 3));
                $status = $this->get($key);
                break;

            case str_starts_with($method, 'set'):
                $key = $this->convertKeyNames(substr($method, 3));
                $status = $this->set($key, $arguments[0]);
                break;

            case str_starts_with($method, 'has'):
                $key = $this->convertKeyNames(substr($method, 3));
                $status = $this->has($key);
                break;

            case str_starts_with($method, 'not'):
                $key = $this->convertKeyNames(substr($method, 3));
                $val = $this->get($key);
                $status = $this->compareDataInternal($arguments[0], $key, $val, '!==');
                break;

            case str_starts_with($method, 'unset') || str_starts_with($method, 'destroy'):
                $key = $this->convertKeyNames(substr($method, 5));
                $status = $this->destroy($key);
                break;

            case str_starts_with($method, 'clear'):
                $key = $this->convertKeyNames(substr($method, 5));
                $status = $this->clear($key);
                break;

            case str_starts_with($method, 'restore'):
                $key = $this->convertKeyNames(substr($method, 7));
                $status = $this->restore($key);
                break;

            case str_starts_with($method, 'is'):
                $key = $this->convertKeyNames(substr($method, 2));
                $val = $this->get($key);
                $status = $this->compareDataInternal($arguments[0], $key, $val, '===');
                break;

            default:
                $this->errorsList['wrong_method'] = get_class($this) . ' - ' . $method;
                $this->hasErrors = true;
                $status = false;
                break;
        }

        return $status;
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
    protected function compareDataInternal(mixed $dataToCheck, string $key, mixed $originalData, string $comparator): bool
    {
        if (\is_callable($dataToCheck)) {
            return $dataToCheck($key, $originalData, $this);
        }

        return $this->comparator($originalData, $dataToCheck, $comparator);
    }

    /**
     * return object data as string representation
     *
     * @return string
     */
    public function __toString(): string
    {
        $this->prepareData();
        return implode($this->separator, $this->toArray());
    }

    /**
     * return bool information that object has some error
     *
     * @return bool
     */
    public function checkErrors(): bool
    {
        return $this->hasErrors;
    }

    /**
     * return single error by key, ora list of all errors
     *
     * @param string|null $key
     * @return mixed
     */
    public function returnObjectError(?string $key = null): mixed
    {
        return $this->genericReturn($key, 'error_list');
    }

    /**
     * remove single error, or all object errors
     *
     * @param string|null $key
     * @return ContainerObject|Container
     */
    public function removeObjectError(?string $key = null): self
    {
        $this->genericDestroy($key, 'error_list');
        $this->hasErrors = false;
        return $this;
    }

    /**
     * return serialized object data
     *
     * @param bool $skipObjects
     * @return string
     */
    public function serialize(bool $skipObjects = false): string
    {
        $this->prepareData();
        $temporaryData = $this->toArray();
        $data = '';

        if ($skipObjects) {
            $temporaryData = $this->traveler(
                [$this, 'skipObject'],
                null,
                $temporaryData,
                true
            );
        }

        try {
            $data = $this->serializeAdapter($temporaryData);
        } catch (ExceptionInterface $exception) {
            $this->addException($exception);
        }

        return $data;
    }

    /**
     * return object data from serialized string
     *
     * @param mixed $data
     * @return $this
     */
    protected function serializeAdapter(mixed $data): string
    {
        return (new PhpSerialize())->serialize($data);
    }

    /**
     * return object data from serialized string
     *
     * @param string $data
     * @return mixed
     */
    protected function unserializeAdapter(string $data): mixed
    {
        return (new PhpSerialize())->unserialize($data);
    }

    /**
     * allow to set data from serialized string with keep original data
     *
     * @param string $string
     * @return ContainerObject|Container
     * @throws \ReflectionException
     */
    public function unserialize(string $string): self
    {
        return $this->appendSerialized($string);
    }

    /**
     * return data for given key if exist in object, or all object data
     *
     * @param null|string $key
     * @return array
     * @deprecated
     */
    public function getData(?string $key = null): array
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
    public function get(?string $key = null): mixed
    {
        $this->prepareData($key);
        $data = null;

        if (is_null($key)) {
            $data = $this->data;
        } elseif (array_key_exists($key, $this->data)) {
            $data = $this->data[$key];
        }

        if ($this->getPreparationOn) {
            return $this->dataPreparation($key, $data, $this->dataRetrieveCallbacks);
        }
        return $data;
    }

    /**
     * set some data in object
     * can give pair key=>value or array of keys
     *
     * @param string|array $key
     * @param mixed $data
     * @return ContainerObject|Container
     * @throws \ReflectionException
     * @deprecated
     */
    public function setData(string|array $key, mixed $data = null): self
    {
        return $this->set($key, $data);
    }

    /**
     * set some data in object
     * can give pair key=>value or array of keys
     *
     * @param string|array $key
     * @param mixed $data
     * @return ContainerObject|Container
     * @throws \ReflectionException
     */
    public function set(string|array $key, mixed $data = null): self
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
    public function returnOriginalData(?string $key = null): mixed
    {
        $this->prepareData($key);

        $mergedData = \array_merge($this->data, $this->originalData);
        $data = $this->removeNewKeys($mergedData);

        return $data[$key] ?? null;
    }

    /**
     * check if data with given key exist in object, or object has some data
     * if key wast given
     *
     * @param null|string $key
     * @return bool
     * @deprecated
     */
    public function hasData(?string $key = null): bool
    {
        return $this->has($key);
    }

    /**
     * check if data with given key exist in object, or object has some data
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        if (\array_key_exists($key, $this->data)) {
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
     * @param array|string|Closure $operator
     * @param string|null $key
     * @param bool $origin
     * @return bool|null
     */
    public function compareData(mixed $dataToCheck, ?string $key = null, array|string|Closure $operator = '===', bool $origin = false): ?bool
    {
        if ($origin) {
            $mergedData = \array_merge($this->data, $this->originalData);
            $data = $this->removeNewKeys($mergedData);
        } else {
            $data = $this->data;
        }

        if ($dataToCheck instanceof Container) {
            $dataToCheck = $dataToCheck->toArray();
        }

        if (\is_callable($operator)) {
            return $operator($key, $dataToCheck, $data, $this);
        }

        return match (true) {
            \is_null($key) => $this->comparator($dataToCheck, $data, $operator),
            \array_key_exists($key, $data) => $this->comparator($dataToCheck, $data[$key], $operator),
            default => false,
        };
    }

    /**
     * allow to compare data with given operator
     *
     * @param mixed $dataOrigin
     * @param mixed $dataCheck
     * @param string $operator
     * @return bool|null
     */
    protected function comparator(mixed $dataOrigin, mixed $dataCheck, string $operator): ?bool
    {
        return match ($operator) {
            '===' => $dataOrigin === $dataCheck,
            '!==' => $dataOrigin !== $dataCheck,
            '==' => $dataOrigin == $dataCheck,
            '!=', '<>' => $dataOrigin != $dataCheck,
            '<' => $dataOrigin < $dataCheck,
            '>' => $dataOrigin > $dataCheck,
            '<=' => $dataOrigin <= $dataCheck,
            '>=' => $dataOrigin >= $dataCheck,
            '<=>' => $dataOrigin <=> $dataCheck,
            default => null,
        };
    }

    /**
     * destroy key entry in object data, or whole keys
     * automatically set data to original array
     *
     * @param string|null $key
     * @return ContainerObject|Container
     * @deprecated
     */
    public function unsetData(?string $key = null): self
    {
        return $this->destroy($key);
    }

    /**
     * destroy key entry in object data, or whole keys
     * automatically set data to original array
     *
     * @param string|null $key
     * @return ContainerObject|Container
     */
    public function destroy(?string $key = null): self
    {
        if (\is_null($key)) {
            $this->dataChanged  = true;
            $mergedData = \array_merge($this->data, $this->originalData);
            $this->originalData = $this->removeNewKeys($mergedData);
            $this->data = [];

        } elseif (\array_key_exists($key, $this->data)) {
            $this->dataChanged = true;

            if (!\array_key_exists($key, $this->originalData)
                && !\array_key_exists($key, $this->newKeys)
            ) {
                $this->originalData[$key] = $this->data[$key];
            }

            unset ($this->data[$key]);
        }

        return $this;
    }

    /**
     * set object key data to null
     *
     * @param string $key
     * @return ContainerObject|Container
     * @throws \ReflectionException
     * @deprecated
     */
    public function clearData(string $key): self
    {
        return $this->clear($key);
    }

    /**
     * set object key data to null
     *
     * @param string $key
     * @return ContainerObject|Container
     * @throws \ReflectionException
     */
    public function clear(string $key): self
    {
        $this->putData($key, null);
        return $this;
    }

    /**
     * replace changed data by original data
     * set data changed to false only if restore whole data
     *
     * @param string|null $key
     * @return ContainerObject|Container
     * @deprecated
     */
    public function restoreData(?string $key = null): self
    {
        return $this->restore($key);
    }

    /**
     * replace changed data by original data
     * set data changed to false only if restore whole data
     *
     * @param string|null $key
     * @return ContainerObject|Container
     */
    public function restore(?string $key = null): self
    {
        if (\is_null($key)) {
            $mergedData = \array_merge($this->data, $this->originalData);
            $this->data = $this->removeNewKeys($mergedData);
            $this->dataChanged = false;
            $this->newKeys = [];
        } elseif (\array_key_exists($key, $this->originalData)) {
            $this->data[$key] = $this->originalData[$key];
        }

        return $this;
    }

    /**
     * all data stored in object became original data
     *
     * @return ContainerObject|Container
     */
    public function replaceDataArrays(): self
    {
        $this->originalData = [];
        $this->dataChanged = false;
        $this->newKeys = [];
        return $this;
    }

    /**
     * return object as string
     * each data value separated by coma
     *
     * @param string|null $separator
     * @return string
     */
    public function toString(?string $separator = null): string
    {
        if (!\is_null($separator)) {
            $this->separator = $separator;
        }

        $this->prepareData();
        return $this->__toString();
    }

    /**
     * return current separator
     *
     * @return string
     */
    public function returnSeparator(): string
    {
        return $this->separator;
    }

    /**
     * allow to change default separator
     *
     * @param string $separator
     * @return ContainerObject|Container
     */
    public function changeSeparator(string $separator): self
    {
        $this->separator = $separator;
        return $this;
    }

    /**
     * return data as json string
     *
     * @return string
     * @throws \JsonException
     */
    public function toJson(): string
    {
        $this->prepareData();
        return \json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * return object data as xml representation
     *
     * @param bool $addCdata
     * @param string|bool $dtd
     * @param string $version
     * @return string
     * @throws DOMException
     */
    public function toXml(bool $addCdata = true, string|bool$dtd = false, string $version = '1.0'): string
    {
        $this->prepareData();

        $xml = new Xml(['version' => $version]);
        $root = $xml->createElement('root');
        $xml = $this->arrayToXml($this->toArray(), $xml, $addCdata, $root);

        $xml->appendChild($root);

        if ($dtd) {
            $dtd = "<!DOCTYPE root SYSTEM '$dtd'>";
        }

        $xml->formatOutput = true;
        $xmlData = $xml->saveXmlFile(false);

        if ($xml->hasErrors()) {
            $this->hasErrors = true;
            $this->errorsList[] = $xml->getError();
            return false;
        }

        return $dtd . $xmlData;
    }

    /**
     * return object as stdClass
     *
     * @return stdClass
     */
    public function toStdClass(): stdClass
    {
        $this->prepareData();
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
    public function toArray(?string $key = null): mixed
    {
        return $this->get($key);
    }

    /**
     * return information that some data was changed in object
     *
     * @return bool
     */
    public function dataChanged(): bool
    {
        return $this->dataChanged;
    }

    /**
     * check that data for given key was changed
     *
     * @param string $key
     * @return bool
     */
    public function keyDataChanged(string $key): bool
    {
        $data = $this->toArray($key);
        $originalData = $this->returnOriginalData($key);

        return $data !== $originalData;
    }

    /**
     * allow to use given method or function for all data inside of object
     *
     * @param array|string|Closure $function
     * @param mixed $methodAttributes
     * @param mixed $data
     * @param bool $recursive
     * @return array|null
     */
    public function traveler(
        array|string|Closure $function,
        mixed $methodAttributes = null,
        mixed $data = null,
        bool $recursive = false
    ): ?array {
        if (!$data) {
            $data =& $this->data;
        }

        foreach ($data as $key => $value) {
            $isRecursive = \is_array($value) && $recursive;

            if ($isRecursive) {
                $data[$key] = $this->recursiveTraveler($function, $methodAttributes, $value);
            } else {
                $data[$key] = $this->callUserFunction($function, $key, $value, $methodAttributes);
            }
        }

        return $data;
    }

    /**
     * allow to change some data in multi level arrays
     *
     * @param mixed $methodAttributes
     * @param mixed $data
     * @param array|string|Closure $function
     * @return mixed
     */
    protected function recursiveTraveler(mixed $function, mixed $methodAttributes, array|string|Closure $data): mixed
    {
        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                $data[$key] = $this->recursiveTraveler($function, $methodAttributes, $value);
            } else {
                $data[$key] = $this->callUserFunction($function, $key, $value, $methodAttributes);
            }
        }

        return $data;
    }

    /**
     * run given function, method or closure on given data
     *
     * @param array|string|Closure $function
     * @param string $key
     * @param mixed $value
     * @param mixed $attributes
     * @return mixed
     */
    protected function callUserFunction(array|string|Closure $function, string $key, mixed $value, mixed $attributes): mixed
    {
        if (is_callable($function)) {
            return $function($key, $value, $this, $attributes);
        }

        return $value;
    }

    /**
     * allow to join two blue objects into one
     *
     * @param Container $object
     * @return ContainerObject|Container
     * @throws \ReflectionException
     */
    public function mergeBlueObject(Container $object): self
    {
        $newData = $object->toArray();

        foreach ($newData as $key => $value) {
            $this->appendData($key, $value);
        }

        $this->dataChanged = true;
        return $this;
    }

    /**
     * remove all new keys from given data
     *
     * @param array $data
     * @return array
     */
    protected function removeNewKeys(array $data): array
    {
        foreach ($this->newKeys as $key) {
            unset($data[$key]);
        }
        return $data;
    }

    /**
     * clear some data after creating new object with data
     *
     * @return ContainerObject|Container
     */
    protected function afterAppendDataToNewObject(): self
    {
        $this->dataChanged = false;
        $this->newKeys = [];

        return $this;
    }

    /**
     * apply given json data as object data
     *
     * @param string $data
     * @return ContainerObject|Container
     * @throws \ReflectionException
     */
    public function appendJson(string $data): self
    {
        try {
            $jsonData = \json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->addException($exception);
            return $this;
        }

        $this->appendArray($jsonData);

        if ($this->objectCreation) {
            return $this->afterAppendDataToNewObject();
        }

        return $this;
    }

    /**
     * apply given xml data as object data
     *
     * @param $data string
     * @return ContainerObject|Container
     * @throws \ReflectionException
     */
    public function appendSimpleXml(string $data): self
    {
        try {
            $loadedXml = \simplexml_load_string($data);
            $jsonXml = \json_encode($loadedXml, JSON_THROW_ON_ERROR);
            $jsonData = \json_decode($jsonXml, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->addException($exception);
            return $this;
        }

        $this->appendArray($jsonData);

        if ($this->objectCreation) {
            return $this->afterAppendDataToNewObject();
        }
        return $this;
    }

    /**
     * apply given xml data as object data
     * also handling attributes
     *
     * @param $data string
     * @return ContainerObject|Container
     * @throws \ReflectionException
     */
    public function appendXml(string $data): self
    {
        $xml = new Xml();
        $xml->preserveWhiteSpace = false;
        $bool = @$xml->loadXML($data);

        if (!$bool) {
            $this->errorsList['xml_load_error'] = $data;
            $this->hasErrors = true;
            return $this;
        }

        try {
            $temp = $this->xmlToArray($xml->documentElement);
            $this->appendArray($temp);
        } catch (DOMException $exception) {
            $this->addException($exception);
        }

        if ($this->objectCreation) {
            return $this->afterAppendDataToNewObject();
        }

        return $this;
    }

    /**
     * recurrent function to travel on xml nodes and set their data as object data
     *
     * @param DOMElement $data
     * @return array
     */
    protected function xmlToArray(DOMElement $data): array
    {
        $temporaryData = [];

        /** @var $node DOMElement */
        foreach ($data->childNodes as $node) {
            $nodeName = $this->stringToIntegerKey($node->nodeName);
            $nodeData = [];
            $unSerializedData = [];

            if ($node->hasAttributes() && $node->getAttribute('serialized_object')) {
                try {
                    $unSerializedData = $this->unserializeAdapter($node->nodeValue);
                } catch (ExceptionInterface $exception) {
                    $this->addException($exception);
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
                        $childNodesData = $this->xmlToArray($node);
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
     * @return string|int
     */
    protected function stringToIntegerKey(string $key): int|string
    {
        return \str_replace($this->integerKeyPrefix, '', $key);
    }

    /**
     * return set up integer key prefix value
     *
     * @return string
     */
    public function returnIntegerKeyPrefix(): string
    {
        return $this->integerKeyPrefix;
    }

    /**
     * allow to set array in object or some other value
     *
     * @param array $arrayData
     * @return ContainerObject|Container
     * @throws \ReflectionException
     */
    public function appendArray(array $arrayData): self
    {
        foreach ($arrayData as $dataKey => $data) {
            $this->putData($dataKey, $data);
        }

        if ($this->objectCreation) {
            return $this->afterAppendDataToNewObject();
        }

        return $this;
    }

    /**
     * allow to set some mixed data type as given key
     *
     * @param array|string $key
     * @param mixed $data
     * @return ContainerObject|Container
     * @throws \ReflectionException
     */
    public function appendData(array|string $key, mixed $data): self
    {
        $this->putData($key, $data);

        if ($this->objectCreation) {
            return $this->afterAppendDataToNewObject();
        }

        return $this;
    }

    /**
     * get class variables and set them as data
     *
     * @param stdClass $class
     * @return ContainerObject|Container
     * @throws \ReflectionException
     */
    public function appendStdClass(stdClass $class): self
    {
        $this->appendArray(\get_object_vars($class));

        if ($this->objectCreation) {
            return $this->afterAppendDataToNewObject();
        }

        return $this;
    }

    /**
     * set data from serialized string as object data
     * if data is an object set one variable where key is an object class name
     *
     * @param mixed $data
     * @return ContainerObject|Container
     * @throws \ReflectionException
     */
    public function appendSerialized(mixed $data): self
    {
        try {
            $data = $this->unserializeAdapter($data);
        } catch (ExceptionInterface $exception) {
            $this->addException($exception);
        }

        if (\is_object($data)) {
            $name = $this->convertKeyNames(\get_class($data));
            $this->appendData($name, $data);
        } elseif (\is_array($data)) {
            $this->appendArray($data);
        } else {
            $this->appendData($this->defaultDataName, $data);
        }

        if ($this->objectCreation) {
            return $this->afterAppendDataToNewObject();
        }

        return $this;
    }

    /**
     * allow to set ini data into object
     *
     * @param string $data
     * @return ContainerObject|Container
     * @throws \ReflectionException
     */
    public function appendIni(string $data): self
    {
        $array = \parse_ini_string($data, $this->processIniSection, INI_SCANNER_RAW);

        if ($array === false) {
            $this->hasErrors = true;
            $this->errorsList[] = 'parse_ini_string';
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
    public function returnProcessIniSection(): bool
    {
        return $this->processIniSection;
    }

    /**
     * enable or disable ini section processing
     *
     * @param bool $bool
     * @return ContainerObject|Container
     */
    public function processIniSection(bool $bool): self
    {
        $this->processIniSection = $bool;
        return $this;
    }

    /**
     * export object as ini string
     *
     * @return string
     */
    public function toIni(): string
    {
        $ini = '';

        foreach ($this->toArray() as $key => $iniRow) {
            $this->appendIniData($ini, $key, $iniRow);
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
    protected function appendIniData(string &$ini, string $key, mixed $iniRow): void
    {
        if ($this->processIniSection && is_array($iniRow)) {
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
     * @return ContainerObject|Container
     * @throws \ReflectionException
     */
    public function appendCsv(string $data): self
    {
        $counter = 0;
        $rows = \str_getcsv($data, $this->csvLineDelimiter);

        foreach ($rows as $row) {
            $rowData = \str_getcsv(
                $row,
                $this->csvDelimiter,
                $this->csvEnclosure,
                $this->csvEscape
            );

            $this->putData($this->integerKeyPrefix . $counter, $rowData);

            $counter++;
        }

        if ($this->objectCreation) {
            return $this->afterAppendDataToNewObject();
        }

        return $this;
    }

    /**
     * @return string
     */
    public function returnCsvDelimiter(): string
    {
        return $this->csvDelimiter;
    }

    /**
     * @return string
     */
    public function returnCsvEnclosure(): string
    {
        return $this->csvEnclosure;
    }

    /**
     * @return string
     */
    public function returnCsvEscape(): string
    {
        return $this->csvEscape;
    }

    /**
     * @return string
     */
    public function returnCsvLineDelimiter(): string
    {
        return $this->csvLineDelimiter;
    }

    /**
     * change delimiter for csv row data (give only one character)
     *
     * @param string $char
     * @return ContainerObject|Container
     */
    public function changeCsvDelimiter(string $char): self
    {
        $this->csvDelimiter = $char;
        return $this;
    }

    /**
     * change enclosure for csv row data (give only one character)
     *
     * @param string $char
     * @return ContainerObject|Container
     */
    public function changeCsvEnclosure(string $char): self
    {
        $this->csvEnclosure = $char;
        return $this;
    }

    /**
     * change data escape for csv row data (give only one character)
     *
     * @param string $char
     * @return ContainerObject|Container
     */
    public function changeCsvEscape(string $char): self
    {
        $this->csvEscape = $char;
        return $this;
    }

    /**
     * change data row delimiter (give only one character)
     *
     * @param string $char
     * @return ContainerObject|Container
     */
    public function changeCsvLineDelimiter(string $char): self
    {
        $this->csvLineDelimiter = $char;
        return $this;
    }

    /**
     * export object as csv data
     *
     * @return string
     */
    public function toCsv(): string
    {
        $csv = '';

        foreach ($this->toArray() as $csvRow) {
            if (\is_array($csvRow)) {
                $data = \implode($this->csvDelimiter, $csvRow);
            } else {
                $data = $csvRow;
            }

            $csv .= $data . $this->csvLineDelimiter;
        }

        return \rtrim($csv, $this->csvLineDelimiter);
    }

    /**
     * check that given data for key is valid and set in object if don't exist or is different
     *
     * @param string $key
     * @param mixed $data
     * @return ContainerObject|Container
     * @throws \ReflectionException
     */
    protected function putData(string $key, mixed $data): self
    {
        $bool = $this->validateDataKey($key, $data);
        if (!$bool) {
            return $this;
        }

        $hasData = $this->has($key);
        if ($this->setPreparationOn) {
            $data = $this->dataPreparation(
                $key,
                $data,
                $this->dataPreparationCallbacks
            );
        }

        if (!$hasData || ($this->comparator($this->data[$key], $data, '!=='))) {
            $this->changeData($key, $data, $hasData);
        }

        return $this;
    }

    /**
     * insert single key=>value pair into object, with key conversion
     * and set dataChanged to true
     * also set original data for given key in $this->originalData
     *
     * @param string $key
     * @param mixed $data
     * @param bool $hasData
     * @return ContainerObject|Container
     */
    protected function changeData(string $key, mixed $data, bool $hasData): self
    {
        if (!\array_key_exists($key, $this->originalData)
            && $hasData
            && !\array_key_exists($key, $this->newKeys)
        ) {
            $this->originalData[$key] = $this->data[$key];
        } else {
            $this->newKeys[$key] = $key;
        }

        $this->dataChanged = true;
        $this->data[$key]  = $data;

        return $this;
    }

    /**
     * search validation rule for given key and check data
     *
     * @param string $key
     * @param mixed $data
     * @return bool
     * @throws \ReflectionException
     */
    protected function validateDataKey(string $key, mixed $data): bool
    {
        $dataOkFlag = true;

        if (!$this->validationOn) {
            return true;
        }

        foreach ($this->validationRules as $ruleKey => $ruleValue) {
            if (!preg_match($ruleKey, $key)) {
                continue;
            }

            $bool = $this->validateData($ruleValue, $key, $data);
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
     * @param string|array|Closure $rule
     * @param string $key
     * @param mixed $data
     * @return bool
     * @throws \ReflectionException
     */
    protected function validateData(string|array|Closure $rule, string $key, mixed $data): bool
    {
        if (\is_callable($rule)) {
            $validate = $rule($key, $data, $this);
        } else {
            $validate = \preg_match($rule, $data);
        }

        if ($validate) {
            return true;
        }

        if ($rule instanceof Closure) {
            $reflection = new ReflectionFunction($rule);
            $rule = $reflection->__toString();
        }

        $this->errorsList[] = [
            'message' => 'validation_mismatch',
            'key' => $key,
            'data' => $data,
            'rule' => $rule,
        ];
        $this->hasErrors = true;

        return false;
    }

    /**
     * convert given object data key (given as came case method)
     * to proper construction
     *
     * @param string $key
     * @return string
     */
    protected function convertKeyNames(string $key): string
    {
        if (\array_key_exists($key, self::$cacheKeys)) {
            return self::$cacheKeys[$key];
        }

        $convertedKey = \strtolower(
            \preg_replace('/(.)([A-Z0-9])/', "$1_$2", $key)
        );
        self::$cacheKeys[$key] = $convertedKey;

        return $convertedKey;
    }

    /**
     * recursive method to create structure xml structure of object data
     *
     * @param $data
     * @param Xml $xml
     * @param bool $addCdata
     * @param DOMElement|Xml $parent
     * @return Xml
     */
    protected function arrayToXml($data, Xml $xml, bool $addCdata, DOMElement|Xml $parent): Xml
    {
        foreach ($data as $key => $value) {
            $key = \str_replace(' ', '_', $key);
            $attributes = [];
            $data = '';

            if (\is_object($value)) {
                try {
                    $data = $this->serializeAdapter($value);
                } catch (ExceptionInterface $exception) {
                    $this->addException($exception);
                }

                $value = [
                    '@attributes' => ['serialized_object' => true],
                    $data
                ];
            }

            try {
                $isArray = \is_array($value);

                if ($isArray && \array_key_exists('@attributes', $value)) {
                    $attributes = $value['@attributes'];
                    unset ($value['@attributes']);
                }

                if ($isArray) {
                    $parent = $this->convertArrayDataToXml(
                        $value,
                        $addCdata,
                        $xml,
                        $key,
                        $parent,
                        $attributes
                    );
                    continue;
                }

                $element = $this->appendDataToNode($addCdata, $xml, $key, $value);
                $parent->appendChild($element);

            } catch (DOMException $exception) {
                $this->addException($exception);
            }
        }

        return $xml;
    }

    /**
     * convert array data value to xml format and return as xml object
     *
     * @param array|string $value
     * @param string $addCdata
     * @param Xml $xml
     * @param int|string $key
     * @param DOMElement $parent
     * @param array $attributes
     * @return DOMElement
     * @throws DOMException
     */
    protected function convertArrayDataToXml(
        array|string $value,
        string $addCdata,
        Xml $xml,
        int|string $key,
        DOMElement $parent,
        array $attributes
    ): DOMElement {
        $count = \count($value) === 1;
        $isNotEmpty = !empty($attributes);
        $exist = \array_key_exists(0, $value);

        if ($count && $isNotEmpty && $exist) {
            $children = $this->appendDataToNode(
                $addCdata,
                $xml,
                $key,
                $value[0]
            );
        } else {
            $children = $xml->createElement(
                $this->integerToStringKey($key)
            );
            $this->arrayToXml($value, $xml, $addCdata, $children);
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
     * @param int|string $key
     * @param string $value
     * @return DOMElement
     * @throws DOMException
     */
    protected function appendDataToNode(string $addCdata, Xml $xml, int|string $key, string $value): DOMElement
    {
        if ($addCdata) {
            $cdata = $xml->createCdataSection($value);
            $element = $xml->createElement(
                $this->integerToStringKey($key)
            );
            $element->appendChild($cdata);
        } else {
            $element = $xml->createElement(
                $this->integerToStringKey($key),
                $value
            );
        }

        return $element;
    }

    /**
     * if array key is number, convert it to string with set up integerKeyPrefix
     *
     * @param int|string $key
     * @return string
     */
    protected function integerToStringKey(int|string $key): string
    {
        if (\is_numeric($key)) {
            $key = $this->integerKeyPrefix . $key;
        }

        return (string)$key;
    }

    /**
     * replace object by string
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function skipObject(string $key, mixed $value): mixed
    {
        if (\is_object($value)) {
            return $key . $this->skippedObject;
        }

        return $value;
    }

    /**
     * set regular expression for key find and validate data
     *
     * @param string|array|Closure $ruleKey
     * @param string|null|Closure $ruleValue
     * @return ContainerObject|Container
     */
    public function putValidationRule(string|array|Closure $ruleKey, null|string|Closure $ruleValue = null): self
    {
        return $this->genericPut($ruleKey, $ruleValue, 'validation');
    }

    /**
     * remove validation rule from list
     *
     * @param string|null $key
     * @return ContainerObject|Container
     */
    public function removeValidationRule(?string $key = null): self
    {
        return $this->genericDestroy($key, 'validation');
    }

    /**
     * return validation rule or all rules set in object
     *
     * @param string|null $rule
     * @return mixed
     */
    public function returnValidationRule(?string $rule = null): mixed
    {
        return $this->genericReturn($rule, 'validation');
    }

    /**
     * common put data method for class data lists
     *
     * @param array|string $key
     * @param mixed $value
     * @param string $type
     * @return ContainerObject|Container
     */
    protected function genericPut(array|string $key, mixed $value, string $type): self
    {
        $listName = $this->getCorrectList($type);

        if (\is_array($key)) {
            $this->$listName = \array_merge($this->$listName, $key);
        } else {
            $list = &$this->$listName;
            $list[$key] = $value;
        }

        return $this;
    }

    /**
     * common destroy data method for class data lists
     *
     * @param string|null $key
     * @param string $type
     * @return ContainerObject|Container
     */
    protected function genericDestroy(?string $key, string $type): self
    {
        $listName = $this->getCorrectList($type);

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
     * @param string|null $key
     * @param string $type
     * @return mixed
     */
    protected function genericReturn(?string $key, string $type): mixed
    {
        $listName = $this->getCorrectList($type);

        switch (true) {
            case !$key:
                return $this->$listName;

            case \array_key_exists($key, $this->$listName):
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
    protected function getCorrectList(string $type): ?string
    {
        return match ($type) {
            'error_list' => 'errorsList',
            'validation' => 'validationRules',
            'preparation_callback' => 'dataPreparationCallbacks',
            'return_callback' => 'dataRetrieveCallbacks',
            default => $type
        };
    }

    /**
     * return data formatted by given function
     *
     * @param string|null $key
     * @param mixed $data
     * @param array $rulesList
     * @return mixed
     */
    protected function dataPreparation(?string $key, mixed $data, array $rulesList): mixed
    {
        foreach ($rulesList as $ruleKey => $function) {

            switch (true) {
                case \is_null($key):
                    $data = $this->prepareWholeData($ruleKey, $data, $function);
                    break;

                case \preg_match($ruleKey, $key) && !\is_null($key):
                    $data = $this->callUserFunction($function, $key, $data, null);
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
     * @param array|string|Closure $function
     * @return array
     */
    protected function prepareWholeData(string $ruleKey, array $data, array|string|Closure $function): array
    {
        foreach ($data as $key => $value) {
            if (\preg_match($ruleKey, $key)) {
                $data[$key] = $this->callUserFunction($function, $key, $value, null);
            }
        }

        return $data;
    }

    /**
     * set regular expression for key find and validate data
     *
     * @param string|array $ruleKey
     * @param callable|null $ruleValue
     * @return Container|ContainerObject
     */
    public function putPreparationCallback(string|array $ruleKey, callable $ruleValue = null): self
    {
        return $this->genericPut($ruleKey, $ruleValue, 'preparation_callback');
    }

    /**
     * remove validation rule from list
     *
     * @param string|null $key
     * @return Container|ContainerObject
     */
    public function removePreparationCallback(?string $key = null): self
    {
        return $this->genericDestroy($key, 'preparation_callback');
    }

    /**
     * return validation rule or all rules set in object
     *
     * @param string|null $rule
     * @return mixed
     */
    public function returnPreparationCallback(?string $rule = null): mixed
    {
        return $this->genericReturn($rule, 'preparation_callback');
    }

    /**
     * set regular expression for key find and validate data
     *
     * @param string|array $ruleKey
     * @param callable|null $ruleValue
     * @return Container|ContainerObject
     */
    public function putReturnCallback(string|array $ruleKey, callable $ruleValue = null): self
    {
        return $this->genericPut($ruleKey, $ruleValue, 'return_callback');
    }

    /**
     * remove validation rule from list
     *
     * @param string|null $key
     * @return Container|ContainerObject
     */
    public function removeReturnCallback(?string $key = null): self
    {
        return $this->genericDestroy($key, 'return_callback');
    }

    /**
     * return validation rule or all rules set in object
     *
     * @param string|null $rule
     * @return mixed
     */
    public function returnReturnCallback(?string $rule = null): mixed
    {
        return $this->genericReturn($rule, 'return_callback');
    }

    /**
     * check that data for given key exists
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * return data for given key
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray($offset);
    }

    /**
     * set data for given key
     *
     * @param string|null $offset
     * @param mixed $value
     * @throws \ReflectionException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (\is_null($offset)) {
            $offset = $this->integerToStringKey($this->integerKeysCounter++);
        }

        $this->putData($offset, $value);
    }

    /**
     * remove data for given key
     *
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->destroy($offset);
    }

    /**
     * return the current element in an array
     * handle data preparation
     *
     * @return mixed
     */
    public function current(): mixed
    {
        return current($this->data);
    }

    /**
     * return the current element in an array
     *
     * @return string|int|null
     */
    public function key(): string|int|null
    {
        return key($this->data);
    }

    /**
     * advance the internal array pointer of an array
     * handle data preparation
     *
     * @return void
     */
    public function next(): void
    {
        next($this->data);
    }

    /**
     * rewind the position of a file pointer
     *
     * @return void
     */
    public function rewind(): void
    {
        reset($this->data);
    }

    /**
     * checks if current position is valid
     *
     * @return bool
     */
    public function valid(): bool
    {
        return key($this->data) !== null;
    }

    /**
     * allow to stop data validation
     *
     * @return Container|ContainerObject
     */
    public function stopValidation(): self
    {
        $this->validationOn = false;
        return $this;
    }

    /**
     * allow to start data validation
     *
     * @return Container|ContainerObject
     */
    public function startValidation(): self
    {
        $this->validationOn = true;
        return $this;
    }

    /**
     * allow to stop data preparation before add tro object
     *
     * @return Container|ContainerObject
     */
    public function stopOutputPreparation(): self
    {
        $this->getPreparationOn = false;
        return $this;
    }

    /**
     * allow to start data preparation before add tro object
     *
     * @return Container|ContainerObject
     */
    public function startOutputPreparation(): self
    {
        $this->getPreparationOn = true;
        return $this;
    }

    /**
     * allow to stop data preparation before return them from object
     *
     * @return Container|ContainerObject
     */
    public function stopInputPreparation(): self
    {
        $this->setPreparationOn = false;
        return $this;
    }

    /**
     * allow to start data preparation before return them from object
     *
     * @return Container|ContainerObject
     */
    public function startInputPreparation(): self
    {
        $this->setPreparationOn = true;
        return $this;
    }

    /**
     * create exception message and set it in object
     *
     * @param Exception $exception
     * @return Container|ContainerObject
     */
    protected function addException(Exception $exception): self
    {
        $this->hasErrors = true;
        $this->errorsList[$exception->getCode()] = [
            'message' => $exception->getMessage(),
            'line' => $exception->getLine(),
            'file' => $exception->getFile(),
            'trace' => $exception->getTraceAsString(),
        ];

        return $this;
    }

    /**
     * can be overwritten by children objects to start with some special operations
     * as parameter take data given to object by reference
     *
     * @param mixed $data
     * @return mixed
     */
    public function initializeObject(mixed $data): mixed
    {
        return $data;
    }

    /**
     * can be overwritten by children objects to start with some special
     * operations
     */
    public function afterInitializeObject(): void
    {
        
    }

    /**
     * can be overwritten by children objects to make some special process on
     * data before return
     *
     * @param string|null $key
     */
    protected function prepareData(?string $key = null): void
    {
        
    }

    /**
     * can be overwritten by children objects to start with some special operations
     * as parameter take data given to object by reference
     *
     * @param mixed $data
     * @return mixed
     */
    protected function beforeInitializeObject(mixed $data): mixed
    {
        return $data;
    }
}
