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
use Laminas\Serializer\Adapter\PhpSerialize;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ContainerTest extends TestCase
{
    /**
     * prefix for some changed data
     */
    public const IM_CHANGED = 'im changed';

    /**
     * check data validation
     *
     * @throws \ReflectionException
     */
    public function testDataValidation(): void
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
        $this->assertEquals([
            "message" => "validation_mismatch",
            "key"=> "data_first",
            "data"=> "first data",
            "rule"=> "#^[\\d]+$#"
        ], $object->returnObjectError()[0]);
        $this->assertEquals('validation_mismatch', $object->returnObjectError()[1]['message']);
        $this->assertEquals('data_third', $object->returnObjectError()[1]['key']);
        $this->assertEquals('third data', $object->returnObjectError()[1]['data']);
        $this->assertCount(2, $object->returnObjectError());

        $object->removeValidationRule();

        $this->assertNull($object->returnValidationRule('#data_first#'));
    }

    /**
     * check data validation in constructor
     *
     * @throws \ReflectionException
     */
    public function testDataValidationInConstructor(): void
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
     *
     * @throws \ReflectionException
     */
    public function testDataPreparationInConstructor(): void
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
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testCreateSimpleObject(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($first, $second);

        $this->assertFalse($object->checkErrors());
        $this->assertEmpty($object->returnObjectError());
    }

    /**
     * check data returned by get* methods
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testGetDataFromObject(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($first, $second);

        $this->assertEquals($first, $object->getDataFirst());
        $this->assertEquals($second, $object->toArray('data_second'));
        $this->assertEquals($second, $object['data_second']);
        $this->assertNull($object->getDataNotExists());

        $this->assertEquals(
            self::getSimpleData($first, $second),
            $object->toArray()
        );
    }

    /**
     * check data with has*, is* and not* magic methods
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testCheckingData(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($first, $second);

        $this->assertTrue($object->hasDataFirst());
        $this->assertFalse($object->hasDataNotExists());

        $this->assertTrue(isset($object['data_first']));
        $this->assertFalse(isset($object['data_not_exist']));

        $this->assertTrue($object->isDataFirst($first));
        $this->assertFalse($object->isDataFirst('1'));

        $this->assertTrue($object->notDataFirst('1'));
        $this->assertFalse($object->notDataFirst($first));

        $this->assertTrue($object->isDataFirst(function ($key, $val) use ($first) {
            return $val === $first;
        }));
        $this->assertTrue($object->notDataFirst(function ($key, $val) {
            return $val !== self::IM_CHANGED;
        }));
    }

    /**
     * check add data by set* magic method with information about value exist and object changes
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testSetDataInObjectByMagicMethods(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($first, $second);

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
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testSetDataInObjectByDataMethod(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($first, $second);

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
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testRemovingData(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($first, $second);

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
    public function testAccessForNonExistingMethods(): void
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
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testDataRestorationForSingleData(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($first, $second);

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
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testFullDataRestoration(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($first, $second);

        $this->assertFalse($object->dataChanged());
        $object->setDataFirst('bar');
        $object->setDataSecond('moo');
        $this->assertTrue($object->dataChanged());

        $object->restore();
        $this->assertEquals(
            self::getSimpleData($first, $second),
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
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testDataReplacement(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($first, $second);

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
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testAccessToDataAsArray(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($first, $second);

        foreach ($object as $key => $val) {
            if ($key === 'data_first') {
                $this->assertEquals($first, $val);
            }
            if ($key === 'data_second') {
                $this->assertEquals($second, $val);
            }
        }

        $this->assertEquals($first, $object['data_first']);

        $object[null] = 'some data';

        $this->assertEquals('some data', $object->get('integer_key_0'));
    }

    /**
     * check access and setup data by object attributes
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */#[DataProvider('baseDataProvider')]
    public function testAccessToDataByAttributes(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($first, $second);

        $this->assertEquals($first, $object->data_first);
        $this->assertNull($object->data_non_exists);

        $object->data_third = 'data third';
        $this->assertEquals('data third', $object->data_third);
    }

    /**
     * check echoing of object
     * with separator changing
     *
     * @requires simpleObject
     */
    public function testDisplayObjectAsStringWithSeparator(): void
    {
        $object = $this->simpleObject('first data', 'second data');
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
     */
    #[DataProvider('baseDataProvider')]
    public function testDataPreparationOnEnter(mixed $first, mixed $second): void
    {
        $object = new Container();
        $object->putPreparationCallback('#data_[\w]+#', function ($key, $value) {
            if ($key === 'data_second') {
                return 'second';
            }

            $value .= '_modified';
            return $value;
        });

        $this->assertInstanceOf(\Closure::class, $object->returnPreparationCallback()['#data_[\w]+#']);

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
     */
    #[DataProvider('baseDataProvider')]
    public function testDataPreparationOnReturn(mixed $first, mixed $second): void
    {
        $object = new Container();
        $object->putReturnCallback('#data_[\w]+#', function ($key, $value) {
            if ($key === 'data_second') {
                return 'second';
            }

            $value .= '_modified';
            return $value;
        });

        $this->assertInstanceOf(\Closure::class, $object->returnReturnCallback()['#data_[\w]+#']);

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
     * @throws \ReflectionException|\JsonException
     */
    #[DataProvider('baseDataProvider')]
    public function testCreationWithJsonData(mixed $first, mixed $second): void
    {
        $jsonData = self::exampleJsonData($first, $second);

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
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testCreationWithStdClassData(mixed $first, mixed $second): void
    {
        $std = $this->exampleStdData($first, $second);

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
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testCreationWithSerializedArray(mixed $first, mixed $second): void
    {
        $serialized = $this->exampleSerializedData($first, $second);

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
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testCreationWithSerializedObject(mixed $first, mixed $second): void
    {
        $serialized = $this->exampleSerializedData($first, $second, true);
        $object = new Container([
            'type'  => 'serialized',
            'data'  => $serialized,
        ]);

        $std = $object->getStdClass();
        $this->assertObjectHasProperty('data_first', $std);
        $this->assertObjectHasProperty('data_second', $std);
        $this->assertEquals($first, $object->toArray('std_class')->data_first);
        $this->assertEquals($second, $std->data_second);
    }

    /**
     * allow to create object with given xml data
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     * @throws \DOMException
     */
    #[DataProvider('baseDataProvider')]
    public function testCreationWithSimpleXml(mixed $first, mixed $second): void
    {
        $xml = $this->exampleSimpleXmlData($first, $second);
        $object = new Container([
            'type'  => 'simple_xml',
            'data'  => $xml,
        ]);

        $this->assertXmlStringEqualsXmlString(
            $this->exampleSimpleXmlData($first, $second),
            $object->toXml()
        );
        $this->assertXmlStringEqualsXmlString(
            $this->exampleSimpleXmlData($first, $second),
            $object->toXml(false)
        );

        $this->assertEquals($this->convertType($first), $object->getDataFirst());
        $this->assertEquals($this->convertType($second), $object->toArray('data_second'));
    }

    /**
     * allow to create object with given xml data
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     * @throws \DOMException
     */
    #[DataProvider('baseDataProvider')]
    public function testCreationWithXml(mixed $first, mixed $second): void
    {
        $xml = $this->exampleXmlData($first, $second);
        $object = new Container([
            'type'  => 'xml',
            'data'  => $xml,
        ]);

        $this->assertXmlStringEqualsXmlString(
            $this->exampleXmlData($first, $second),
            $object->toXml()
        );
        $this->assertXmlStringEqualsXmlString(
            $this->exampleXmlData($first, $second),
            $object->toXml(false)
        );
        $this->assertXmlStringEqualsXmlString(
            $this->exampleXmlDataDtd($first, $second),
            $object->toXml(false, __DIR__ . '/testDtd.dtd')
        );

        $this->assertEquals($this->convertType($first), $object->getDataFirst()[0]);
        $this->assertEquals(
            $this->convertType($second),
            $object->getDataFirst()['@attributes']['data_second']
        );
    }

    /**
     * allow to create object with given json string and data preparation
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException|\JsonException
     */
    #[DataProvider('baseDataProvider')]
    public function testCreationWithJsonDataDataPreparation(mixed $first, mixed $second): void
    {
        $data = self::exampleJsonData($first, $second);
        $this->dataPreparationCommon($first, $data, 'json');
    }

    /**
     * allow to create object with given std class and data preparation
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testCreationWithStdClassDataDataPreparation(mixed $first, mixed $second): void
    {
        $data = $this->exampleStdData($first, $second);
        $this->dataPreparationCommon($first, $data, 'std');
    }

    /**
     * allow to create object with given serialized array and data preparation
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testCreationWithSerializedArrayDataPreparation(mixed $first, mixed $second): void
    {
        $data = $this->exampleSerializedData($first, $second);
        $this->dataPreparationCommon($first, $data, 'serialized_array');
    }

    /**
     * allow to create object with given serialized object and data preparation
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testCreationWithSerializedObjectDataPreparation(mixed $first, mixed $second): void
    {
        $data = $this->exampleSerializedData($first, $second, true);

        $object             = new Container();
        $dataPreparation    = [
            '#^std_class#' => function ($key, $val) {
                $val->data_first = self::IM_CHANGED;
                return $val;
            }
        ];
        $object->putPreparationCallback($dataPreparation);
        $object->unserialize($data);

        $this->assertEquals(self::IM_CHANGED, $object->getStdClass()->data_first);
        $this->assertNotSame($first, $object->getStdClass()->data_first);
    }

    /**
     * allow to create object with given simple xml data and data preparation
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testCreationWithSimpleXmlDataPreparation(mixed $first, mixed $second): void
    {
        $data = $this->exampleSimpleXmlData($first, $second);
        $this->dataPreparationCommon($first, $data, 'simple_xml');
    }

    /**
     * allow to create object with given xml data and data preparation
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testCreationWithXmlDataPreparation(mixed $first, mixed $second): void
    {
        $data = $this->exampleXmlData($first, $second);
        $this->dataPreparationCommon($first, $data, 'xml');
    }

    /**
     * export object as json data with data return callback
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \JsonException|\ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testExportObjectAsJson(mixed $first, mixed $second): void
    {
        $data   = self::exampleJsonData($first, $second);
        $object = $this->simpleObject($first, $second);

        $this->assertEquals($data, $object->toJson());

        $object->putReturnCallback([
            '#^data_first$#' => function () {
                return self::IM_CHANGED;
            }
        ]);
        $data = self::exampleJsonData(self::IM_CHANGED, $second);

        $this->assertEquals($data, $object->toJson());
    }

    /**
     * export object as std class data with data return callback
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testExportObjectAsStdClass(mixed $first, mixed $second): void
    {
        $data   = $this->exampleStdData($first, $second);
        $object = $this->simpleObject($first, $second);

        $this->assertEquals($data, $object->toStdClass());

        $object->putReturnCallback([
            '#^data_first$#' => function () {
                return self::IM_CHANGED;
            }
        ]);
        $data = $this->exampleStdData(self::IM_CHANGED, $second);

        $this->assertEquals($data, $object->toStdClass());
    }

    /**
     * export object as string (data separated by defined separator) data with data return callback
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testExportObjectAsString(mixed $first, mixed $second): void
    {
        if (is_array($second)) {
            $second = implode(', ', $second);
        }
        $string = "$first, $second";

        $object = $this->simpleObject($first, $second);
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
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testExportObjectAsSerializedString(mixed $first, mixed $second): void
    {
        $data = $this->exampleSerializedData($first, $second);
        $object = $this->simpleObject($first, $second);

        $this->assertEquals($data, $object->serialize());

        $object->putReturnCallback([
            '#^data_first$#' => function () {
                return self::IM_CHANGED;
            }
        ]);
        $data = $this->exampleSerializedData(self::IM_CHANGED, $second);

        $this->assertEquals($data, $object->serialize());
    }

    /**
     * export object as serialized string (with object) with data return callback
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testExportObjectAsSerializedStringWithObject(mixed $first, mixed $second): void
    {
        $object = new Container();
        $object->putPreparationCallback([
            '#^data_second$#' => function ($key, $data) {
                return (object)$data;
            }
        ]);
        $object->appendArray(self::getSimpleData($first, $second));
        $data = $this->exampleSerializedData($first, 'data_second: {;skipped_object;}');
        $this->assertEquals(1,1);

        $this->assertEquals($data, $object->serialize(true));
    }

    /**
     * export object as xml with data return callback
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \DOMException
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testExportObjectAsXml(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($first, $second);
        $data   = $this->exampleSimpleXmlData($first, $second);

        $object->putReturnCallback([
            '#^data_second$#' => function ($key, $val) {
                if (is_array($val)) {
                    return implode(',', $val);
                }

                return $val;
            },
            '#.*#' => function ($key, $val) {
                return match (true) {
                    is_null($val) => 'null',
                    $val === true => 'true',
                    $val === false => 'false',
                    default => $val,
                };

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
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testDataComparison(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($first, $second);

        $bool = $object->compareData('5', 'data_first');
        $this->assertFalse($bool);

        $object->setDataFirst(5);
        $bool = $object->compareData(5, 'data_first', '==');
        $this->assertTrue($bool);

        $bool = $object->compareData('5', 'data_first', '===', true);
        $this->assertFalse($bool);

        $bool = $object->compareData(5, 'data_first', function ($key, $dataToCheck, $data) {
            return is_int($dataToCheck) && $data[$key] === $dataToCheck;
        });
        $this->assertTrue($bool);

        $bool = $object->compareData('5', 'none_existing_key', '===', true);
        $this->assertFalse($bool);
    }

    /**
     * test other compare operators
     *
     * @throws \ReflectionException
     */
    public function testCompareOperators(): void
    {
        $object = new Container([
            'data' => [
                'first'     => 5,
                'second'    => true,
                'object'    => new Container(),
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
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testObjectComparison(mixed $first, mixed $second): void
    {
        $object         = $this->simpleObject($first, $second);
        $newObject      = $this->simpleObject($second, $first);
        $anotherObject  = $this->simpleObject($second, $first);

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
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testDataTraveling(mixed $first, mixed $second): void
    {
        if (is_array($second)) {
            $second[1] = [
                'special_1' => 0,
                'special_2' => 1,
            ];
        }

        $object     = $this->simpleObject($first, $second);
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
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testObjectMerging(mixed $first, mixed $second): void
    {
        $object         = $this->simpleObject($first, $second);
        $newObject      = $this->simpleObject($second, $first);

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
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testCreationWithCsvData(mixed $first, mixed $second): void
    {
        $csv = $this->exampleCsvData($first, $second);
        $csv = str_replace(',', ';', $csv);

        $object = new Container([
            'type'  => 'csv',
            'data'  => $csv,
        ]);

        $this->assertEquals('integer_key_', $object->returnIntegerKeyPrefix());
        $this->assertEquals($this->convertType($first), $object->getIntegerKey0()[0]);

        if (\is_array($second) && \count($second) > 1) {
            $this->assertEquals($second, $object->getIntegerKey1());
        } else {
            $this->assertEquals($this->convertType($second), $object->getIntegerKey1()[0]);
        }
    }

    /**
     * allow to create object with given csv string
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testChangeCsvDelimiter(mixed $first, mixed $second): void
    {
        $object = new Container();
        $csv    = $this->exampleCsvData($first, $second);

        $object->changeCsvDelimiter(',');
        $object->changeCsvEnclosure("'");
        $object->changeCsvEscape("|");
        $object->appendCsv($csv);

        $this->assertEquals($this->convertType($first), $object->getIntegerKey0()[0]);
        $this->assertEquals(',', $object->returnCsvDelimiter());
        $this->assertEquals("'", $object->returnCsvEnclosure());
        $this->assertEquals("|", $object->returnCsvEscape());


        if (\is_array($second) && \count($second) > 1) {
            $this->assertEquals($second, $object->getIntegerKey1());
        } else {
            $this->assertEquals($this->convertType($second), $object->getIntegerKey1()[0]);
        }

        $object2 = new Container();
        $object2->changeCsvLineDelimiter(";");
        $object2->changeCsvDelimiter(',');
        $csvChanged = str_replace("\n", ";", $csv);
        $object2->appendCsv($csvChanged);

        $this->assertEquals(";", $object2->returnCsvLineDelimiter());

        if (\is_array($second) && \count($second) > 1) {
            $this->assertEquals($second, $object2->getIntegerKey1());
        } else {
            $this->assertEquals($this->convertType($second), $object2->getIntegerKey1()[0]);
        }
    }

    /**
     * test export object as csv
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testExportObjectAsCsvData(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($this->convertType($first), $this->convertType($second));
        $object->changeCsvDelimiter(',');

        $this->assertEquals($this->exampleCsvData($first, $second), $object->toCsv());
        
        $object2 = new Container();
        $object2->changeCsvDelimiter(',');
        $csv = $this->exampleCsvData($first, $second);
        $object2->appendCsv($csv);

        if ($first === true) {
            $first = 'true';
            $second = 'false';
        }

        if (\is_array($second) && \count($second) > 1) {
            $second = implode(',', $second);
        }

        if (is_null($first)) {
            $first = 'null';
        }

        $this->assertEquals("$first\n$second", $object2->toCsv());
    }

    /**
     * allow to create object with given ini string
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testCreationWithIniData(mixed $first, mixed $second): void
    {
        $object = new Container([
            'type'  => 'ini',
            'data'  => $this->exampleIniData($first, $second),
        ]);

        $this->assertFalse($object->returnProcessIniSection());
        $this->assertEquals($this->convertType($first), $object->getDataFirst());
        $this->assertEquals($this->convertType($second), $object->getDataSecond());

        $object = new Container(['ini_section' => true]);
        $ini    = $this->exampleIniData($first, $second, true);

        $this->assertTrue($object->returnProcessIniSection());
        $object->appendIni($ini);
        $this->assertEquals($this->convertType($first), $object->getDataFirst());

        if (\is_array($second) && \count($second) > 1) {
            $this->assertEquals($second, $object->getDataSecond());
        } else {
            $this->assertEquals(
                $this->convertType($second),
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
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testExportObjectAsIniData(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($this->convertType($first), $this->convertType($second));
        $ini    = $object->toIni();

        $this->assertEquals($this->exampleIniData($first, $second), rtrim($ini));

        if (!is_array($second)) {
            $second = $this->convertType($second);
        }
        $object = $this->simpleObject($this->convertType($first), $second);
        $object->processIniSection(true);
        $ini    = $object->toIni();

        $this->assertEquals(
            rtrim($this->exampleIniData($first, $second, true)),
            rtrim($ini)
        );
    }

    /**
     * manual test for isset
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testIsSetData(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($first, $second);

        $this->assertTrue($object->__isset('data_first'));
        $this->assertFalse($object->__isset('data_not_exist'));
    }

    /**
     * manual test for unset
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testUnsetData(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($first, $second);

        $this->assertTrue($object->__isset('data_first'));
        $object->__unset('data_first');
        $this->assertFalse($object->__isset('data_first'));
    }

    /**
     * test object serialization with exception
     *
     * @throws \ReflectionException
     */
    public function testSerializeWithException(): void
    {
        $instance = new Container();
        $instance->set('object', new SerializeFail());
        $instance->serialize();

        $this->assertTrue($instance->checkErrors());
        $this->assertEquals('test exception', $instance->returnObjectError()[0]['message']);

        restore_error_handler();
    }

    /**
     * test deprecated getData
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testGetData(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($first, $second);

        $this->assertEquals(
            self::getSimpleData($first, $second),
            $object->getData()
        );
    }

    /**
     * test deprecated hasData and unsetData
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testHasData(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($first, $second);

        $this->assertTrue($object->hasData('data_first'));
        $object->unsetData('data_first');
        $this->assertFalse($object->hasData('data_first'));
        $this->assertFalse($object->hasData('some_key'));
    }

    /**
     * check that get will return null for none existing key
     */
    public function testGetDataForNoneExistingKey(): void
    {
        $object = new Container();
        $object->stopOutputPreparation();
        $this->assertNull($object->get('some_key'));
    }

    /**
     * test deprecated restoreData
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testRestoreData(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($first, $second);
        $object->set('new_data', 'a');

        $this->assertEquals($second, $object->get('data_second'));

        $object->destroy('data_second');

        $this->assertNull($object->get('data_second'));

        $object->restoreData('data_second');

        $this->assertEquals($second, $object->get('data_second'));
    }

    /**
     * test deprecated restoreData
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testDestroyAll(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($first, $second);
        $object->set('new_data', 'a');

        $this->assertEquals($second, $object->get('data_second'));

        $object->destroy();

        $this->assertNull($object->get('data_second'));
        $this->assertNull($object->get('data_first'));

        $object->restoreData('data_second');

        $this->assertEquals($second, $object->get('data_second'));
    }

    /**
     * test deprecated clearData
     *
     * @param mixed $first
     * @param mixed $second
     *
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testClearData(mixed $first, mixed $second): void
    {
        $object = $this->simpleObject($first, $second);
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
     * @throws \ReflectionException
     * @throws \ReflectionException
     */
    #[DataProvider('baseDataProvider')]
    public function testSetData(mixed $first, mixed $second): void
    {
        $object = new Container();

        $this->assertNull($object->get('data_first'));

        $object->setData('data_first', $first);
        $object->setData('data_second', $second);

        $this->assertTrue($object->has('data_first'));
        $this->assertEquals($first, $object->get('data_first'));
    }

    /**
     * test try to execute none callable function
     *
     * @throws \ReflectionException
     */
    public function testReturnValueForUnCallableFunction(): void
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
     *
     * @throws \ReflectionException
     */
    public function testAppendJsonWithError(): void
    {
        $object = new Container([
            'type' => 'json',
            'data' => 'json',
        ]);

        $this->assertTrue($object->checkErrors());
    }

    /**
     * launch common object creation and assertion
     *
     * @param mixed $first
     * @param mixed $data
     * @param string $type
     * @throws \ReflectionException
     */
    protected function dataPreparationCommon(mixed $first, mixed $data, string $type): void
    {
        $object             = new Container();
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
        $this->assertNotSame($object->getDataFirst(), $first);
    }

    /**
     * @throws \ReflectionException
     * @throws \DOMException
     */
    public function testAppendXmlWithObject(): void
    {
        $object = new Container();
        $std = $this->exampleStdData('foo', 'bar');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<root>
  <object_data serialized_object="1"><![CDATA[O:8:"stdClass":2:{s:10:"data_first";s:3:"foo";s:11:"data_second";s:3:"bar";}]]></object_data>
</root>
';

        $object->set('object_data', $std);
        $this->assertEquals($xml, $object->toXml());

        $object2 = new Container();
        $object2->appendXml($xml);

        $this->assertEquals($std, $object2->getObjectData());
        
    }

    /**
     * @throws \ReflectionException
     * @throws \DOMException
     */
    public function testMultidimensionalArrayToXml(): void
    {
        $object = new Container();
        $std = [
            'data_first' => [
                'foo',
                'bar'
            ],
            'data_second' => [
                'bar' => ['a', 'b'],
                'baz' => ['c', 'd']
            ],
        ];
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<root>
  <array_data>
    <data_first>
      <integer_key_0><![CDATA[foo]]></integer_key_0>
      <integer_key_1><![CDATA[bar]]></integer_key_1>
    </data_first>
    <data_second>
      <bar>
        <integer_key_0><![CDATA[a]]></integer_key_0>
        <integer_key_1><![CDATA[b]]></integer_key_1>
      </bar>
      <baz>
        <integer_key_0><![CDATA[c]]></integer_key_0>
        <integer_key_1><![CDATA[d]]></integer_key_1>
      </baz>
    </data_second>
  </array_data>
</root>
';

        $object->set('array_data', $std);
        $this->assertEquals($xml, $object->toXml());

        $object2 = new Container();
        $object2->appendXml($xml);

        $this->assertEquals($std, $object2->getArrayData());
    }

    /**
     * test unserialize with string (none array or object)
     *
     * @throws \ReflectionException
     */
    public function testAppendSerializeForStringData(): void
    {
        $object     = new Container();
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
    public static function baseDataProvider(): array
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
     * @return Container
     * @throws \ReflectionException
     */
    public function simpleObject(mixed $first, mixed $second): Container
    {
        return new Container(['data' => self::getSimpleData($first, $second)]);
    }

    /**
     * return basic data to test
     * 
     * @param mixed $first
     * @param mixed $second
     * @return array
     */
    public static function getSimpleData(mixed $first, mixed $second): array
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
    public function exampleCsvData(mixed $first, mixed $second): string
    {
        $first  = $this->convertType($first);
        $second = $this->convertType($second);
        return $first . "\n" . $second;
    }

    /**
     * create simple ini data to test
     *
     * @param mixed $first
     * @param mixed $second
     * @param bool $section
     * @return string
     */
    public function exampleIniData(mixed $first, mixed $second, bool $section = false): string
    {
        $ini    = '';
        $first  = $this->convertType($first);
        $ini    .= 'data_first = ' . $first . "\n";

        if ($section && is_array($second)) {
            $ini .= '[data_second]' . "\n";
            foreach ($second as $key => $data) {
                $ini .= $key . ' = ' . $data . "\n";
            }
        } else {
            $second = $this->convertType($second);
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
    public function exampleSimpleXmlData(mixed $first, mixed $second): string
    {
        $first  = $this->convertType($first);
        $second = $this->convertType($second);

        return "<?xml version='1.0' encoding='UTF-8'?>
            <root>
                <data_first>$first</data_first>
                <data_second>$second</data_second>
            </root>";
    }

    /**
     * create xml data to test
     *
     * @param mixed $first
     * @param mixed $second
     * @return string
     */
    public function exampleXmlData(mixed $first, mixed $second): string
    {
        $first  = $this->convertType($first);
        $second = $this->convertType($second);

        return "<?xml version='1.0' encoding='UTF-8'?>
            <root>
                <data_first data_second='$second'>$first</data_first>
            </root>";
    }

    /**
     * create xml data to test
     *
     * @param mixed $first
     * @param mixed $second
     * @return string
     */
    public function exampleXmlDataDtd(mixed $first, mixed $second): string
    {
        $first  = $this->convertType($first);
        $second = $this->convertType($second);

        return "<?xml version='1.0' encoding='UTF-8'?>
            <!DOCTYPE root SYSTEM \"/var/www/html/tests/Test/testDtd.dtd\">
            <root>
                <data_first data_second='$second'>$first</data_first>
            </root>";
    }

    /**
     * allow to convert arrays or boolean information to string
     * 
     * @param mixed $variable
     * @return string
     */
    protected function convertType(mixed $variable): string
    {
        return match (true) {
            is_null($variable) => 'null',
            is_array($variable) => implode(',', $variable),
            is_bool($variable) => var_export($variable, true),
            default => $variable,
        };
    }

    /**
     * create json data to test
     *
     * @param mixed $first
     * @param mixed $second
     * @return string
     * @throws \JsonException
     */
    public static function exampleJsonData(mixed $first, mixed $second): string
    {
        return json_encode(self::getSimpleData($first, $second), JSON_THROW_ON_ERROR);
    }

    /**
     * create serialized string to test
     *
     * @param mixed $first
     * @param mixed $second
     * @param bool $object @object
     * @return string
     */
    public function exampleSerializedData(mixed $first, mixed $second, bool $object = false): string
    {
        $serializer = new PhpSerialize();

        if ($object) {
            return $serializer->serialize((object)self::getSimpleData($first, $second));
        }

        return $serializer->serialize(self::getSimpleData($first, $second));
    }

    /**
     * create std object to test
     *
     * @param mixed $first
     * @param mixed $second
     * @return \stdClass
     */
    public function exampleStdData(mixed $first, mixed $second): \stdClass
    {
        $std                = new \stdClass();
        $std->data_first    = $first;
        $std->data_second   = $second;

        return $std;
    }
}
