<?php
/**
 * test ContainerObject using Container class
 *
 * @package     BlueContainer
 * @subpackage  Test
 * @author      Michał Adamiak    <chajr@bluetree.pl>
 * @copyright   bluetree-service
 */
namespace Test;

use BlueContainer\Container;
use Zend\Serializer\Serializer;
use StdClass;

class ObjectTest extends \PHPUnit_Framework_TestCase
{
    /**
     * prefix for some changed data
     */
    const IM_CHANGED = 'im changed';

    /**
     * check data validation
     */
    public function testDataValidation()
    {
        $object = new Container();
        $data   = [
            'data_first'    => 'first data',
            'data_second'   => 'second data',
            'data_third'    => 'third data',
            'data_fourth'   => null,
        ];

        $object->putValidationRule('#data_first#', '#^[\d]+$#');
        $object->putValidationRule('#data_second#', '#[\w]*#');
        $object->putValidationRule('#data_(third|fourth)#', function ($key, $data) {
            if (is_null($data)) {
                return true;
            }
            return false;
        });

        $this->assertEquals('#^[\d]+$#', $object->returnValidationRule('#data_first#'));
        $this->assertNull($object->returnValidationRule('none_existing_rule'));

        $object->stopValidation();
        $object->set($data);

        $this->assertFalse($object->checkErrors());

        $object->startValidation();
        $object->set($data);

        $this->assertTrue($object->checkErrors());
        $this->assertEquals($object->returnObjectError()[0], [
            "message" => "validation_mismatch",
            "key"=> "data_first",
            "data"=> "first data",
            "rule"=> "#^[\\d]+$#"
        ]);
        $this->assertEquals($object->returnObjectError()[1]['message'], 'validation_mismatch');
        $this->assertEquals($object->returnObjectError()[1]['key'], 'data_third');
        $this->assertEquals($object->returnObjectError()[1]['data'], 'third data');
        $this->assertCount(2, $object->returnObjectError());

        $object->removeValidationRule();

        $this->assertNull($object->returnValidationRule('#data_first#'));
    }

    /**
     * check data validation in constructor
     */
    public function testDataValidationInConstructor()
    {
        $object = new Container([
            'data'          => [
                'data_first'    => 'first data',
                'data_second'   => 4535,
            ],
            'validation'    => [
                '#data_first#'  => '#^[\w ]+$#',
                '#data_second#' => '#^[\d]+$#',
            ],
        ]);

        $this->assertFalse($object->checkErrors());
    }

    /**
     * check data preparation in constructor
     */
    public function testDataPreparationInConstructor()
    {
        $object = new Container([
            'data'          => [
                'data_first'    => 'first data',
                'data_second'   => 4535,
            ],
            'preparation'    => [
                '#^data_[\w]+#'  => function () {
                    return self::IM_CHANGED;
                },
            ],
        ]);

        $this->assertEquals(self::IM_CHANGED, $object->getDataFirst());
    }

