<?php

namespace DkanTools\Commands;

use DkanTools\Util\Util;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class BasicCommands extends \Robo\Tasks
{
    /**
     * Test some things.
     */
    function test(array $cmd)
    {
        $cmdStr = implode(' ', $cmd);
        $this->say($cmdStr);
    }

    /**
     * Initialize DKAN project directory.
     */
    function init($opts = ['host' => ''])
    {
        $dktlRoot = Util::getDktlRoot();
        if (file_exists('dktl.yml') && file_exists('config') && file_exists('site')) {
            throw new \Exception("This project has already been initialized.");
            exit;
        }
        if (file_exists('dktl.yml')) {
            $this->io()->warning('The dktl.yml file already exists in this directory; skipping.');
        }
        else {
            // Create dktl.yml file
            $result = $this->taskWriteToFile('dktl.yml')
            ->textFromFile("$dktlRoot/assets/dktl.yml")
            ->run();
            if (file_exists('dktl.yml')) {
                $this->io()->success("dktl.yml file successfully initialized.");
            }
        }
        if (file_exists('config')) {
            $this->io()->warning('The config directory already exists in this directory; skipping.');
        }
        else {
            // Create makefile overrides
            $this->_mkdir('config');
            $result = $this->taskWriteToFile('config/drupal-override.make')
                ->textFromFile("$dktlRoot/assets/drush/template.make.yml")
                ->run();
            $result = $this->taskWriteToFile('config/dkan-override.make')
                ->textFromFile("$dktlRoot/assets/drush/template.make.yml")
                ->run();
            $result = $this->taskWriteToFile('config/contrib.make')
                ->textFromFile("$dktlRoot/assets/drush/template.make.yml")
                ->run();
            $this->_mkdir('config/modules/custom');
            if (file_exists('config')) {
                $this->io()->success("Config directory successfully initialized.");
            }
        }
        if (file_exists('site')) {
            $this->io()->warning('The site directory already exists in this directory; skipping.');
        }
        else {
            // Create the site directory. This will get symlinked into
            // docroot/sites/all/default.
            $this->_mkdir('site');
            $result = $this->taskWriteToFile('site/settings.docker.php')
                ->textFromFile("$dktlRoot/assets/site/settings.docker.php")
                ->run();
            if ($opts['host']) {
                $this->initHost($opts['host']);
            }
        }
    }

    /**
     * Initialize host settings.
     *
     * @todo Fix opts, make required.
     */
    function initHost($host = NULL) {
        $dktlRoot = Util::getDktlRoot();
        $settingsFile = "settings.$host.php";
        if (!$host) {
            throw new \Exception("Host not specified.");
            exit;
        }
        if (!file_exists("$dktlRoot/assets/site/$settingsFile")) {
            $this->io()->warning("Host settings for '$host' not supported; skipping.");
            exit;
        }
        if (!file_exists('site')) {
            throw new \Exception("The project's site directory must be initialized before adding host settings.");
            exit;
        }
        if (file_exists("site/$settingsFile")) {
            $this->io()->warning("Host settings for '$host' already initialized; skipping.");
            exit;
        }
        $result = $this->taskWriteToFile("site/settings.$host.php")
            ->textFromFile("$dktlRoot/assets/site/settings.$host.php")
            ->run();
    }

    /**
     * Run drush command on current site.
     *
     * Run drush command on current site. For instance, to clear caches, run
     * "dktl drush cc all". Note that the shell script will pass all arguments
     * correctly as well as append a --uri argument so that commands like
     * "drush uli" will output a correct url.
      */
    function drush(array $cmd) {
        $drushExec = $this->taskExec('drush')->dir('docroot');
        foreach ($cmd as $arg) {
            $drushExec->arg($arg);
        }
        $drushExec->run();
    }
}
