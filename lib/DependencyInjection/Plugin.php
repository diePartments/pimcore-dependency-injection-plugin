<?php

namespace DependencyInjection;

use DependencyInjection\Config\PluginConfig;
use DependencyInjection\Config\PluginConfigDist;
use DependencyInjection\Container\Definitions;
use DependencyInjection\Container\DefinitionsDist;
use DependencyInjection\Controller\Action\Helper\DI as DIControllerHelper;
use DI\Cache\ArrayCache;
use DI\ContainerBuilder;
use Doctrine\Common\Cache\ApcuCache;
use Doctrine\Common\Cache\Cache as CacheInterface;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Cache\MemcacheCache;
use Doctrine\Common\Cache\MemcachedCache;
use Doctrine\Common\Cache\RedisCache;
use Interop\Container\ContainerInterface;
use Pimcore\API\Plugin as PluginLib;
use Pimcore\Config;
use Pimcore\Model\Cache;

class Plugin extends PluginLib\AbstractPlugin implements PluginLib\PluginInterface
{
    const CONTAINER_INIT_EVENT = 'dp.di.initContainer';
    const CONTAINER_REGISTRY_KEY = 'dp.di.container';
    const CONFIG_CACHE_KEY = 'dp.di.config';

    /** @var  Definitions */
    private $containerDefinitions;

    /** @var  Definitions */
    private $envContainerDefinitions;

    /** @var  Definitions */
    private $parameters;

    /** @var  \Zend_Config */
    private $config;

    /** @var  ContainerInterface */
    protected $container;

    public function init()
    {
        parent::init();

        // init definitions
        $this->containerDefinitions = new Definitions();
        $this->parameters = new Definitions(Definitions::TYPE_PARAMETERS);
        $this->envContainerDefinitions = new Definitions(
            Definitions::TYPE_CONTAINER,
            Config::getSystemConfig()->general->environment
        );

        // register events
        \Pimcore::getEventManager()->attach('system.startup', [$this, 'initContainer']);
        //\Pimcore::getEventManager()->attach('frontend.controller.preInit', [$this, 'initController']);
    }

    public static function install ()
    {
        if (self::isInstalled()) {
            return true;
        }

        $pluginConfigDist = new PluginConfigDist();
        $pluginConfig = new PluginConfig();

        $definitionsDist = new DefinitionsDist();
        $containerDefinitions = new Definitions();
        $params = new Definitions(Definitions::TYPE_PARAMETERS);

        // create plugin config, container and parameters definitions
        if (copy($definitionsDist, $containerDefinitions) &&
            copy($definitionsDist, $params) &&
            copy($pluginConfigDist, $pluginConfig)) {
            return true;
        }

        throw new \RuntimeException('Unable to create config files!');
    }

    public static function uninstall ()
    {
        return true;
    }

    public static function isInstalled ()
    {
        return file_exists((string) new Definitions());
    }

    public function initContainer()
    {
        $builder = new ContainerBuilder();
        $builder->useAnnotations(true);

        // add default definitions
        $builder->addDefinitions($this->containerDefinitions->getPath());

        // add env specific definitions if exists
        if (file_exists($this->envContainerDefinitions->getPath())) {
            $builder->addDefinitions($this->envContainerDefinitions->getPath());
        }

        // add local parameters
        $builder->addDefinitions($this->parameters->getPath());

        // create and set definitions cache
        $builder->setDefinitionCache($this->createDefinitionCache());

        // dispatch event to enable other plugins to extend the container
        \Pimcore::getEventManager()->trigger(self::CONTAINER_INIT_EVENT, $builder);

        $this->container = $builder->build();

        // create action helper
        $this->initActionHelper();

        // add container to registry
        \Zend_Registry::set(self::CONTAINER_REGISTRY_KEY, $this->container);
    }

    public function initController(\Zend_EventManager_Event $e)
    {
        $controller = $e->getTarget();
        $this->container->injectOn($controller);
    }

    /**
     * @return CacheInterface
     */
    protected function createDefinitionCache()
    {
        /** @var CacheInterface $cacheDriver */
        $cacheDriver = null;

        if ('production' == Config::getSystemConfig()->general->environment) {
            $config = $this->getConfig()->get('cache');
            $cacheOptions = $config->options;

            switch($config->type) {
                case 'redis':
                    $redis = new \Redis();
                    $redis->connect($cacheOptions->host, $cacheOptions->port);

                    /** @var RedisCache $cacheDriver */
                    $cacheDriver = new RedisCache();
                    $cacheDriver->setRedis($redis);
                    break;
                case 'memcached':
                    $memcached = new \Memcached();
                    $memcached->addServer($cacheOptions->host, $cacheOptions->port);

                    /** @var MemcachedCache $cacheDriver */
                    $cacheDriver = new MemcachedCache();
                    $cacheDriver->setMemcached($memcached);
                    break;
                case 'apcu':
                    $cacheDriver = new ApcuCache();
                    break;
                default:
                    $cacheDriver = new FilesystemCache(PIMCORE_CACHE_DIRECTORY);
            }

            $cacheDriver->setNamespace($config->namespace);
        }

        return $cacheDriver? $cacheDriver : new ArrayCache();
    }

    protected function initActionHelper()
    {
        $actionHelper = new DIControllerHelper();
        $actionHelper->setContainer($this->container);

        // register action helper
        \Zend_Controller_Action_HelperBroker::addHelper($actionHelper);
    }

    /**
     * @return \Zend_Config
     */
    protected function getConfig()
    {
        if ($this->config) {
            return $this->config;
        }

        $config = Cache::load(self::CONFIG_CACHE_KEY);

        if (!$config) {
            $config = new \Zend_Config_Xml((string) new PluginConfig());
            Cache::save($config, self::CONFIG_CACHE_KEY);
        }

        $this->config = $config;

        return $this->config;
    }
}
