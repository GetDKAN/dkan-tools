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
$appVersion = '1.0.0-alpha1';

$runner = new \Robo\Runner($commandClasses);

$argv = $_SERVER['argv'];

$loader = new \DkanTools\Util\ArgumentsAndOptionsLoader($argv);

$output = new \Symfony\Component\Console\Output\ConsoleOutput();
$statusCode = $runner->execute($loader->enhancedArgv(), $appName, $appVersion, $output);

exit($statusCode);
