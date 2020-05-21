<?php

namespace HQWHMCS;

use Symfony\Component\Filesystem\Filesystem;


class Installer
{
    public static function postPackageInstall($event) {
        $vendorDir           = $event->getComposer()->getConfig()->get('vendor-dir');
        $rootProject         = dirname($vendorDir);
        $pathToSetConfig     = $rootProject . '/src/HQ/bin/shell.php';
        $pathToConfig        = $rootProject . '/src/HQ/config/command.php';
        $pathComposerExample = $rootProject . '/src/HQ/Commands/CommandTest.php';

        $fileSystem = new Filesystem();

        if (!$fileSystem->exists($pathComposerExample)) {
            $fileSystem->mkdir($rootProject . '/src/HQ/Commands/');
            $fileSystem->touch($rootProject . '/src/HQ/Commands/CommandTest.php');

            $fileSystem->dumpFile($pathComposerExample, file_get_contents(__DIR__ . '/templates/CommandTest.conf.php'));
        }

        if (!$fileSystem->exists($pathToConfig)) {
            $fileSystem->mkdir($rootProject . '/src/HQ/config/');
            $fileSystem->touch($rootProject . '/src/HQ/config/command.php');

            $fileSystem->dumpFile($pathToConfig, file_get_contents(__DIR__ . '/templates/command.conf.php'));
        }

        if (!$fileSystem->exists($pathToSetConfig)) {
            $fileSystem->mkdir($rootProject . '/src/HQ/bin/');
            $fileSystem->touch($rootProject . '/src/HQ/bin/shell.php');

            $fileSystem->dumpFile($pathToSetConfig, file_get_contents(__DIR__ . '/templates/shell.conf.php'));
        }
    }
}
