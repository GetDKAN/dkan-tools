#!/usr/bin/env php
<?php

require_once __DIR__.'/../vendor/autoload.php';

if (file_exists('/var/www/vendor/autoload.php')) {
    require_once '/var/www/vendor/autoload.php';
}


$output = new \Symfony\Component\Console\Output\ConsoleOutput();

$discovery = new \Consolidation\AnnotatedCommand\CommandFileDiscovery();
$discovery->setSearchPattern('*Commands.php');
$commandClasses = $discovery->discover('/usr/local/dkan-tools/src', '\\DkanTools');

$commandClassesCustom = [];
if (file_exists('/var/www/src/Command')) {
    $commandClassesCustom = $discovery->discover('/var/www/src', '\\DkanTools\\Custom');
}

$finalCommands = array_merge($commandClasses, $commandClassesCustom);

$statusCode = \Robo\Robo::run(
    $_SERVER['argv'],
    $finalCommands,
    'DkanTools',
    '0.0.0-alpha0',
    $output,
    'org/project'
);

exit($statusCode);
