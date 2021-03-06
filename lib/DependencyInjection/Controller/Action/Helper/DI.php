<?php

namespace DependencyInjection\Controller\Action\Helper;


use Interop\Container\ContainerInterface;

class DI extends \Zend_Controller_Action_Helper_Abstract
{
    /** @var  ContainerInterface */
    protected $container;

    public function init()
    {
        parent::init();

        // inject dependencies to action controller
        $this->container->injectOn($this->getActionController());
    }

    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Get a entry from container by its id
     *
     * @param {string} $id - Identifier of the entry to look for.
     * @return mixed
     */
    public function get($id)
    {
        return $this->container->get($id);
    }
}