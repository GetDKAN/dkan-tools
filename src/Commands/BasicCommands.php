<?php

namespace DkanTools\Commands;

use DkanTools\Util\Util;
use Robo\Result;

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
    public function test(array $cmd)
    {
        $cmdStr = implode(' ', $cmd);
        $this->say($cmdStr);
    }

    /**
     * Initialize DKAN project directory.
     */
    public function init($opts = ['host' => ''])
    {
        $this->io()->section('Initializing dktl configuration');
        if (file_exists('dktl.yml') && file_exists('src')) {
            $this->io()->note("This project has already been initialized.");
            exit;
        }

        if (file_exists('dktl.yml')) {
            $this->io()->warning('The dktl.yml file already exists in this directory; skipping.');
        } else {
            $this->createDktlYmlFile();
        }

        $this->io()->section('Initializing src directory');
        if (file_exists('src')) {
            $this->io()->warning('The src directory already exists in this directory; skipping.');
        } else {
            $this->createSrcDirectory($opts['host']);
        }
    }

    private function createDktlYmlFile()
    {
        $dktlRoot = Util::getDktlRoot();
        $f = 'dktl.yml';
        $result = $this->taskWriteToFile($f)
        ->textFromFile("$dktlRoot/assets/dktl.yml")
        ->run();

        $this->directoryAndFileCreationCheck($result, $f);
    }

    private function createSrcDirectory($host = "")
    {
        $this->_mkdir('src');

        $directories = ['docker', 'make', 'modules', 'themes', 'site', 'tests'];

        foreach ($directories as $directory) {
            $dir = "src/{$directory}";

            $result = $this->_mkdir($dir);

            $this->directoryAndFileCreationCheck($result, $dir);
        }

        $this->createMakeFiles();
        $this->createSiteFilesDirectory();
        $this->createSettingsFiles($host);
    }

    private function createMakeFiles()
    {
        $dktlRoot = Util::getDktlRoot();

        $files = ['drupal', 'dkan'];

        foreach ($files as $file) {
            $f = "src/make/{$file}.make";

            $result = $this->taskWriteToFile($f)
          ->textFromFile("$dktlRoot/assets/drush/template.make.yml")
          ->run();

            $this->directoryAndFileCreationCheck($result, $f);
        }
    }

    private function createSiteFilesDirectory()
    {
        $directory = 'src/site/files';
        $this->_mkdir($directory);
        $result = $this->_exec("chmod 777 {$directory}");

        $this->directoryAndFileCreationCheck($result, $directory);
    }

    private function createSettingsFiles($host = "")
    {
        $dktlRoot = Util::getDktlRoot();

        $settings = ["settings.php", "settings.docker.php"];

        foreach ($settings as $setting) {
            $f = "src/site/{$setting}";
            $result = $this->taskWriteToFile($f)
          ->textFromFile("$dktlRoot/assets/site/{$setting}")
          ->run();
            $this->directoryAndFileCreationCheck($result, $f);
        }

        if (!empty($host)) {
            $this->initHost();
        }
    }

    private function directoryAndFileCreationCheck(Result $result, $df)
    {
        if ($result->getExitCode() == 0 && file_exists($df)) {
            $this->io()->success("{$df} was created.");
        } else {
            $this->io()->error("{$df} was not created.");
            exit;
        }
    }

    /**
     * Initialize host settings.
     *
     * @todo Fix opts, make required.
     */
    public function initHost($host = null)
    {
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
    public function drush(array $cmd)
    {
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
