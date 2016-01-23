<?php

namespace DependencyInjection\Config;


use DependencyInjection\Plugin;

class FileLocator
{
    const VAR_DIR = '/plugins/dependency-injection';
    const DIST_DIR = '/DependencyInjection';

    /** @var string  */
    protected $path;

    public function __construct($fileName, $baseDir = self::VAR_DIR)
    {
        switch ($baseDir) {
            case self::VAR_DIR:
                $this->path = PIMCORE_WEBSITE_VAR;
                break;
            case self::DIST_DIR:
                $this->path = PIMCORE_PLUGINS_PATH;
                break;
        }

        $this->path .= $baseDir . '/' . $fileName;
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
        return $this->getPath();
    }
}