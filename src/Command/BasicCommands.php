<?php

namespace DkanTools\Command;

use DkanTools\SymlinksTrait;
use DkanTools\Util\Util;
use Robo\Tasks;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class BasicCommands extends Tasks
{
    use SymlinksTrait;

    const DRUPAL_FOLDER_NAME = "docroot";

    /**
     * Get all necessary dependencies and "make" a working codebase.
     *
     * Running `dktl make` will:
     *   1. Modify the stock drupal composer.json file to merge in anything in src/make
     *   2. Use composer to download and build all php dependencies.
     *   3. Symlink a number of dirs from /src into docroot.
     *   4. If requested, pull the DKAN frontend application into docroot.
     *
     * @option yes
     *   Skip confirmation step, overwrite existing no matter what. Use with caution!
     * @option prefer-dist
     *   Prefer dist for packages. See composer docs.
     * @option prefer-source
     *   Prefer dist for packages. See composer docs.
     * @option no-dev
     *   Skip installing packages listed in require-dev.
     * @option optimize-autoloader
     *   Convert PSR-0/4 autoloading to classmap to get a faster autoloader.
     * @option frontend
     *   - Build with the DKAN frontend application.
     *   - You may specify which data-catalog-react branch to build, defaults to master.
     * @option tag
     *   Specify DKAN tagged release to build.
     * @option branch
     *   Specify DKAN branch to build.
     */
    public function make($opts = [
        'yes|y' => false,
        'prefer-source' => false,
        'prefer-dist' => false,
        'no-dev' => false,
        'optimize-autoloader' => false,
        'frontend' => null,
        'tag' => null,
        'branch' => null,
        ])
    {
        $this->io()->section("Running dktl make");

        // Add project dependencies.
        $this->addDrush();
        $this->addDkan($opts);

        // Run composer install while passing the options.
        $composerBools = ['prefer-source', 'prefer-dist', 'no-dev', 'optimize-autoloader'];
        $install = $this->taskComposerInstall();
        foreach ($composerBools as $composerBool) {
            if ($opts[$composerBool] === true) {
                $install->option($composerBool);
            }
        }
        $install->run();

        // Symlink dirs from src into docroot.
        $this->addSymlinksToDrupalRoot();

        if ($opts['frontend']) {
            $branch = ($opts['frontend'] == 1) ? 'master' : $opts['frontend'];
            $result = $this->taskExec('dktl frontend:get')
                ->arg($branch)
                ->run();
            $result = $this->taskExec('dktl frontend:install')->run();
        }

        $this->io()->success("dktl make completed.");
    }

    public function makeAddSymlinksToDrupalRoot()
    {
        $this->addSymlinksToDrupalRoot();
    }

    private function addDrush()
    {
        $addDrush = $this->taskComposerRequire()
          ->dependency("drush/drush")
          ->run();
        if ($addDrush->getExitCode() != 0) {
            $this->io()->error('Unable to add Drush as a project dependencies.');
            exit;
        }
        $this->io()->success("drush/drush added as a project dependency.");
    }

    private function addDkan(array $opts)
    {
        $addDkan = $this->taskComposerRequire()
            ->dependency("getdkan/dkan", $this->getDkanVersion($opts))
            ->run();
        if ($addDkan->getExitCode() != 0) {
            $this->io()->error('Unable to add Drush and Dkan dependencies.');
            exit;
        }
        $this->io()->success("getdkan/dkan added as a project dependency.");
    }

    private function getDkanVersion(array $opts)
    {
        $dkanVersion = '2.x-dev';
        // Find Dkan version from options' tag or branch values.
        if ($opts['tag']) {
            $dkanVersion = $opts['tag'];
        } elseif ($opts['branch']) {
            $branch = $opts['branch'];
            if (is_numeric($branch[0])) {
                $dkanVersion = "{$branch}-dev";
            } else {
                $dkanVersion = "dev-{$branch}";
            }
        }
        return $dkanVersion;
    }

    public function installphpunit()
    {
        $result = $this->taskExec("which phpunit")->run();
        if ($result->getExitCode() == 0) {
            $this->io()->text('phpunit is already installed.');
            return true;
        }

        $this->io()->text('Installing phpunit.');

        $result = $this->taskFilesystemStack()->stopOnFail()
            ->remove('/root/.composer/vendor')
            ->remove('/root/.composer/composer.lock')
            ->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('could not remove root composer files.');
            return $result;
        }
        $result = $this->taskComposerRequire()
            ->dependency('phpunit/phpunit', "7.5.18")
            ->dir('/root/.composer')
            ->run();

        $result = $this->taskExec("ln -s /root/.composer/vendor/bin/phpunit /usr/local/bin/phpunit")->run();

        return $result;
    }

    /**
     * Proxy to the phpunit binary.
     *
     * @param array $args  Arguments to append to full phpunit command.
     */
    public function phpunit(array $args)
    {

        $proj_dir = Util::getProjectDirectory();

        $phpunit_executable = "{$proj_dir}/docroot/vendor/bin/phpunit";

        $phpunitExec = $this->taskExec($phpunit_executable);

        foreach ($args as $arg) {
            $phpunitExec->arg($arg);
        }

        return $phpunitExec->run();
    }

    private function checkDrushCompatibility($version = '9')
    {
        if (version_compare($this->getDrushVersion(), $version) >= 0) {
            return true;
        }
        return false;
    }

    private function getDrushVersion()
    {
        $result = $this->taskExec('drush --version')
            ->dir(Util::getProjectDocroot())
            ->printOutput(false)
            ->run();
        preg_match('/.+?(\d+\.\d+\.\d+)/', $result->getMessage(), $matches);
        if (!isset($matches[1])) {
            throw new \Exception("Could not determine Drush version on this system.");
        }
        return $matches[1];
    }
}
