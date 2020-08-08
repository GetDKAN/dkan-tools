<?php

namespace DkanTools\Command;

use DkanTools\Util\Util;
use DkanTools\DrupalProjectTrait;

class InitCommands extends \Robo\Tasks
{
    use DrupalProjectTrait;

    /**
     * Initialize DKAN project directory.
    */
    public function init($opts = ['drupal' => null, 'dkan' => null])
    {
        $this->initConfig();
        $this->initSrc();
        $this->initDrupal($opts['drupal']);
        $this->initDkan($opts['dkan']);
    }

    private function initConfig()
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
    }

    private function initSrc()
    {
        $this->io()->section('Initializing src directory');
        if (file_exists('src')) {
            $this->io()->warning('The src directory already exists in this directory; skipping.');
            exit;
        }

        $this->_mkdir('src');

        $directories = ['docker', 'modules', 'themes', 'site', 'tests', 'script', 'command'];

        foreach ($directories as $directory) {
            $dir = "src/{$directory}";
            $result = $this->_mkdir($dir);
            if ($directory == "site") {
                $this->_exec("chmod -R 777 {$dir}");
            }
            $this->directoryAndFileCreationCheck($result, $dir);
        }

        $this->createSiteFilesDirectory();
        $this->createSettingsFiles();
        $this->setupScripts();
    }

    private function createDktlYmlFile()
    {
        $dktlRoot = Util::getDktlDirectory();
        $f = 'dktl.yml';
        $result = $this->taskWriteToFile($f)
        ->textFromFile("$dktlRoot/assets/dktl.yml")
        ->run();

        $this->directoryAndFileCreationCheck($result, $f);
    }

    private function setupScripts()
    {
        $dktlRoot = Util::getDktlDirectory();
        $project_dir = Util::getProjectDirectory();

        $files = ['deploy', 'deploy.custom'];

        foreach ($files as $file) {
            $f = "src/script/{$file}.sh";

            $task = $this->taskWriteToFile($f)
            ->textFromFile("{$dktlRoot}/assets/script/{$file}.sh");
            $result = $task->run();
            $this->_exec("chmod +x {$project_dir}/src/script/{$file}.sh");

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

    private function createSettingsFiles()
    {
        $dktlRoot = Util::getDktlDirectory();
        $hash_salt = Util::generateHashSalt(55);

        $settings = ["default.settings.php", "settings.php", "settings.docker.php", "default.services.yml"];

        foreach ($settings as $setting) {
            $f = "src/site/{$setting}";
            if ($setting == 'settings.php') {
                $result = $this->taskWriteToFile($f)
                ->textFromFile("$dktlRoot/assets/site/{$setting}")
                ->place('HASH_SALT', $hash_salt)
                ->run();
            } else {
                $result = $this->taskWriteToFile($f)
                ->textFromFile("$dktlRoot/assets/site/{$setting}")
                ->run();
            }
            $this->directoryAndFileCreationCheck($result, $f);
        }
    }

    private function directoryAndFileCreationCheck(\Robo\Result $result, $df)
    {
        if ($result->getExitCode() == 0 && file_exists($df)) {
            $this->io()->success("{$df} was created.");
        } else {
            $this->io()->error("{$df} was not created.");
            exit;
        }
    }

    /**
     * Generates basic configuration for a DKAN project to work with CircleCI.
     */
    private function initCircleCI()
    {
        $dktl_dir = Util::getDktlDirectory();
        $project_dir = Util::getProjectDirectory();
        return $this->taskExec("cp -r {$dktl_dir}/assets/.circleci {$project_dir}")->run();
    }

    public function initDrupal($drupalVersion = "8")
    {
        // Validate version is semantic and at least the minium set
        // in DrupalProjectTrait.
        $this->drupalProjectValidateVersion($drupalVersion);
        Util::prepareTmp();

        // Composer's create-project requires an empty folder, so run it in
        // Util::Tmp, then move the 2 composer files back into project root.
        $this->drupalProjectCreate($drupalVersion);
        $this->drupalProjectMoveComposerFiles();

        // Modify project's scaffold and installation paths to `docroot`, then
        // install Drupal in it.
        $this->drupalProjectSetDocrootPath();

        Util::cleanupTmp();
    }

    public function initDkan(string $version = null)
    {
        $this->taskComposerRequire()
            ->dependency('getdkan/dkan', $version)
            ->option('--no-update')
            ->run();
    }
}
