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
        $this->io()->section('Initializing dktl configuration');
        if (file_exists('dktl.yml') && file_exists('config') && file_exists('assets')) {
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
        $this->io()->section('Initializing config directory');
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
        $this->io()->section('Initializing assets directory');
        if (file_exists('assets')) {
            $this->io()->warning('The assets directory already exists in this directory; skipping.');
        }
        else {
            // Create the site directory. This will get symlinked into
            // docroot/sites/all/default.
            $this->_mkdir('assets/sites/default');
            $this->_mkdir('assets/sites/default/files');
            $this->_exec('chmod 777 assets/sites/default/files');
            $result = $this->taskWriteToFile('assets/sites/default/settings.php')
                ->textFromFile("$dktlRoot/assets/site/settings.php")
                ->run();
            $result = $this->taskWriteToFile('assets/sites/default/settings.docker.php')
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
     *
     * @param array $cmd Array of arguments to create a full Drush command.
     */
    function drush(array $cmd) {
        $drushExec = $this->taskExec('drush')->dir('docroot');
        foreach ($cmd as $arg) {
            $drushExec->arg($arg);
        }
        return $drushExec->run();
    }

    /**
     * Run "drush uli" command with correct ULI argument.
     *
     * Like the "docker" group of commands, this command is actually run in
     * inside the dktl.sh script makes it to the DKAN Tools php application. It
     * simply runs the real "dktl drush" command and passes it the result of
     * "dktl surl" as the --uri argument.
     *
     * @todo Make it configurable whether this uses http or https. 
     */
    function drushUli() {
        throw new \Exception('Something went wrong; this command should be run through dktl.sh');
    }
}
