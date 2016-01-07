<?php
namespace DependencyInjection\Config;


class PluginConfigDist
{
    /** @var string  */
    protected $path = PIMCORE_PLUGINS_PATH . '/DependencyInjection/config.xml.dist';

    public function getPath()
    {
        return $this->path;
    }

    public function __toString()
    {
        return $this->getPath();
    }
}