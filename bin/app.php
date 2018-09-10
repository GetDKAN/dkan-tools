#!/usr/bin/env php
<?php

require_once __DIR__.'/../vendor/autoload.php';

$dktl_directory = DkanTools\Util\Util::getDktlDirectory();
$dktl_project_directory = DkanTools\Util\Util::getProjectDirectory();

$output = new \Symfony\Component\Console\Output\ConsoleOutput();

$discovery = new \Consolidation\AnnotatedCommand\CommandFileDiscovery();
$discovery->setSearchPattern('*Commands.php');
$defaultCommandClasses = $discovery->discover("{$dktl_directory}/src", '\\DkanTools');

$customCommandClasses = [];
if (file_exists("{$dktl_project_directory}/src/command")) {
    $customCommandClasses = $discovery->discover("{$dktl_project_directory}/src/command", '\\DkanTools\\Custom');
}

$commandClasses = array_merge($defaultCommandClasses, $customCommandClasses);

$appName = "DkanTools";
$appVersion = '1.0.0-alpha1';

$runner = new \Robo\Runner($commandClasses);

$argv = $_SERVER['argv'];

$loader = new \DkanTools\Util\ArgumentLoader($argv);

$output = new \Symfony\Component\Console\Output\ConsoleOutput();
$statusCode = $runner->execute($loader->getAlteredArgv(), $appName, $appVersion, $output);

exit($statusCode);
