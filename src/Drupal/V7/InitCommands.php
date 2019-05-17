<?php

namespace DkanTools\Drupal\V7;

use DkanTools\Util\Util;

class InitCommands extends \Robo\Tasks
{
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

    /**
     * Initialize custom test directories.
     *
     * Running dktl init:custom-tests will create a /src/test folder in your
     * project if one does not exist, and populate it with example custom
     * phpunit and behat tests that should run and pass on any project. You can
     * run these tests with the test:phpunit-custom and test:behat-custom
     * commands.
     * 
     * For new sites this command will automatically be run as part of 
     * dktl:init.
     */
    public function initCustomTests()
    {
        $dktlRoot = Util::getDktlDirectory();
        $this->io()->section('Initializing src/test directory');
        if (!is_dir('src/test')) {
            $result = $this->_mkdir('src/test');
            Util::directoryAndFileCreationCheck($result, 'src/test', $this->io);
        }
        if (file_exists('src/test/behat.yml')) {
            $this->io()->note("Custom behat tests appear to already be configured.");
        } else {
            $files = ['behat.yml', 'behat.docker.yml'];
            foreach ($files as $file) {
                $f = "src/test/{$file}";
                $result = $this->taskWriteToFile($f)
                    ->textFromFile("{$dktlRoot}/assets/test/{$file}")
                    ->run();
                Util::directoryAndFileCreationCheck($result, $f, $this->io);
            }

            $result = $this->_mkdir('src/test/features');
            Util::directoryAndFileCreationCheck($result, 'src/test/features', $this->io);
            $result = $this->_mkdir('src/test/features/bootstrap');
            Util::directoryAndFileCreationCheck($result, 'src/test/features/bootstrap', $this->io);

            $f = "test/features/bootstrap/FeatureContext.php";
            $result = $this->taskWriteToFile("src/{$f}")
                ->textFromFile("{$dktlRoot}/assets/{$f}")
                ->run();
            Util::directoryAndFileCreationCheck($result, "src/$f", $this->io);
        }
        if (file_exists('src/test/phpunit/phpunit.xml')) {
            $this->io()->note("Custom phpunit tests appear to already be configured.");
        } else {
            $result = $this->_mkdir('src/test/phpunit');
            Util::directoryAndFileCreationCheck($result, 'src/test/phpunit', $this->io);
            $result = $this->_mkdir('src/test/phpunit/example');
            Util::directoryAndFileCreationCheck($result, 'src/test/phpunit/example', $this->io);

            $files = ['phpunit.xml', 'boot.php', 'example/CustomTest.php'];
            foreach ($files as $file) {
                $f = "src/test/phpunit/{$file}";
                $result = $this->taskWriteToFile($f)
                    ->textFromFile("{$dktlRoot}/assets/test/phpunit/{$file}")
                    ->run();
                Util::directoryAndFileCreationCheck($result, $f, $this->io);
            }
        }
    }
    
    private function createDktlYmlFile()
    {
        $dktlRoot = Util::getDktlDirectory();
        $f = 'dktl.yml';
        $result = $this->taskWriteToFile($f)
            ->textFromFile("$dktlRoot/assets/dktl.yml")
            ->run();

        Util::directoryAndFileCreationCheck($result, $f, $this->io);
    }

    private function createSrcDirectory($host = "")
    {
        $this->_mkdir('src');

        $directories = ['docker', 'make', 'modules', 'themes', 'site', 'test', 'script', 'command'];

        foreach ($directories as $directory) {
            $dir = "src/{$directory}";

            $result = $this->_mkdir($dir);

            Util::directoryAndFileCreationCheck($result, $dir, $this->io);
        }

        $this->createMakeFiles();
        $this->createSiteFilesDirectory();
        $this->createSettingsFiles($host);
        $this->setupScripts();
        $this->initCustomTests();
    }

    private function setupScripts() {
        $dktlRoot = Util::getDktlDirectory();
        $project_dir = Util::getProjectDirectory();

        $files = ['deploy', 'deploy.custom'];

        foreach ($files as $file) {
            $f = "src/script/{$file}.sh";

            $task = $this->taskWriteToFile($f)
                ->textFromFile("{$dktlRoot}/assets/script/{$file}.sh");
            $result = $task->run();
            $this->_exec("chmod +x {$project_dir}/src/script/{$file}.sh");

            Util::directoryAndFileCreationCheck($result, $f, $this->io);
        }
    }

    private function createMakeFiles()
    {
        $dktlRoot = Util::getDktlDirectory();

        $files = ['drupal', 'dkan'];

        foreach ($files as $file) {
            $f = "src/make/{$file}.make";

            $task = $this->taskWriteToFile($f)
                ->textFromFile("$dktlRoot/assets/drush/template.make.yml");
            if ($file == "drupal") {
                $task->text("defaults:\n  projects:\n    subdir: contrib\n");
                $task->text("projects:\n  environment:\n    version: '1.0'\n  environment_indicator:\n    version: '2.9'");
            }
            $result = $task->run();

            Util::directoryAndFileCreationCheck($result, $f, $this->io);
        }
    }

    private function createSiteFilesDirectory()
    {
        $directory = 'src/site/files';
        $this->_mkdir($directory);
        $result = $this->_exec("chmod 777 {$directory}");

        Util::directoryAndFileCreationCheck($result, $directory, $this->io);
    }

    private function createSettingsFiles($host = "")
    {
        $dktlRoot = Util::getDktlDirectory();

        $settings = ["settings.php", "settings.docker.php"];

        foreach ($settings as $setting) {
            $f = "src/site/{$setting}";
            $result = $this->taskWriteToFile($f)
                ->textFromFile("$dktlRoot/assets/site/{$setting}")
                ->run();
            Util::directoryAndFileCreationCheck($result, $f, $this->io);
        }

        if (!empty($host)) {
            $this->initHost($host);
        }
    }

    /**
     * Initialize host settings.
     *
     * @todo Fix opts, make required.
     */
    private function initHost($host)
    {
        $dktlRoot = Util::getDktlDirectory();
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

        return $result;
    }
}