    /**
     * check that object after creation has some errors
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testCreateSimpleObject($first, $second)
    {
        $object = $this->_simpleObject($first, $second);

        $this->assertFalse($object->checkErrors());
        $this->assertEmpty($object->returnObjectError());
    }

    /**
     * check data returned by get* methods
     * 
     * @param mixed $first
     * @param mixed $second
     * 
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testGetDataFromObject($first, $second)
    {
        $object = $this->_simpleObject($first, $second);

        $this->assertEquals($first, $object->getDataFirst());
        $this->assertEquals($second, $object->toArray('data_second'));
        $this->assertEquals($second, $object['data_second']);
        $this->assertNull($object->getDataNotExists());

        $this->assertEquals(
            $this->_getSimpleData($first, $second),
            $object->toArray()
        );
    }

    /**
     * check data with has*, is* and not* magic methods
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testCheckingData($first, $second)
    {
        $object = $this->_simpleObject($first, $second);

        $this->assertTrue($object->hasDataFirst());
        $this->assertFalse($object->hasDataNotExists());

        $this->assertTrue(isset($object['data_first']));
        $this->assertFalse(isset($object['data_not_exist']));

        $this->assertTrue($object->isDataFirst($first));
        $this->assertFalse($object->isDataFirst('1'));

        $this->assertTrue($object->notDataFirst('1'));
        $this->assertFalse($object->notDataFirst($first));

        $this->assertTrue($object->isDataFirst(function ($key, $val) use ($first) {
            if ($val === $first) {
                return true;
            }
            return false;
        }));
        $this->assertTrue($object->notDataFirst(function ($key, $val) {
            if ($val !== self::IM_CHANGED) {
                return true;
            }
            return false;
        }));
    }

    /**
     * check add data by set* magic method with information about value exist and object changes
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testSetDataInObjectByMagicMethods($first, $second)
    {
        $object = $this->_simpleObject($first, $second);

        $this->assertFalse($object->hasDataThird());
        $this->assertFalse($object->dataChanged());

        $object->setDataThird(3);
        $object['data_fourth'] = 4;

        $this->assertTrue($object->hasDataThird());
        $this->assertTrue($object->has('data_fourth'));
        $this->assertTrue($object->dataChanged());

        $this->assertFalse($object->checkErrors());
    }

    /**
     * check add data by setData method with information about value exist and object changes
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testSetDataInObjectByDataMethod($first, $second)
    {
        $object = $this->_simpleObject($first, $second);

        $this->assertFalse($object->hasDataThird());
        $this->assertFalse($object->has('data_fourth'));
        $this->assertFalse($object->dataChanged());

        $object->appendData('data_third', 3);
        $object->appendData('data_fourth', 4);

        $this->assertTrue($object->hasDataThird());
        $this->assertTrue($object->has('data_fourth'));
        $this->assertTrue($object->dataChanged());

        $this->assertFalse($object->checkErrors());
    }

    /**
     * check removing and clearing data with information about value exist and object changes
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testRemovingData($first, $second)
    {
        $object = $this->_simpleObject($first, $second);

        $this->assertFalse($object->dataChanged());

        $object->clearDataFirst();
        $this->assertNull($object->getDataFirst());
        $this->assertTrue($object->hasDataFirst());

        unset($object['data_first']);
        $this->assertFalse($object->hasDataFirst());

        $object->unsetDataSecond();
        $this->assertNull($object->getDataSecond());
        $this->assertFalse($object->hasDataSecond());

        $this->assertTrue($object->dataChanged());
    }

    /**
     * check that access to non existing method will create error information
     */
    public function testAccessForNonExistingMethods()
    {
        $object = new Container();
        $object->executeNonExistingMethod();

        $this->assertTrue($object->checkErrors());
        $this->assertArrayHasKey('wrong_method', $object->returnObjectError());

        $object->removeObjectError();
        $this->assertFalse($object->checkErrors());
    }

    /**
     * check restore data for single key
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testDataRestorationForSingleData($first, $second)
    {
        $object = $this->_simpleObject($first, $second);

        $this->assertFalse($object->dataChanged());
        $this->assertFalse($object->keyDataChanged('data_first'));
        $object->setDataFirst('bar');

        $this->assertTrue($object->dataChanged());
        $this->assertEquals('bar', $object->getDataFirst());
        $this->assertEquals($first, $object->returnOriginalData('data_first'));
        $this->assertTrue($object->keyDataChanged('data_first'));

        $object->set('some_key', 'data');
        $this->assertNull($object->returnOriginalData('some_key'));

        $object->restoreDataFirst();
        $this->assertEquals($first, $object->getDataFirst());
        $this->assertTrue($object->dataChanged());
    }

    /**
     * check restoration for all data in object with change dataChanged value
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testFullDataRestoration($first, $second)
    {
        $object = $this->_simpleObject($first, $second);

        $this->assertFalse($object->dataChanged());
        $object->setDataFirst('bar');
        $object->setDataSecond('moo');
        $this->assertTrue($object->dataChanged());

        $object->restore();
        $this->assertEquals(
            $this->_getSimpleData($first, $second),
            $object->toArray()
        );
        $this->assertFalse($object->dataChanged());
    }

    /**
     * check set current data as original data
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testDataReplacement($first, $second)
    {
        $object = $this->_simpleObject($first, $second);

        $this->assertFalse($object->dataChanged());
        $object->setDataFirst('bar');
        $object->setDataSecond('moo');
        $this->assertTrue($object->dataChanged());

        $object->replaceDataArrays();

        $this->assertFalse($object->dataChanged());
    }

    /**
     * check usage object as array (access data and loop processing)
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testAccessToDataAsArray($first, $second)
    {
        $object = $this->_simpleObject($first, $second);

        foreach ($object as $key => $val) {
            if ($key === 'data_first') {
                $this->assertEquals($first, $val);
            }
            if ($key === 'data_second') {
                $this->assertEquals($second, $val);
            }
        }

        $this->assertEquals($object['data_first'], $first);

        $object[null] = 'some data';

        $this->assertEquals($object->get('integer_key_0'), 'some data');
    }

    /**
     * check access and setup data by object attributes
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testAccessToDataByAttributes($first, $second)
    {
        $object = $this->_simpleObject($first, $second);

        $this->assertEquals($object->data_first, $first);
        $this->assertNull($object->data_non_exists);

        $object->data_third = 'data third';
        $this->assertEquals($object->data_third, 'data third');
    }

    /**
     * check echoing of object
     * with separator changing
     *
     * @requires _simpleObject
     */
    public function testDisplayObjectAsStringWithSeparator()
    {
        $object = $this->_simpleObject('first data', 'second data');
        $this->assertEquals('first data, second data', (string)$object);

        $object->changeSeparator('; ');
        $this->assertEquals('first data; second data', (string)$object);

        $string = $object->toString(': ');
        $this->assertEquals('first data: second data', $string);

        $this->assertEquals(': ', $object->returnSeparator());
    }

