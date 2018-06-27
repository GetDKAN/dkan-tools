#!/usr/bin/env php
<?php

/**
 * If we're running from phar load the phar autoload file.
 * @todo remove phar related code.
 */
$pharPath = \Phar::running(true);
echo $pharPath;
if ($pharPath) {
    require_once "$pharPath/vendor/autoload.php";
} else {
    require_once __DIR__.'/../vendor/autoload.php';
}

$output = new \Symfony\Component\Console\Output\ConsoleOutput();

$commandClasses = [
    \DkanTools\Commands\BasicCommands::class,
    \DkanTools\Commands\DrupalCommands::class,
    \DkanTools\Commands\DkanCommands::class,
    \DkanTools\Commands\TestCommands::class,
    \DkanTools\Commands\DockerCommands::class
];

$statusCode = \Robo\Robo::run(
    $_SERVER['argv'],
    $commandClasses,
    'DkanTools',
    '0.0.0-alpha0',
    $output,
    'org/project'
);

exit($statusCode);
