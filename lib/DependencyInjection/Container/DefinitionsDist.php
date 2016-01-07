<?php
/**
 * Created by PhpStorm.
 * User: Michael
 * Date: 06.01.2016
 * Time: 18:04
 */

namespace DependencyInjection\Container;


class DefinitionsDist
{
    protected $path = PIMCORE_PLUGINS_PATH . '/DependencyInjection/container.php.dist';

    public function getPath()
    {
        return $this->path;
    }

    public function __toString()
    {
        return $this->path;
    }
}