    /**
     * allow to change data before insert for founded key using closure
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     */
    public function testDataPreparationOnEnter($first, $second)
    {
        $object = new Container();
        $object->putPreparationCallback('#data_[\w]+#', function ($key, $value) {
            if ($key === 'data_second') {
                return 'second';
            }

            $value .= '_modified';
            return $value;
        });

        $this->assertTrue($object->returnPreparationCallback()['#data_[\w]+#'] instanceof \Closure);

        $object->stopInputPreparation();

        $object->setDataFirst($first);
        $this->assertEquals($first, $object->getDataFirst());

        $object->startInputPreparation();

        $object->setDataFirst($first);
        $object->setDataSecond($second);

        $this->assertEquals($first . '_modified', $object->getDataFirst());
        $this->assertEquals('second', $object->toArray('data_second'));

        $object->removePreparationCallback('#data_[\w]+#');

        $this->assertEmpty($object->returnPreparationCallback('#data_[\w]+#'));
    }

    /**
     * allow to change data before return for founded key using closure
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     */
    public function testDataPreparationOnReturn($first, $second)
    {
        $object = new Container();
        $object->putReturnCallback('#data_[\w]+#', function ($key, $value) {
            if ($key === 'data_second') {
                return 'second';
            }

            $value .= '_modified';
            return $value;
        });

        $this->assertTrue($object->returnReturnCallback()['#data_[\w]+#'] instanceof \Closure);

        $object->stopOutputPreparation();

        $object->setDataFirst($first);
        $this->assertEquals($first, $object->getDataFirst());

        $object->startOutputPreparation();

        $object->setDataFirst($first);
        $object->setDataSecond($second);

        $this->assertEquals($first . '_modified', $object->getDataFirst());
        $this->assertEquals('second', $object->toArray('data_second'));

        $object->removeReturnCallback();

        $this->assertEmpty($object->returnReturnCallback('#data_[\w]+#'));
    }

    /**
     * allow to create object with given json string
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _exampleJsonData
     */
    public function testCreationWithJsonData($first, $second)
    {
        $jsonData = $this->_exampleJsonData($first, $second);

        $object = new Container([
            'data'  => $jsonData,
            'type'  => 'json',
        ]);

        $this->assertEquals($first, $object->getDataFirst());
        $this->assertEquals($second, $object->toArray('data_second'));
    }

    /**
     * allow to create object with given stdClass object
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _exampleStdData
     */
    public function testCreationWithStdClassData($first, $second)
    {
        $std = $this->_exampleStdData($first, $second);

        $object = new Container(['data' => $std]);

        $this->assertEquals($first, $object->getDataFirst());
        $this->assertEquals($second, $object->toArray('data_second'));
    }

    /**
     * allow to create object with given serialized array
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _exampleStdData
     */
    public function testCreationWithSerializedArray($first, $second)
    {
        $serialized = $this->_exampleSerializedData($first, $second);

        $object = new Container([
            'type'  => 'serialized',
            'data'  => $serialized,
        ]);

        $this->assertEquals($first, $object->getDataFirst());
        $this->assertEquals($second, $object->toArray('data_second'));
    }

    /**
     * allow to create object with given serialized object
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _exampleStdData
     */
    public function testCreationWithSerializedObject($first, $second)
    {
        $serialized = $this->_exampleSerializedData($first, $second, true);
        $object = new Container([
            'type'  => 'serialized',
            'data'  => $serialized,
        ]);

        $std = $object->getStdClass();
        $this->assertObjectHasAttribute('data_first', $std);
        $this->assertObjectHasAttribute('data_second', $std);
        $this->assertEquals($first, $object->toArray('std_class')->data_first);
        $this->assertEquals($second, $std->data_second);
    }

