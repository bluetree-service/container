BlueContainer (as trait and Object implementation)
====================

Idea of usage
--------------
BlueContainer is one of main objects, used to store data as object. It allows easily
set and access to data by magical methods. If we try to access data that dos't
exist inside of object, we always get `null` value.

### Storage data
All data in object is stored in special
**protected** `$_DATA` variable and accessible by magic methods or by giving variable
key. Also Container store original data, before changes.

### Data key naming
Container has build in method that convert keys between underscore and camel case.  
If we use key names as method attributes we always must use an underscore naming.
Camel case naming is used only if we try to access to data by giving his key name
as method name.

### Trait
Because whole logic is inside of `trait BlueContainer` we can inject all logic into
every object that we want to. Also BlueContainer `__constructor` has lunch two special
methods `initializeObject` and `afterInitializeObject` that can help with lunch
new object without `__construct` method.  
If we want to have only simple BlueContainer implementation use for that `Container`
class (_BlueContainer\Container_)

Basic Usage
--------------

### Create Container
Constructor accept only one optional parameter `options` that must be an array,
where we give data to set as **data** key (array or string) and second with data
**type** (_json_, _xml_, _simple_xml_, _serialized_). Default type is `array`.  
Data given in that way into container automatically is treat as original data.

**Do not store data with keyword** `data` **that will cause some problems**  
In constructor `data` keyword is used to recognize that given array is list of data or data with specified type.  
In magic methods `data` cannot be used because there are some specials methods like `getData` or `setData` that won't return correct value in that case.

```php
$object = new Container([
    'data' => [
        'variable_key' => 'some data to set'
    ]
]);
```
or
```php
$json = '{"first_data":"a","second_data":"b","third_data":"c"}';
$objectJson = new Container([
    'data' => $json,
    'type' => 'json'
]);
```

### Xml data
Container can parse xml data in two ways. The fastest is usage as type **simple_xml**,
Container will use `simplexml_load_string` function to convert xml into data. But
that function cannot access to node attributes.  
Second is usage of **xml** as type, that will use `Xml` object (_extending DOMDocument_)
to create xml. That method is slower but accept xml attributes and serialized objects.
Attributes are stored into special array at `@attributes` key value.

### Xml data and object
Inside of xml data we can give serialized objects. To inform Object that data given
in node is serialized object and must be converted to object use special attribute
`serialized_object` in node that will have serialized object. That attribute must
just exist, value of that attribute  has no meaning.

When we don't give any parameter to object, object will be created as empty and
all data given into that object later will be treat as new, non original data.

### Set Data
To set data we have two ways. Give complete data in constructor (that data will
be treed as original data), or by using magical `set*` methods when data key is
part of method name or by using `setData` where we give data key as first method
attribute, and data to set as second attribute.

```php
$object->setData('variable_key', 'some data to set');
```

```php
$object->setVariableKey('some data to set');
```

Add data in that way wil set object as changed and if there was some data at key
we set new data, that data will be copied into original data array and can be
accessible by `returnOriginalData` method.  
**Data will be set up, or changed only if given value is different (compare by** `!==` **operator)
than value stored on given key**

### Get Data
To access data we have similar ways to set data. We can use magical `get*` methods
when data kay is part of method name or by using `getData` where we give data key
as first method attribute.

```php
$object->getData('variable_key', 'some data to set');
```

```php
$object->getVariableKey('some data to set');
```

### Unset Data
To destroy data we have two methods. One will totally remove key from `$_DATA`
array, second wil set key value to null.  
To set data sa null we can use `clearData` method and give key as attribute and
`clear*` giving key as method name. The same way work totally removing key from
`$_DATA` array. Just use `unset` keyword.  
In both cases data will be saved as original data.

### Data validation on set
Container has special ability to validate data before set in into object. Rules to
comparison are stored in special variable `$_validationRules` as pair  
key name => comparison rule (both of them are regular expression).  
Because rule key is regular expression you can validate some group of keys depend
of their names. Data you want to put into object is checked with regular expression
by `preg_match` function and if that function return `true` data wil be inserted
into object. Otherwise in object error list will appear error information with
**validation_mismatch** message:

