#!/usr/bin/env php
<?php

require_once __DIR__.'/../vendor/autoload.php';

$output = new \Symfony\Component\Console\Output\ConsoleOutput();

$discovery = new \Consolidation\AnnotatedCommand\CommandFileDiscovery();
$discovery->setSearchPattern('*Commands.php');
$defaultCommandClasses = $discovery->discover('/usr/local/dkan-tools/src', '\\DkanTools');

$customCommandClasses = [];
if (file_exists('/var/www/src/command')) {
    $customCommandClasses = $discovery->discover('/var/www/src/command', '\\DkanTools\\Custom');
}

$commandClasses = array_merge($defaultCommandClasses, $customCommandClasses);

$appName = "DkanTools";
$appVersion = '0.0.0-alpha0';
$configurationFilename = 'dktl.yml';

$runner = new \Robo\Runner($commandClasses);
$runner->setConfigurationFilename($configurationFilename);

$argv = $_SERVER['argv'];
$output = new \Symfony\Component\Console\Output\ConsoleOutput();
$statusCode = $runner->execute($argv, $appName, $appVersion, $output);

exit($statusCode);
