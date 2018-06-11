<?php

namespace DkanTools\Commands;

use Symfony\Component\Console\Input\InputOption;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class TestCommands extends \Robo\Tasks
{
    function testInit()
    {
        if (!file_exists('docroot/profiles/dkan/test/vendor')) {
            $this->io()->section('Installing test dependencies.');
            $this->taskExec('composer install --prefer-source --no-interaction')
                ->dir('docroot/profiles/dkan/test')
                ->run();
        }
        if (!file_exists('docroot/profiles/dkan/test/assets')) {
            $this->io()->section('Creating test subdirectories.');
            $this->_mkdir('docroot/profiles/dkan/test/assets/junit');            
        }
    }

    function testBehat(array $opts = ['name' => InputOption::VALUE_REQUIRED])
    {
        $this->testInit();
        $behatExec = $this->taskExec('bin/behat')
            ->dir('docroot/profiles/dkan/test')
            ->arg('--colors')
            ->arg('--suite=dkan')
            ->arg('--format=pretty')
            ->arg('--out=std')
            ->arg('--format=junit')
            ->arg('--out=assets/junit')
            ->arg('--config=behat.docker.yml');
        if ($opts['name']) {
            $behatExec->arg("--name={$opts['name']}");
        }
        $behatExec->run();
    }
}
