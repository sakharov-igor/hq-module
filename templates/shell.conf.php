#!/usr/bin/env php
<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Filesystem\Filesystem;

require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

$application = new Application('Hostiq', '0.1');
$fileSystem  = new Filesystem();
$configFile  = require_once dirname(__DIR__) . '/config/command.php';
$filesRange  = array_diff(scandir($configFile['directory']), ['.', '..']);
$classes     = [];

foreach ($filesRange as $file) {
    $className = $configFile['className'] . '\\' . str_replace('.php', '', $file);
    $file = $configFile['directory'] . '/' . $file;
    if ($fileSystem->exists($file)) {
        require $file;

        if (class_exists($className)) {
            $classes[$className::$defaultName] = $className;
        }
    }
}

$commandLoader = new FactoryCommandLoader(array_reduce($classes, function ($carry, $item) {
    $carry[$item::$defaultName] = function () use ($item) { return new $item();};

    return $carry;
}));

$application->setCommandLoader($commandLoader);
$application->run();
