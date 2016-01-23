# Pimcore Dependency Injection Plugin

Plugin for using the [dependency injection software design pattern](https://en.wikipedia.org/wiki/Dependency_injection) in [Pimcore](https://www.pimcore.org/) projects.
It uses the amazing [PHP-DI](http://php-di.org/) dependency injection container and enables you to use all of its injection options.

## Basic Usage

```php

class SomeController extends Action
{
    /**
     * This service object gets injected automatically without any further config.
     * Important thing is that you add the @Inject annotation as well as the objects type (class)
     *
     * @Inject
     * @var \Website\Service\SomeService
     */
    private $someService;
    
    public function indexAction()
    {
        // just use the service anywhere in your controller
        $this->view->some = $this->someService->doSomething();
    }
}
```

## Installation
Add the following line to the ```require``` section of your composer.json
```
"diepartments-pimcore-plugin/dependency-injection": "dev-master"
```
For more information about how to use extensions via composer, [take a look at the documentation](https://www.pimcore.org/wiki/display/PIMCORE4/Extension+management+using+Composer)

## Advanced Usage

You can use all injection options provided by PHP-DI. The Plugins installer creates all necessary configuration files for you. 
They are located at ``` /website/var/plugins/dependency-injection ```

Put your service configurations in ```conainer.php``` and your parameters in ``` parameters.php ```.

You can also create environment specific definitions, to override defaults. Simply create a ```container.ENV.php ``` file and put your env specif services configuration in it.
*Replace ```ENV``` by the environment you want to create specific config for, e.g. ```development```*

The Pimcore System environment is used to load the correct config. It can be set in ``` Settings -> System -> Debug -> Environment ```

Learn more about the different options at http://php-di.org/doc/definition.html

## Plugin Configuration

Configuration file is located at 
```
/plugins/dependency-injection/config.xml
```

This file can be edited directly by clicking on the configure button in the Extension Manager 
 
### Configuration Options

#### Cache Proxies

If ```true``` genereated proxy classes for lazy injection will be written and read from file.
Defaults to ```true```

#### Use definition files

Set to ```false``` if you won`t use php definition files
Defaults to ```true``` 

#### Cache
Configure the cache driver used for annotation caching.
Supported cached types are:

- Redis
- Memcached
- Apcu
- File (Default)

Some Types require additional parameters like host and port of the Cache Backend. 
You have to provide them in the ```options``` part of the cache config.

#### Example config.xml

```xml
<?xml version="1.0"?>
<zend-config xmlns:zf="http://framework.zend.com/xml/zend-config-xml/1.0/">
    <!-- cache generated proxy classes for lazy injection (should be enabled if using lazy injections) -->
    <cache-proxies>true</cache-proxies>
    
    <!-- whether to load definition files or not
    <use-definition-files>true</use-definition-files>
    
    <!-- annotation cache settings -->
    <cache>
        <!-- the cache driver to be used -->    
        <type>redis</type>
        
        <!-- namespace for cache entries (otional) -->
        <namespace>MyApp</namespace>
        
        <!-- cache driver options, depends on driver type -->
        <options>
            <host>localhost</host>
            <port>6379</port>
        </options>
    </cache>
</zend-config>
```

The Cache is only activated for ```production``` environment, which can be enabled in the system config.

