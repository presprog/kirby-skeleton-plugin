<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitcd55f576074ee31f9c47b389ad3181d1
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'PresProg\\MyPlugin\\' => 18,
        ),
        'K' => 
        array (
            'Kirby\\' => 6,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'PresProg\\MyPlugin\\' => 
        array (
            0 => __DIR__ . '/../..' . '/classes',
        ),
        'Kirby\\' => 
        array (
            0 => __DIR__ . '/..' . '/getkirby/composer-installer/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'Kirby\\ComposerInstaller\\CmsInstaller' => __DIR__ . '/..' . '/getkirby/composer-installer/src/ComposerInstaller/CmsInstaller.php',
        'Kirby\\ComposerInstaller\\Installer' => __DIR__ . '/..' . '/getkirby/composer-installer/src/ComposerInstaller/Installer.php',
        'Kirby\\ComposerInstaller\\Plugin' => __DIR__ . '/..' . '/getkirby/composer-installer/src/ComposerInstaller/Plugin.php',
        'Kirby\\ComposerInstaller\\PluginInstaller' => __DIR__ . '/..' . '/getkirby/composer-installer/src/ComposerInstaller/PluginInstaller.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitcd55f576074ee31f9c47b389ad3181d1::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitcd55f576074ee31f9c47b389ad3181d1::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitcd55f576074ee31f9c47b389ad3181d1::$classMap;

        }, null, ClassLoader::class);
    }
}