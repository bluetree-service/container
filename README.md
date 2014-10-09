ClassKernel
============

Main files for all class libraries. Include classes to use BlueObject as trait and
independent Object with xml data handling. Also allow to use Register to create
objects and singletons. That package is base package for all Class libraries, but
also can be used independent.  
Its recommended to use packages `ClassEvents` and optionally `ClassBenchmark`.

### Included libraries
* **ClassKernel\Base\BlueObject** - trait class to store data as object
* **ClassKernel\Data\Object** - include BlueObject trait for create object
* **ClassKernel\Data\Xml** - extends DOMDocument to handle xml data
* **ClassKernel\Base\Register** - allow to create objects and singletons

Documentation
--------------
* [ClassKernel\Base\BlueObject](https://githib.com/chajr/class-kernel/doc/BlueObject.md "BlueObject and Object")
* [ClassKernel\Base\Register](https://githib.com/chajr/class-kernel/doc/Register.md "Register")
* [ClassKernel\Data\Xml](https://githib.com/chajr/class-kernel/doc/Xml.md "Xml")
* [Events](https://githib.com/chajr/class-kernel/doc/Events.md "Events")

Install via Composer
--------------
To use packages you can just download package and pace it in your code. But recommended
way to use _ClassKernel_ is install it via Composer. To include _ClassKernel_
libraries paste into composer json:

```json
{
    "require": {
        "chajr/class-kernel": "version_number"
    }
}
```

Project description
--------------

### Used conventions

* **Namespaces** - each library use namespaces
* **PSR-4** - [PSR-4](http://www.php-fig.org/psr/psr-4/) coding standard
* **Composer** - [Composer](https://getcomposer.org/) usage to load/update libraries

### Requirements

* PHP 5.4 or higher
* DOM extension enabled

Change log
--------------
All release version changes:  
[Change log](https://githib.com/chajr/class-kernel/CHANGELOG.md "Change log")

License
--------------
This bundle is released under the Apache license.  
[Apache license](https://githib.com/chajr/class-kernel/LICENSE "Apache license")