    /**
     * allow to create object with given xml data
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _exampleStdData
     */
    public function testCreationWithSimpleXml($first, $second)
    {
        $xml = $this->_exampleSimpleXmlData($first, $second);
        $object = new Container([
            'type'  => 'simple_xml',
            'data'  => $xml,
        ]);

        $this->assertXmlStringEqualsXmlString(
            $this->_exampleSimpleXmlData($first, $second),
            $object->toXml()
        );
        $this->assertXmlStringEqualsXmlString(
            $this->_exampleSimpleXmlData($first, $second),
            $object->toXml(false)
        );

        $this->assertEquals($this->_convertType($first), $object->getDataFirst());
        $this->assertEquals($this->_convertType($second), $object->toArray('data_second'));
    }

    /**
     * allow to create object with given xml data
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _exampleStdData
     */
    public function testCreationWithXml($first, $second)
    {
        $xml = $this->_exampleXmlData($first, $second);
        $object = new Container([
            'type'  => 'xml',
            'data'  => $xml,
        ]);

        $this->assertXmlStringEqualsXmlString(
            $this->_exampleXmlData($first, $second),
            $object->toXml()
        );
        $this->assertXmlStringEqualsXmlString(
            $this->_exampleXmlData($first, $second),
            $object->toXml(false)
        );

        $this->assertEquals($this->_convertType($first), $object->getDataFirst()[0]);
        $this->assertEquals(
            $this->_convertType($second),
            $object->getDataFirst()['@attributes']['data_second']
        );
    }

    /**
     * allow to create object with given json string and data preparation
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _exampleJsonData
     * @requires _dataPreparationCommon
     */
    public function testCreationWithJsonDataDataPreparation($first, $second)
    {
        $data = $this->_exampleJsonData($first, $second);
        $this->_dataPreparationCommon($first, $data, 'json');
    }

    /**
     * allow to create object with given std class and data preparation
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _exampleJsonData
     * @requires _dataPreparationCommon
     */
    public function testCreationWithStdClassDataDataPreparation($first, $second)
    {
        $data = $this->_exampleStdData($first, $second);
        $this->_dataPreparationCommon($first, $data, 'std');
    }

    /**
     * allow to create object with given serialized array and data preparation
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _exampleJsonData
     * @requires _dataPreparationCommon
     */
    public function testCreationWithSerializedArrayDataPreparation($first, $second)
    {
        $data = $this->_exampleSerializedData($first, $second);
        $this->_dataPreparationCommon($first, $data, 'serialized_array');
    }

    /**
     * allow to create object with given serialized object and data preparation
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _exampleJsonData
     * @requires _dataPreparationCommon
     */
    public function testCreationWithSerializedObjectDataPreparation($first, $second)
    {
        $data = $this->_exampleSerializedData($first, $second, true);

        $object             = new Container;
        $dataPreparation    = [
            '#^std_class#' => function ($key, $val) {
                $val->data_first = self::IM_CHANGED;
                return $val;
            }
        ];
        $object->putPreparationCallback($dataPreparation);
        $object->unserialize($data);

        $this->assertEquals(self::IM_CHANGED, $object->getStdClass()->data_first);
        $this->assertNotEquals($first, $object->getStdClass()->data_first);
    }

    /**
     * allow to create object with given simple xml data and data preparation
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _exampleJsonData
     * @requires _dataPreparationCommon
     */
    public function testCreationWithSimpleXmlDataPreparation($first, $second)
    {
        $data = $this->_exampleSimpleXmlData($first, $second);
        $this->_dataPreparationCommon($first, $data, 'simple_xml');
    }

    /**
     * allow to create object with given xml data and data preparation
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _exampleJsonData
     * @requires _dataPreparationCommon
     */
    public function testCreationWithXmlDataPreparation($first, $second)
    {
        $data = $this->_exampleXmlData($first, $second);
        $this->_dataPreparationCommon($first, $data, 'xml');
    }

    /**
     * export object as json data with data return callback
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     * @requires _exampleJsonData
     */
    public function testExportObjectAsJson($first, $second)
    {
        $data   = $this->_exampleJsonData($first, $second);
        $object = $this->_simpleObject($first, $second);

        $this->assertEquals($data, $object->toJson());

        $object->putReturnCallback([
            '#^data_first$#' => function () {
                return self::IM_CHANGED;
            }
        ]);
        $data = $this->_exampleJsonData(self::IM_CHANGED, $second);

        $this->assertEquals($data, $object->toJson());
    }

