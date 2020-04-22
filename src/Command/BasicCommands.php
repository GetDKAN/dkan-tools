<?php

namespace DkanTools\Command;

use DkanTools\Util\Util;
use Symfony\Component\Filesystem\Filesystem;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class BasicCommands extends \Robo\Tasks
{
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
     *   Build with the DKAN frontend application.
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
        'frontend' => false,
        'tag' => null,
        'branch' => null,
        ])
    {
        $this->io()->section("Running dktl make");

        // Add project dependencies.
        // $this->addDrush();
        $this->addDkan2($opts);

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
        $this->makeAddSymlinksToDrupalRoot();

        if ($opts['frontend'] === true) {
            $this->installFrontend();
        }

        $this->io()->success("dktl make completed.");
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

    private function addDkan2(array $opts)
    {
        $dkanVersion = 'dev-master';
        // Find Dkan2 version from options' tag or branch values.
        if ($opts['tag']) {
            $dkanVersion = $opts['tag'];
        } elseif ($opts['branch']) {
            $dkanVersion = "dev-{$opts['branch']}";
        }

        $addDkan2 = $this->taskComposerRequire()
            ->env("COMPOSER_MEMORY_LIMIT=-1")
            ->dependency("getdkan/dkan2", $dkanVersion)
            ->run();
        if ($addDkan2->getExitCode() != 0) {
            $this->io()->error('Unable to add Drush and Dkan2 dependencies.');
            exit;
        }
        $this->io()->success("getdkan/dkan2 added as a project dependency.");
    }

    public function makeAddSymlinksToDrupalRoot()
    {
        $targetsAndLinks = [
            ['target' => 'src/site',    'link' => '/sites/default'],
            ['target' => 'src/modules', 'link' => '/modules/custom'],
            ['target' => 'src/themes',  'link' => '/themes/custom'],
            ['target' => 'src/schema',  'link' => '/schema'],
        ];
        foreach ($targetsAndLinks as $targetAndLink) {
            $this->docrootSymlink(
                $targetAndLink['target'],
                self::DRUPAL_FOLDER_NAME . $targetAndLink['link']
            );
        }
    }

    private function installFrontend()
    {
        $this->io()->section('Adding frontend application');

        $result = $this->downloadFrontend();

        if ($result && $result->getExitCode() === 0) {
            $this->io()->note(
                'Successfully downloaded data-catalog-frontend to /src/frontend'
            );
        }

        if (file_exists('src/frontend')) {
            $this->docrootSymlink('src/frontend', self::DRUPAL_FOLDER_NAME . '/data-catalog-frontend');
        }

        $this->io()->note(
            'You are building DKAN with the React frontend application. ' .
            'In order for the frontend to find the correct routes to work correctly,' .
            'you will need to enable the dkan_frontend module . ' .
            'Do this by running "dktl install" with the "--frontend" option as well, ' .
            'or else run "drush en dkan_frontend" after installation.'
        );
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
     * Link src/modules to docroot/sites/all/modules/custom.
     */
    private function docrootSymlink($target, $link)
    {
        $project_dir = Util::getProjectDirectory();
        $target = $project_dir . "/{$target}";
        $link = $project_dir . "/{$link}";
        $link_parts = pathinfo($link);
        $link_dirname = $link_parts['dirname'];
        $target_path_relative_to_link = (new Filesystem())->makePathRelative($target, $link_dirname);

        if (!file_exists($target) || !file_exists(self::DRUPAL_FOLDER_NAME)) {
            $this->io()->warning(
                "Skipping linking $target. Folders $target and '" .
                self::DRUPAL_FOLDER_NAME."' must both be present to create link."
            );
            return;
        }

        $result = $this->taskFilesystemStack()->stopOnFail()
            ->remove($link)
            ->symlink($target_path_relative_to_link, $link)
            ->run();

        if ($result->getExitCode() != 0) {
            $this->io()->warning('Could not create link');
        } else {
            $this->io()->success("Symlinked $target to $link");
        }
        return $result;
    }

    private function downloadFrontend()
    {
        $result = $this->taskExec('git clone')
            ->option('depth', '1')
            ->option('branch', 'master')
            ->arg('https://github.com/GetDKAN/data-catalog-frontend.git')
            ->arg('frontend')
            ->dir('src')
            ->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not download front-end app.');
            return $result;
        }
        $result = $this->_deleteDir('src/frontend/.git');
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not remove front-end git folder.');
            return $result;
        }

        $this->io()->success('frontend application added.');
        return $result;
    }

    public function install($opts = ['frontend' => false])
    {
        $result = $this->taskExec('drush si standard -y')
            ->dir(Util::getProjectDocroot())
            ->run();
        $result = $this->taskExec('drush en dkan2 dkan_admin dkan_harvest dkan_dummy_content dblog config_update_ui -y')
            ->dir(Util::getProjectDocroot())
            ->run();
        $result = $this->taskExec('drush config-set system.performance css.preprocess 0 -y')
            ->dir(Util::getProjectDocroot())
            ->run();
        $result = $this->taskExec('drush config-set system.performance js.preprocess 0 -y')
            ->dir(Util::getProjectDocroot())
            ->run();
        $result = $this->taskExec('drush config-set system.site page.front "//dkan/home" -y')
            ->dir(Util::getProjectDocroot())
            ->run();

        if ($opts['frontend'] === true) {
            $result = $this->taskExec('drush en -y')
                ->arg('dkan_frontend')
                ->dir(Util::getProjectDocroot())
                ->run();
        }
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
