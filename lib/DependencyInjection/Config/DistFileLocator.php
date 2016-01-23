<?php

namespace DependencyInjection\Config;


class DistFileLocator extends FileLocator
{
    const DEFINITIONS_FILE = 'container.php.dist';
    const CONFIG_FILE = 'config.xml.dist';

    public function __construct($fileName = self::DEFINITIONS_FILE)
    {
        parent::__construct($fileName, FileLocator::DIST_DIR);
    }
}