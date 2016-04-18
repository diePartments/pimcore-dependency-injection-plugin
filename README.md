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

Run ```composer install``` from your commandline and enable the plugin in the Pimcore Extension Manager afterwards.

For more information about how to use extensions via composer, [take a look at the documentation](https://www.pimcore.org/wiki/display/PIMCORE4/Extension+management+using+Composer)

## Advanced Usage

You can use all injection options provided by PHP-DI. The Plugins installer creates all necessary configuration files for you. 
They are located at ``` /website/var/plugins/dependency-injection ```

Put your service configurations in ```container.php``` and your parameters in ``` parameters.php ```.

You can also create environment specific definitions, to override defaults. Simply create a ```container.ENV.php ``` file and put your env specific services configuration in it.
*Replace ```ENV``` by the environment you want to create specific config for, e.g. ```development```*

The [Pimcore System environment](https://www.pimcore.org/wiki/pages/viewpage.action?pageId=20217900).

### Example

Lets configure a shopping cart. 
The cart should use an external system, like an ERP, to calculate shipping costs or customer specific prices. 
Therefore it needs a Gateway which knows how to communicate with the external System. The Gateway itself needs to know the api path its calling.

#### Example container.php

```php
<?php 
use Website\Service\Cart as CartService;
use Website\Gateway\CartInterface as CartGatewayInterface;
use Website\Gateway\Cart as CartGateway;

return [
    
    // Configure the base path to our external system. Can easily be overridden e.g in container.development.php for dev environment
    'api.root' => 'http://somehost/api',
    
    // Configure the Gateway for our Cart. 
    // For this example we will create the gateway using a simple factory. Factories can have their own class for more flexibility.
    CartGatewayInterface::class => function() {
    
        // The factory method could create the correct instance depending on the env or any other config.
        // This might be helpful to create a mock gateway while in development.
        
        // For the sake of this example, we simple create the gateway and set the correct api path, based on the root api key
        return new CartGateway(DI\string('{api.root}/cart'));
    }
        
    // Configure the cart, giving it a gateway identified by the interface class    
    'cart' => DI\object(CartService::class)
        ->constructor(DI\get(CartGatewayInterface::class)) // give the service a gateway
        ->lazy() // make it lazy as most likely not every request needs the cart service so it only gets initialized when its needed
];

```

Then use the cart Service in your Controller by using Annotation with the configured container key. This also works for parameters.

```php

class CartController extends Action
{
    /** 
     * @Inject("cart")
     * @var Cart
     */   
    protected $cart;
    
    public function someAction()
    {
        // just use your service
        $this->cart->someCartMethod();
    }
}

```

Learn more about the different options at http://php-di.org/doc/definition.html.

##### Best Practise 
Use [lazy injection](http://php-di.org/doc/lazy-injection.html) for every service which will not be used in every request.

## Accessing the Container

### Built in Controller Action Helper

You can use the built in helper in your Controller to access the container at any time. This allows you to get configured container entries on demand.
Be aware that you should couple your application to the container as less as possible, otherwise you loose portability. This in mind, use the action helper only if you have to.

#### Action Helper example usage

```php

class MyController extends Action
{
    public function someAction()
    {
        // get a container entry when needed
        $from = $this->_helper->DI->get('mail.default_sender');
    }
}

```

### Zend Registry

The Container gets added to the registry and can be retrieved from there like in this example:
```php

/** @var Interop\Container\ContainerInterface $container */
$container = \Zend_Registry::get(DependencyInjection\Plugin::CONTAINER_REGISTRY_KEY);

```

### Event Hook

You can access the container after its initialization utilizing Pimcores Event System

```php
\Pimcore::getEventManager()->attach(\DependencyInjection\Plugin::CONTAINER_INITIALIZED_EVENT, function(\Zend_EventManager_Event $e) {
    
    /** @var Interop\Container\ContainerInterface $container */    
    $container = $e->getTarget(); 
      
    // access your defined services
    $someService = $container->get('service.key');
});
```

## Extending the Container

You can extend the container, e.g. to add a additional definitions.php file, using Pimcores Event System.
Therefore you have to attach a Listener in your own Plugin or startup.php like in the example below.

```php

// attach a event handler to extend the container
\Pimcore::getEventManager()->attach(DependencyInjection\Plugin::CONTAINER_INIT_EVENT, function(\Zend_EventManager_Event $e) {
    
    /** @var Interop\Container\ContainerInterface $container */    
    $container = $e->getTarget();
    
    // add more definitions, may be from a vendor bundle or from another dir.
    $container->addDefinitions('path/to/definitions.php');
});

```

Please note that the container will be built after the mentioned event was fired and can not be extended anymore.
This is due to performance optimization.

## Plugin Configuration

Configuration file is located at 
```
/plugins/dependency-injection/config.xml
```

This file can be edited directly by clicking on the ```configure``` button in the Extension Manager 
 
### Configuration Options

#### Cache Proxies

If ```true``` generated proxy classes for lazy injection will be written and read from file.
Defaults to ```true```

#### Use definition files

Set to ```false``` if you wont use php definition files
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

The Cache is only activated for ```production``` environment.

