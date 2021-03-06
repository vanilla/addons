<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInitdc1ed509188ec08171bb71f174d1f37a
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        spl_autoload_register(['ComposerAutoloaderInitdc1ed509188ec08171bb71f174d1f37a', 'loadClassLoader'], true, true);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader();
        spl_autoload_unregister(['ComposerAutoloaderInitdc1ed509188ec08171bb71f174d1f37a', 'loadClassLoader']);

        $map = require __DIR__ . '/autoload_namespaces.php';
        foreach ($map as $namespace => $path) {
            $loader->set($namespace, $path);
        }

        $map = require __DIR__ . '/autoload_psr4.php';
        foreach ($map as $namespace => $path) {
            $loader->setPsr4($namespace, $path);
        }

        $classMap = require __DIR__ . '/autoload_classmap.php';
        if ($classMap) {
            $loader->addClassMap($classMap);
        }

        $loader->register(true);

        $includeFiles = require __DIR__ . '/autoload_files.php';
        foreach ($includeFiles as $fileIdentifier => $file) {
            composerRequiredc1ed509188ec08171bb71f174d1f37a($fileIdentifier, $file);
        }

        return $loader;
    }
}

function composerRequiredc1ed509188ec08171bb71f174d1f37a($fileIdentifier, $file)
{
    if (empty($GLOBALS['__composer_autoload_files'][$fileIdentifier])) {
        require $file;

        $GLOBALS['__composer_autoload_files'][$fileIdentifier] = true;
    }
}