```php
array(1) {
  [0]=>
  array(4) {
    ["message"]=>
    string(19) "validation_mismatch"
    ["key"]=>
    string(12) "key_name"
    ["data"]=>
    type and value of data
    ["rule"]=>
    string(11) "regular expression used to check"
  }
}
```

Methods used to handle validation rules:
* **putValidationRule** - add validation rule to list
    * **$ruleKey** - key regular expression or array of rules
    * **$ruleValue** - rule regular expression or `null` if first parameter is array (_default is null_)
* **destroyValidationRule** - remove validation rule for given key
* **returnValidationRule** - return all rules or rule for given key

Example of rules in `$_validationRules` array:

```php
$_validationRules = [
    "#^test_[\w]+#" => "#^[\d]{2}$#"
    "#[\w]+_new$#" => "#^[a-z]+$#"
    "#special_key#" => "#^[a-z]+$#"
    
    //function
]
```

### Use Container as array
`Container` class use [ArrayAccess](http://php.net/manual/en/class.arrayaccess.php)
and [Iterator](http://pl1.php.net/manual/en/class.iterator.php)
PHP interfaces, so you can use that class as array.  
Of course array methods implementation takes into account all methods to validate
data and change data when try to get or set data into object.

Data preparation
--------------
Another special ability of Container is prepare data before _set_ and _get_ to add
to object. As above we have two special arrays that store regular expression
to find key to set changes (and methods to set/unset rules in that array) and
functions/methods or lambda functions to change data.

### Set change list
To manipulate change data rules we can use this methods:
* **putPreparationCallback** - allow to set rule for data change before add to object  
  Method gets two parameters, first is rule key (or array of rules), second is an callable
  acceptable by `call_user_func_array` function
* **destroyPreparationCallback** - allow to remove all rules or that with given key
* **returnPreparationCallback** - return list of all rules, or value for given rule key
* **putReturnCallback** - allow to set rule for data change before return from object  
  Method gets two parameters, first is rule key (or array of rules), second is an callable
  acceptable by `call_user_func_array` function
* **destroyReturnCallback** - allow to remove all rules or that with given key
* **returnReturnCallback** - return list of all rules, or value for given rule key

Example of data change rule with lambda function:
```php
$testObject->putReturnCallback([
    '#^test_[\w]+#' => function ($key, $value) {
        return $value . ' - changed';
    }
]);
```

### Get or set data with changes
After set up rules, all data matched with data key, will be changed by given
function/method.

Original Data usage
--------------
When we give data in method constructor we have access to special Object ability
that is store data in original data array. Each operation on that data that will
change key value will copy value given in constructor to special array `$_originalDATA`
so that data won't be loosed and we can access to that data with special methods.  
To check that data was changed in object call `hasDataChanged` that will return
`true` if data was changed or use `keyDataChanged` to check that data for given
key was changed.  
If data was not stored in originalData table and will be removed `keyDataChanged` will return `false`.

### Get original Data
When we call some destructive methods on some key, data before change will be copied
into original data. To access that data before change we use `returnOriginalData`
method with giving key name as attribute

### Restore Data
So if we store original data we can also restore data into `$_DATA` array. We can
restore data for single key or for whole `$_DATA` array.  
To restore data for whole object use `restoreData` method and for single key the
same method but with key as method attribute.  
You can also use magic `restore*` method to restore data for given key.

### Replace Data
Also we have ability to set current data as original data. To do that just use
`replaceDataArrays` and `$_originalDATA` will be replaced by `$_DATA` array and
`_dataChanged` set to false.

Export Container
--------------
As default Container return data as single value key or array of key, values. But
we can export data from object in the same formats as we can put it to Container.  
Accepted formats are `json`, `xml` and `serialized` array. Also we can return
object data in non acceptable as input format that is string. In that export format
we get data separated by coma or by given char.  
Each export method lunch `_prepareData` method to prepare data before export.
By default that method is empty.

### Export as json
To export Container data as `json` format just use `toJson` method.

### Export as xml
To export Container as data as `xml` format use `toXml` method. That method get three
attributes:

* `$addCdata` - will set data inside of CDATA section (_default true_)
* `$dtd` - will add DTD definition to `<!DOCTYPE root SYSTEM` (_default false_)
* `$version` - set xml version (_default 1.0_)

If we have some objects as key value, they will be automatically serialized and
node with that object will get `serialized_object` attribute to inform Container
to unserialize object on import.

### Export as stdClass
Container data can be converted into stdClass. To do that use `toStdClass` method.
Keys will be converted as variable names.

### Export as serialized string
To export data as serialized string just use `serialize` method. That method have
one optional attribute that inform to skip objects inside of data when its set
on `true`.

### Export as string
When we call `echo` or `print` function on object instance, object will return
string with values separated by coma. That separator is saved in variable and we
can easily change to some other using method `changeSeparator` before `echo` function
and as parameter give values separator, or use method `toString` and as parameter
give separator.  
Remember that if you use `toString`, separator given as parameter will be stored
in variable and each usage of `echo` function will use separator given in `toString`
method.

Compare Container and keys
--------------
Container has special method allowed to compare object data or single data key value.
To do that use `compareData` with this attributes:

1. **$dataToCheck** - it can be instance of Object, or array or value fof key
2. **$key** - name of key to compare with (_default is null_)
3. **$operator** - compare operator (_default is ===_)
4. **$origin** - if set to `true` use original data to compare (_default is false_)

Available operator  to compare data `==`, `===`, `!=`, `<>`, `!==`, `<`, `>`, `<=`,
`>=`, `instance` (_alis to instanceof_)

Also there was added two magic methods to compare data with `===` and `!==` operators.
To compare that that is the same us `is*` method, with value to be compared and
`not*` method to check that data are different.

Merge Container
--------------
Container has possibility to merge with other Container by `mergeBlueContainer` method.
Method has one attribute that is other Container instance that will be joined into
Container on which we call merge method.

Container errors
--------------
Container has build in simple error handling. All errors are stored inside of `$_errorsList`
variable as array. To check that Container has errors just call `checkErrors` method
that will return `true` if there was some error in object.  
To return list of errors from object call `returnObjectError` method. That method has
one optional parameter that is error key (to return some concrete error).  
Also we can clear object error or single error using `removeObjectError` method.
Without parameter will clear all errors and with key only error for given key.

### Possible errors
* **append xml data** - can return error with `xml_load_error` key when try to load xml data
* **working with xml data** - when object catch `DOMException` trying create array from xml data
  (code will be `Exception->getCode()` value)
* **try to access wrong magic method** - return `wrong_method` code with class and method name
* **convert array to xml** - when object catch `DOMException` trying create array from xml data
  (code will be `Exception->getCode()` value)

Extending Container
--------------
Extending Container can be done on two ways. First classic is use `extend`:

```php
use BlueContainer\Container;

class Test extends Container
{

}
```
or use trait to inject logic to other object:

```php
class Test extends OtherClass
{
    use BlueContainer\ContainerObject;
}
```

Container has some special methods that are empty and are implemented directly for
extending.

* **initializeObject** - is called at beginning of constructor and take as parameter all options (_as reference_)
* **afterInitializeObject** - is called at end of constructor and loaded data (has no parameters)
* **_prepareData** - is protected method lunched before export data or get data by `getData` or `returnOriginalData` methods
  on two last examples can have `$key` parameter that is key name for data to return.

Some usable methods
--------------
Other usable public methods inside of Object:

### Magic methods
* **__get** - allow to access object data by using method variable `$object->key_name`
* **__set** - allow to set object data by using method variable `$object->key_name = 'value'`
* **__isset** - used when called function `isset` on object variable `isset($object->key_name)`
  will use method `hasData` to return value
* **__unset** - used when called function `unset` on object variable `unset($object->key_name)`
  will use method `unsetData` to remove value
* **__set_state** - will return object data (_like getData()_) when use `var_dump` on object `var_dump($object)`

### Normal methods
* **returnSeparator** - return current set separator for return data as string
* **toArray** - return object attributes as array
* **traveler** - allow to change data inside of object by using method or function
    * **$function** - accepted by `call_user_func_array` callback, can be array of `object, method`, string with `object::method`, function name or lambda function
    * **$methodAttributes** - some additional attributes for method or function
    * **$data** - data to change, if null use object data (_default is null_)
    * **$recursive** - change data even in associative array if true (_default is false_)