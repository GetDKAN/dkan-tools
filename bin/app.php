#!/usr/bin/env php
<?php

require_once __DIR__.'/../vendor/autoload.php';

$drupalVersion = "V8";

$dktl_directory = DkanTools\Util\Util::getDktlDirectory();
$dktl_project_directory = DkanTools\Util\Util::getProjectDirectory();

$output = new \Symfony\Component\Console\Output\ConsoleOutput();

$discovery = new \Consolidation\AnnotatedCommand\CommandFileDiscovery();
$discovery->setSearchPattern('*Commands.php');
$defaultCommandClasses = $discovery->discover("{$dktl_directory}/src", '\\DkanTools');

$discovery->setSearchPattern('*Commands.php');
$drupalVersionSpecificCommandsClasses = $discovery->discover("{$dktl_directory}/src/Drupal/{$drupalVersion}", '\\DkanTools\\Drupal\\' . $drupalVersion);

$customCommandClasses = [];
if (file_exists("{$dktl_project_directory}/src/Command")) {
    $customCommandClasses = $discovery->discover("{$dktl_project_directory}/src/Command", '\\DkanTools\\Custom');
}

$commandClasses = array_merge($defaultCommandClasses, $drupalVersionSpecificCommandsClasses, $customCommandClasses);

$appName = "DKAN Tools";
$appVersion = '1.0.0-alpha1';
$configurationFilename = 'dktl.yml';

$runner = new \Robo\Runner($commandClasses);
$runner->setConfigurationFilename($configurationFilename);

$argv = $_SERVER['argv'];

$output = new \Symfony\Component\Console\Output\ConsoleOutput();
$statusCode = $runner->execute($argv, $appName, $appVersion, $output);

exit($statusCode);
