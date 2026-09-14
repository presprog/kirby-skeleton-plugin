<?php declare(strict_types=1);

use Kirby\Cms\App;
use PresProg\MyPlugin\MyPlugin;
use PresProg\MyPlugin\Options;

if (!function_exists('myPlugin')) {
    function myPlugin(?App $kirby = null): MyPlugin
    {
        return new MyPlugin(Options::fromConfig($kirby));
    }
}

// Add global helper classes here
