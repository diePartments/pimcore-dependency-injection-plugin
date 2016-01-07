<?php

namespace DependencyInjection\Config;


class PluginConfig
{
    protected $path = PIMCORE_WEBSITE_VAR . '/plugins/dependency-injection/config.xml';

    public function getPath()
    {
        return $this->path;
    }

    public function __toString()
    {
        return $this->getPath();
    }
}