    /**
     * export object as std class data with data return callback
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     * @requires _exampleStdData
     */
    public function testExportObjectAsStdClass($first, $second)
    {
        $data   = $this->_exampleStdData($first, $second);
        $object = $this->_simpleObject($first, $second);

        $this->assertEquals($data, $object->toStdClass());

        $object->putReturnCallback([
            '#^data_first$#' => function () {
                return self::IM_CHANGED;
            }
        ]);
        $data = $this->_exampleStdData(self::IM_CHANGED, $second);

        $this->assertEquals($data, $object->toStdClass());
    }

    /**
     * export object as string (data separated by defined separator) data with data return callback
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     * @requires _exampleStdData
     */
    public function testExportObjectAsString($first, $second)
    {
        if (is_array($second)) {
            $second = implode(', ', $second);
        }
        $string = "$first, $second";

        $object = $this->_simpleObject($first, $second);
        $this->assertEquals($string, $object->toString());

        $object->putReturnCallback([
            '#^data_second$#' => function () {
                return self::IM_CHANGED;
            }
        ]);
        $string = "$first, " . self::IM_CHANGED;

        $this->assertEquals($string, (string)$object);
    }

    /**
     * export object as serialized string with data return callback
     * 
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     * @requires _exampleSerializedData
     */
    public function testExportObjectAsSerializedString($first, $second)
    {
        $data = $this->_exampleSerializedData($first, $second);
        $object = $this->_simpleObject($first, $second);

        $this->assertEquals($data, $object->serialize());

        $object->putReturnCallback([
            '#^data_first$#' => function () {
                return self::IM_CHANGED;
            }
        ]);
        $data = $this->_exampleSerializedData(self::IM_CHANGED, $second);

        $this->assertEquals($data, $object->serialize());
    }

    /**
     * export object as serialized string (with object) with data return callback
     * 
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _getSimpleData
     */
    public function testExportObjectAsSerializedStringWithObject($first, $second)
    {
        $object = new Container();
        $object->putPreparationCallback([
            '#^data_second$#' => function ($key, $data) {
                return (object)$data;
            }
        ]);
        $object->appendArray($this->_getSimpleData($first, $second));
        $data = $this->_exampleSerializedData($first, 'data_second: {;skipped_object;}');

        $this->assertEquals($data, $object->serialize(true));
    }

    /**
     * export object as xml with data return callback
     * 
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     * @requires _exampleSimpleXmlData
     */
    public function testExportObjectAsXml($first, $second)
    {
        $object = $this->_simpleObject($first, $second);
        $data   = $this->_exampleSimpleXmlData($first, $second);

        $object->putReturnCallback([
            '#^data_second$#' => function ($key, $val) {
                if (is_array($val)) {
                    return implode(',', $val);
                }

                return $val;
            },
            '#.*#' => function ($key, $val) {
                switch (true) {
                    case is_null($val):
                        return 'null';
                    case $val === true:
                        return 'true';
                    case $val === false:
                        return 'false';
                }

                return $val;
            }
        ]);
        $xml = $object->toXml(false);

        $this->assertXmlStringEqualsXmlString($data, $xml);
    }

    /**
     * test comparison of some data in object
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testDataComparison($first, $second)
    {
        $object = $this->_simpleObject($first, $second);

        $bool = $object->compareData('5', 'data_first', '===');
        $this->assertFalse($bool);

        $object->setDataFirst(5);
        $bool = $object->compareData(5, 'data_first', '==');
        $this->assertTrue($bool);

        $bool = $object->compareData('5', 'data_first', '===', true);
        $this->assertFalse($bool);

        $bool = $object->compareData(5, 'data_first', function ($key, $dataToCheck, $data) {
            if (is_int($dataToCheck) && $data[$key] === $dataToCheck) {
                return true;
            }
            return false;
        });
        $this->assertTrue($bool);

        $bool = $object->compareData('5', 'none_existing_key', '===', true);
        $this->assertFalse($bool);
    }

    /**
     * test other compare operators
     */
    public function testCompareOperators()
    {
        $object = new Container([
            'data' => [
                'first'     => 5,
                'second'    => true,
                'object'    => new Container,
            ]
        ]);

        $bool = $object->compareData(false, 'second', '!=');
        $this->assertTrue($bool);

        $bool = $object->compareData(false, 'second', '<>');
        $this->assertTrue($bool);

        $bool = $object->compareData(6, 'first', '>');
        $this->assertTrue($bool);

        $bool = $object->compareData(4, 'first', '<');
        $this->assertTrue($bool);

        $bool = $object->compareData(4, 'first', '<=');
        $this->assertTrue($bool);

        $bool = $object->compareData(6, 'first', '>=');
        $this->assertTrue($bool);

        $bool = $object->compareData('', 'first', 'unknown');
        $this->assertNull($bool);
    }

