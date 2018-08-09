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

$statusCode = \Robo\Robo::run(
    $_SERVER['argv'],
    $commandClasses,
    'DkanTools',
    '0.0.0-alpha0',
    $output,
    'org/project'
);

exit($statusCode);
