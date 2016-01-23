<?php
namespace DependencyInjection\Config;

class DefinitionsLocator extends FileLocator
{
    const CONTAINER = 'container';
    const PARAMETERS = 'parameters';

    /** @var string  */
    protected $path;

    public function __construct($type = self::CONTAINER, $env = '')
    {
        if (!empty($env)) {
            $env .= '.';
        }

        parent::__construct($env . $type . '.php', FileLocator::VAR_DIR);
    }
}