    /**
     * test comparison of whole objects
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testObjectComparison($first, $second)
    {
        $object         = $this->_simpleObject($first, $second);
        $newObject      = $this->_simpleObject($second, $first);
        $anotherObject  = $this->_simpleObject($second, $first);

        $bool = $object->compareData($newObject);
        $this->assertFalse($bool);

        $bool = $newObject->compareData($anotherObject);
        $this->assertTrue($bool);
    }

    /**
     * test method to walk on all elements in object and call on them given function
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testDataTraveling($first, $second)
    {
        if (is_array($second)) {
            $second[1] = [
                'special_1' => 0,
                'special_2' => 1,
            ];
        }

        $object     = $this->_simpleObject($first, $second);
        $function   = function ($key, $val) {
            if ($key === 0 && $val === 'bar') {
                return $val;
            }
            return self::IM_CHANGED;
        };

        $object->traveler($function, null, null, true);

        $this->assertEquals(self::IM_CHANGED, $object->getDataFirst());
        if (is_array($object->getDataSecond())) {
            $this->assertEquals(self::IM_CHANGED, $object->getDataSecond()[0]);
            $this->assertEquals(self::IM_CHANGED, $object->getDataSecond()[1]['special_1']);
        } else {
            $this->assertEquals(self::IM_CHANGED, $object->getDataSecond());
        }
    }

    /**
     * test object merging
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testObjectMerging($first, $second)
    {
        $object         = $this->_simpleObject($first, $second);
        $newObject      = $this->_simpleObject($second, $first);

        $object->mergeBlueObject($newObject);

        $this->assertEquals($first, $object->getDataSecond());
        if (is_array($object->getDataFirst())) {
            $this->assertEquals($second[0], $object->getDataFirst()[0]);
        } else {
            $this->assertEquals($second, $object->getDataFirst());
        }
    }

    /**
     * allow to create object with given csv string
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testCreationWithCsvData($first, $second)
    {
        $csv = $this->_exampleCsvData($first, $second);
        $csv = str_replace(',', ';', $csv);

        $object = new Container([
            'type'  => 'csv',
            'data'  => $csv,
        ]);

        $this->assertEquals('integer_key_', $object->returnIntegerKeyPrefix());
        $this->assertEquals($this->_convertType($first), $object->getIntegerKey0()[0]);

        if (count($second) > 1) {
            $this->assertEquals($second, $object->getIntegerKey1());
        } else {
            $this->assertEquals($this->_convertType($second), $object->getIntegerKey1()[0]);
        }
    }

    /**
     * allow to create object with given csv string
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testChangeCsvDelimiter($first, $second)
    {
        $object = new Container();
        $csv    = $this->_exampleCsvData($first, $second);

        $object->changeCsvDelimiter(',');
        $object->appendCsv($csv);

        $this->assertEquals($this->_convertType($first), $object->getIntegerKey0()[0]);

        if (count($second) > 1) {
            $this->assertEquals($second, $object->getIntegerKey1());
        } else {
            $this->assertEquals($this->_convertType($second), $object->getIntegerKey1()[0]);
        }
    }

    /**
     * test export object as csv
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testExportObjectAsCsvData($first, $second)
    {
        $object = $this->_simpleObject($this->_convertType($first), $this->_convertType($second));
        $object->changeCsvDelimiter(',');

        $this->assertEquals($this->_exampleCsvData($first, $second), $object->toCsv());
    }

    /**
     * allow to create object with given ini string
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     * @requires _exampleIniData
     */
    public function testCreationWithIniData($first, $second)
    {
        $object = new Container([
            'type'  => 'ini',
            'data'  => $this->_exampleIniData($first, $second),
        ]);

        $this->assertFalse($object->returnProcessIniSection());
        $this->assertEquals($this->_convertType($first), $object->getDataFirst());
        $this->assertEquals($this->_convertType($second), $object->getDataSecond());

        $object = new Container(['ini_section' => true]);
        $ini    = $this->_exampleIniData($first, $second, true);

        $this->assertTrue($object->returnProcessIniSection());
        $object->appendIni($ini);
        $this->assertEquals($this->_convertType($first), $object->getDataFirst());
        if (count($second) > 1) {
            $this->assertEquals($second, $object->getDataSecond());
        } else {
            $this->assertEquals(
                $this->_convertType($second),
                $object->getDataSecond()
            );
        }

//        $object->appendIni($brokenIni, true);
//        $this->assertTrue($object->checkErrors());
    }

