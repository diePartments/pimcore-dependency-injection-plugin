<?php
namespace DependencyInjection\Container;

class Definitions
{
    const TYPE_CONTAINER = 'container';
    const TYPE_PARAMETERS = 'parameters';

    /** @var string  */
    protected $path;

    public function __construct($type = self::TYPE_CONTAINER, $env = '')
    {
        if (!empty($env)) {
            $env .= '.';
        }

        $this->path = PIMCORE_CONFIGURATION_DIRECTORY . '/' . $env . $type . '.php';
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getDir()
    {
        return dirname($this->path);
    }

    public function __toString()
    {
        return $this->path;
    }
}