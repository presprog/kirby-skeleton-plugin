<?php declare(strict_types=1);

use PresProg\MyPlugin\Options;

if (!function_exists('myPlugin')) {
    function myPlugin(array $config)
    {
        return new PresProg\MyPlugin\MyPlugin(Options::fromConfig($config));
    }
}

// Add global helper classes here