    /**
     * test export object as ini
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     * @requires _exampleIniData
     */
    public function testExportObjectAsIniData($first, $second)
    {
        $object = $this->_simpleObject($this->_convertType($first), $this->_convertType($second));
        $ini    = $object->toIni();

        $this->assertEquals($this->_exampleIniData($first, $second), rtrim($ini));

        if (!is_array($second)) {
            $second = $this->_convertType($second);
        }
        $object = $this->_simpleObject($this->_convertType($first), $second);
        $object->processIniSection(true);
        $ini    = $object->toIni();

        $this->assertEquals(
            rtrim($this->_exampleIniData($first, $second, true)),
            rtrim($ini)
        );
    }

    /**
     * manual test for isset
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testIsSetData($first, $second)
    {
        $object = $this->_simpleObject($first, $second);

        $this->assertTrue($object->__isset('data_first'));
        $this->assertFalse($object->__isset('data_not_exist'));
    }

    /**
     * manual test for unset
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testUnsetData($first, $second)
    {
        $object = $this->_simpleObject($first, $second);

        $this->assertTrue($object->__isset('data_first'));
        $object->__unset('data_first');
        $this->assertFalse($object->__isset('data_first'));
    }

    /**
     * test object serialization with exception
     */
    public function testSerializeWithException()
    {
        $instance = new Container;
        $instance->set('object', new SerializeFail);
        $instance->serialize();

        $this->assertTrue($instance->checkErrors());
        $this->assertEquals($instance->returnObjectError()[0]['message'], 'test exception');
    }

    /**
     * test deprecated getData
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testGetData($first, $second)
    {
        $object = $this->_simpleObject($first, $second);

        $this->assertEquals(
            $this->_getSimpleData($first, $second),
            $object->getData()
        );
    }

    /**
     * test deprecated hasData and unsetData
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testHasData($first, $second)
    {
        $object = $this->_simpleObject($first, $second);

        $this->assertTrue($object->hasData('data_first'));
        $object->unsetData('data_first');
        $this->assertFalse($object->hasData('data_first'));
        $this->assertFalse($object->hasData('some_key'));
    }

    /**
     * check that get will return null for none existing key
     */
    public function testGetDataForNoneExistingKey()
    {
        $object = new Container;
        $object->stopOutputPreparation();
        $this->assertNull($object->get('some_key'));
    }

    /**
     * test deprecated restoreData
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testRestoreData($first, $second)
    {
        $object = $this->_simpleObject($first, $second);
        $object->set('new_data', 'a');

        $this->assertEquals($second, $object->get('data_second'));

        $object->destroy('data_second');

        $this->assertNull($object->get('data_second'));

        $object->restoreData('data_second');

        $this->assertEquals($second, $object->get('data_second'));
    }

    /**
     * test deprecated clearData
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testClearData($first, $second)
    {
        $object = $this->_simpleObject($first, $second);
        $object->clearData('data_first');

        $this->assertNull($object->get('data_first'));
        $this->assertTrue($object->has('data_first'));
    }

    /**
     * test deprecated setData
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @dataProvider baseDataProvider
     * @requires baseDataProvider
     * @requires _simpleObject
     */
    public function testSetData($first, $second)
    {
        $object = new Container;

        $this->assertNull($object->get('data_first'));

        $object->setData('data_first', $first);
        $object->setData('data_second', $second);

        $this->assertTrue($object->has('data_first'));
        $this->assertEquals($first, $object->get('data_first'));
    }

    /**
     * test try to execute none callable function
     */
    public function testReturnValueForUnCallableFunction()
    {
        $object = new Container([
            'preparation' => [
                '#data#' => 'im not callable'
            ]
        ]);

        $object->set('data', 'some data');

        $this->assertEquals('some data', $object->get('data'));
    }

    /**
     * test json that cannot be decoded
     */
    public function testAppendJsonWithError()
    {
        $object = new Container([
            'type' => 'json',
            'data' => 'json',
        ]);

        $this->assertEquals(true, $object->checkErrors());
    }

    /**
     * launch common object creation and assertion
     * 
     * @param mixed $first
     * @param mixed $data
     * @param string $type
     */
    protected function _dataPreparationCommon($first, $data, $type)
    {
        $object             = new Container;
        $dataPreparation    = [
            '#^data_first$#' => function () {
                return self::IM_CHANGED;
            }
        ];

        $object->putPreparationCallback($dataPreparation);
        switch ($type) {
            case 'json':
                $object->appendJson($data);
                break;
            case 'std':
                $object->appendStdClass($data);
                break;
            case 'serialized_array':
                $object->appendSerialized($data);
                break;
            case 'xml':
                $object->appendXml($data);
                break;
            case 'simple_xml':
                $object->appendSimpleXml($data);
                break;
        }

        $this->assertEquals(self::IM_CHANGED, $object->getDataFirst());
        $this->assertNotEquals($first, $object->getDataFirst());
    }

