<?php

namespace DependencyInjection;

use DependencyInjection\Config\ConfigLocator;
use DependencyInjection\Config\DefinitionsLocator;
use DependencyInjection\Config\DistFileLocator;
use DependencyInjection\Config\FileLocator;
use DependencyInjection\Config\ProxyDirLocator;
use DependencyInjection\Controller\Action\Helper\DI as DIControllerHelper;
use DI\Cache\ArrayCache;
use DI\ContainerBuilder;
use Doctrine\Common\Cache\ApcuCache;
use Doctrine\Common\Cache\Cache as CacheInterface;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Cache\MemcachedCache;
use Doctrine\Common\Cache\RedisCache;
use Interop\Container\ContainerInterface;
use Pimcore\API\Plugin as PluginLib;
use Pimcore\Cache;

class Plugin extends PluginLib\AbstractPlugin implements PluginLib\PluginInterface
{
    const CONTAINER_INIT_EVENT = 'dp.di.initContainer';
    const CONTAINER_INITIALIZED_EVENT = 'dp.di.containerInitialized';
    const CONTAINER_REGISTRY_KEY = 'dp.di.container';
    const CONFIG_CACHE_KEY = 'dp_di_config';

    const PROXY_DIR = 'generated';
    const CONFIG_FILE = 'config.xml';

    /** @var  DefinitionsLocator */
    private $containerDefinitions;

    /** @var  DefinitionsLocator */
    private $envContainerDefinitions;

    /** @var  DefinitionsLocator */
    private $parameters;

    /** @var  \Zend_Config */
    private $config;

    /** @var  ContainerInterface */
    protected $container;

    /** @var  string */
    private $env;

    public function init()
    {
        parent::init();

        $this->env = getenv('PIMCORE_ENVIRONMENT') ?: (getenv('REDIRECT_PIMCORE_ENVIRONMENT') ?: '');

        // init definitions
        $this->containerDefinitions = new DefinitionsLocator();
        $this->parameters = new DefinitionsLocator(DefinitionsLocator::PARAMETERS);
        $this->envContainerDefinitions = new DefinitionsLocator(
            DefinitionsLocator::CONTAINER,
            $this->env
        );

        // register events
        \Pimcore::getEventManager()->attach('system.startup', [$this, 'initContainer']);
        \Pimcore::getEventManager()->attach('system.console.init', [$this, 'initContainer']);
        //\Pimcore::getEventManager()->attach('frontend.controller.preInit', [$this, 'initController']);
    }

    public static function install ()
    {
        if (self::isInstalled()) {
            return true;
        }

        $pluginConfigDist = new DistFileLocator(DistFileLocator::CONFIG_FILE);
        $pluginConfig = new FileLocator(self::CONFIG_FILE);

        $definitionsDist = new DistFileLocator();
        $containerDefinitions = new DefinitionsLocator();
        $params = new DefinitionsLocator(DefinitionsLocator::PARAMETERS);

        $proxyDir = new FileLocator(self::PROXY_DIR);

        // do not create dir and configs to avoid overriding them
        if (is_dir($containerDefinitions->getDir())) {
            return true;
        }

        // create plugin config, container and parameters definitions
        if (@mkdir($containerDefinitions->getDir(), 0755) &&
            @mkdir($proxyDir->getPath(), 0755) &&
            copy($definitionsDist, $containerDefinitions) &&
            copy($definitionsDist, $params) &&
            copy($pluginConfigDist, $pluginConfig)) {
            return true;
        }

        throw new \RuntimeException('Unable to create config files and directories!');
    }

    public static function uninstall ()
    {
        $definitions = new DefinitionsLocator();

        if (is_dir($definitions->getDir())) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($definitions->getDir()));

            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealpath());
                }
            }

            rmdir($definitions->getDir());
        }

        return true;
    }

    public static function isInstalled ()
    {
        $definitions = new DefinitionsLocator();
        return is_dir($definitions->getDir());
    }

    public function initContainer()
    {
        $config = $this->getConfig();

        $builder = new ContainerBuilder();
        $builder->useAnnotations(true);

        // add definitions and create definition cache if definitions enabled
        if ($config->get('useDefinitionFiles', true)) {
            $this->addDefinitions($builder);
            $builder->setDefinitionCache($this->createDefinitionCache($config->get('cache')));
        }

        // use proxy cache if enabled
        if ($writeProxiesToFile = $config->get('cacheProxies', true)) {
            $proxyDir = new FileLocator(self::PROXY_DIR);
            $builder->writeProxiesToFile($writeProxiesToFile, $proxyDir->getPath());
        }

        // dispatch event to enable other plugins to extend the container
        \Pimcore::getEventManager()->trigger(self::CONTAINER_INIT_EVENT, $builder);

        $this->container = $builder->build();

        // create action helper
        $this->initActionHelper();

        // add container to registry
        \Zend_Registry::set(self::CONTAINER_REGISTRY_KEY, $this->container);

        // dispatch event to inform others that the container is ready
        \Pimcore::getEventManager()->trigger(self::CONTAINER_INITIALIZED_EVENT, $this->container);
    }

    public function initController(\Zend_EventManager_Event $e)
    {
        $controller = $e->getTarget();
        $this->container->injectOn($controller);
    }

    /**
     * @param ContainerBuilder $builder
     */
    protected function addDefinitions(ContainerBuilder $builder)
    {
        // add default definitions
        $builder->addDefinitions($this->containerDefinitions->getPath());

        // add env specific definitions if exists
        if (file_exists($this->envContainerDefinitions->getPath())) {
            $builder->addDefinitions($this->envContainerDefinitions->getPath());
        }

        // add local parameters
        $builder->addDefinitions($this->parameters->getPath());
    }

    /**
     * @return CacheInterface
     */
    protected function createDefinitionCache($config)
    {
        /** @var CacheInterface $cacheDriver */
        $cacheDriver = null;

        if ('production' == $this->env) {
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
            $config = new \Zend_Config_Xml((string) new FileLocator(self::CONFIG_FILE), null, true);
            Cache::save($config, self::CONFIG_CACHE_KEY);
        }

        $this->config = $config;

        return $this->config;
    }
}
