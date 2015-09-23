Bluetree Service Container
============

[![Build Status](https://travis-ci.org/bluetree-service/container.svg)](https://travis-ci.org/bluetree-service/container)
[![Latest Stable Version](https://poser.pugx.org/bluetree-service/container/v/stable.svg)](https://packagist.org/packages/bluetree-service/container)
[![Total Downloads](https://poser.pugx.org/bluetree-service/container/downloads.svg)](https://packagist.org/packages/bluetree-service/container)
[![License](https://poser.pugx.org/bluetree-service/container/license.svg)](https://packagist.org/packages/bluetree-service/container)
[![Documentation Status](https://readthedocs.org/projects/container/badge/?version=latest)](https://readthedocs.org/projects/container/?badge=latest)
[![Coverage Status](https://coveralls.io/repos/bluetree-service/container/badge.svg)](https://coveralls.io/r/bluetree-service/container)

Main files for all class libraries. Include classes to use ContainerObject as trait and
independent Container with xml, json, array data handling.That package is one of the base
package for all Bluetree-Service libraries, but also can be used independent.  

### Included libraries
* **BlueContainer\ContainerObject** - trait class to store data as object
* **BlueContainer\Container** - include ContainerObject trait for create object

Documentation
--------------
* [BlueContainer\ContainerObject](https://github.com/bluetree-service/container/wiki/ContainerObject "ContainerObject and Container")

Install via Composer
--------------
To use packages you can just download package and pace it in your code. But recommended
way to use _BlueContainer_ is install it via Composer. To include _BlueContainer_
libraries paste into composer json:

```json
{
    "require": {
        "bluetree-service/container": "version_number"
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
* 

Change log
--------------
All release version changes:  
[Change log](https://github.com/bluetree-service/container/wiki/Change-log "Change log")

License
--------------
This bundle is released under the Apache license.  
[Apache license](https://github.com/bluetree-service/container/LICENSE "Apache license")

Travis Information
--------------
[Travis CI Build Info](https://travis-ci.org/bluetree-service/container)