    /**
     * test unserialize with string (none array or object)
     */
    public function testAppendSerializeForStringData()
    {
        $object     = new Container;
        $string     = "Some string to \n serialize";
        $serialized = serialize($string);

        $object->appendSerialized($serialized);

        $this->assertEquals($string, $object->get('default'));
    }

    /**
     * return data for base example
     * 
     * @return array
     */
    public function baseDataProvider()
    {
        return [
            [1, 2],
            ['first', 'second'],
            [true, false],
            [null, ['foo', 'bar']],
        ];
    }

    /**
     * create simple object to test
     * 
     * @param mixed $first
     * @param mixed $second
     * @return \BlueContainer\Container
     */
    protected function _simpleObject($first, $second)
    {
        return new Container(['data' => $this->_getSimpleData($first, $second)]);
    }

    /**
     * return basic data to test
     * 
     * @param mixed $first
     * @param mixed $second
     * @return array
     */
    protected function _getSimpleData($first, $second)
    {
        return [
            'data_first'    => $first,
            'data_second'   => $second,
        ];
    }

    /**
     * create simple csv data to test
     *
     * @param mixed $first
     * @param mixed $second
     * @return string
     */
    protected function _exampleCsvData($first, $second)
    {
        $first  = $this->_convertType($first);
        $second = $this->_convertType($second);
        $csv    = $first . "\n" . $second;

        return $csv;
    }

    /**
     * create simple ini data to test
     *
     * @param mixed $first
     * @param mixed $second
     * @param bool $section
     * @return string
     */
    protected function _exampleIniData($first, $second, $section = false)
    {
        $ini    = '';
        $first  = $this->_convertType($first);
        $ini    .= 'data_first = ' . $first . "\n";

        if ($section && is_array($second)) {
            $ini .= '[data_second]' . "\n";
            foreach ($second as $key => $data) {
                $ini .= $key . ' = ' . $data . "\n";
            }
        } else {
            $second = $this->_convertType($second);
            $ini .= 'data_second = ' . $second;
        }

        return $ini;
    }

    /**
     * create simple xml data to test
     *
     * @param mixed $first
     * @param mixed $second
     * @return string
     */
    protected function _exampleSimpleXmlData($first, $second)
    {
        $first  = $this->_convertType($first);
        $second = $this->_convertType($second);

        $xml = "<?xml version='1.0' encoding='UTF-8'?>
            <root>
                <data_first>$first</data_first>
                <data_second>$second</data_second>
            </root>";

        return $xml;
    }

    /**
     * create xml data to test
     *
     * @param mixed $first
     * @param mixed $second
     * @return string
     */
    protected function _exampleXmlData($first, $second)
    {
        $first  = $this->_convertType($first);
        $second = $this->_convertType($second);

        $xml = "<?xml version='1.0' encoding='UTF-8'?>
            <root>
                <data_first data_second='$second'>$first</data_first>
            </root>";

        return $xml;
    }

    /**
     * allow to convert arrays or boolean information to string
     * 
     * @param mixed $variable
     * @return string
     */
    protected function _convertType($variable)
    {
        switch (true) {
            case is_null($variable):
                $converted = 'null';
                break;

            case is_array($variable):
                $converted = implode(',', $variable);
                break;

            case is_bool($variable):
                $converted = var_export($variable, true);
                break;

            default:
                $converted = $variable;
                break;
        }

        return $converted;
    }

    /**
     * create json data to test
     * 
     * @param mixed $first
     * @param mixed $second
     * @return string
     */
    protected function _exampleJsonData($first, $second)
    {
        return json_encode($this->_getSimpleData($first, $second));
    }

    /**
     * create serialized string to test
     *
     * @param mixed $first
     * @param mixed $second
     * @param bool @object
     * @return string
     */
    protected function _exampleSerializedData($first, $second, $object = false)
    {
        if ($object) {
            return Serializer::serialize((object)$this->_getSimpleData($first, $second));
        }

        return Serializer::serialize($this->_getSimpleData($first, $second));
    }

    /**
     * create std object to test
     *
     * @param mixed $first
     * @param mixed $second
     * @return \stdClass
     */
    protected function _exampleStdData($first, $second)
    {
        $std                = new \stdClass;
        $std->data_first    = $first;
        $std->data_second   = $second;

        return $std;
    }
}
