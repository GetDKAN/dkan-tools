<?php

namespace DkanTools\Command;

use DkanTools\Util\Util;
use DkanTools\DrupalProjectTrait;

class InitCommands extends \Robo\Tasks
{
    use DrupalProjectTrait;

    /**
     * Initialize DKAN project directory.
     *
     * This command will result in a project directory with all needed files
     * and directories for development, including a composer.json (but NOT
     * including any Composer dependencies.)
     *
     * @option str drupal
     *   Drupal composer version (expressed as composer constraint).
     * @option str dkan
     *   DKAN version (expressed as composer constraint). Use 2.x-dev for current
     *   bleeding edge.
    */
    public function init($opts = ['drupal' => '9.0.0', 'dkan' => null, 'dkan-local' => false])
    {
        // Validate version is semantic and at least the minimum set
        // in DrupalProjectTrait.
        if (!$this->drupalProjectValidateVersion($opts['drupal'])) {
            exit;
        }
        $this->initDrupal($opts['drupal']);
        $this->initConfig();
        $this->initSrc();
        if ($opts['dkan-local']) {
            $this->initLocalDkan();
        }
        $this->initDkan($opts['dkan']);
    }

    /**
     * Create the dktl.yml file.
     */
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

    /**
     * Set up the src directory in a new project.
     */
    private function initSrc()
    {
        $this->io()->section('Initializing src directory');
        if (file_exists('src')) {
            $this->io()->warning('The src directory already exists in this directory; skipping.');
            exit;
        }

        $this->_mkdir('src');
        $this->_mkdir('docroot');

        $directories = ['docker', 'modules', 'themes', 'site', 'test', 'script', 'command'];

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

    /**
     * Create command directory and copy in sample SiteCommands.php file.
     */
    private function createSiteCommands()
    {
        $dktlRoot = Util::getDktlDirectory();
        $f = 'command/SiteCommands.php';
        $result = $this->taskWriteToFile($f)
            ->textFromFile("$dktlRoot/assets/command/SiteCommands.php")
            ->run();

        $this->directoryAndFileCreationCheck($result, $f);
    }

    private function createDktlYmlFile()
    {
        $f = Util::getProjectDirectory() . '/dktl.yml';
        $result = $this->taskExec('touch')->arg($f)->run();
        $this->directoryAndFileCreationCheck($result, $f);
    }

    /**
     * Set up scripts directory and copy in standard deploy.sh scripts.
     */
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

    /**
     * Create the "site" directory, which will by symlinked to
     * docroot/sites/default.
     */
    private function createSiteFilesDirectory()
    {
        $directory = 'src/site/files';
        $this->_mkdir($directory);
        $result = $this->_exec("chmod 777 {$directory}");

        $this->directoryAndFileCreationCheck($result, $directory);
    }

    /**
     * Add Drupal settings files to src/site.
     *
     * @todo The default.* files are probably no longer necessary.
     */
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

    /**
     * Confirm file or directory was created successfully.
     *
     * @param \Robo\Result $result
     *   The result of the task called to create the file.
     * @param mixed $df
     *   Path to file created.
     *
     * @return [type]
     */
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

    /**
     * Create a new Drupal project in the current directory.
     *
     * @param mixed $drupalVersion
     *   Drupal version to use, expressed as Composer constraint.
     */
    public function initDrupal($drupalVersion)
    {
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

    /**
     * Add DKAN as a dependency to the project's composer.json.
     *
     * @param string|null $version
     *   Version of DKAN to pull in, expressed as Composer constraint.
     *
     * @return [type]
     */
    public function initDkan(string $version = null)
    {
        $this->taskComposerRequire()
            ->dependency('getdkan/dkan', $version)
            ->option('--no-update')
            ->run();
    }

    public function initLocalDkan()
    {
        $this->taskComposerConfig()
            ->repository('getdkan', 'dkan', 'path')
            ->run();
    }
}
