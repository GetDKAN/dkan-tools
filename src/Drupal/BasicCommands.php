<?php

namespace DkanTools\Drupal;

use DkanTools\Util\Util;
use Symfony\Component\Filesystem\Filesystem;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class BasicCommands extends \Robo\Tasks
{
    const DRUPAL_MIN_VERSION = "8.8";
    const DRUPAL_FOLDER_NAME = "docroot";

    /**
     * Get drupal/recommended-project's composer files.
     *
     * We get both Drupal and DKAN on the make step, using composer.
     *
     * @param string $drupalVersion
     *   Drupal semantic version, i.e. 8.8.4 or 9.0.0-beta1
     */
    public function get(string $drupalVersion)
    {
        $this->io()->section("Running dktl get");

        // Validate version is semantic and at least DRUPAL_MIN_VERSION.
        $this->validateVersion($drupalVersion);
        Util::prepareTmp();

        // Composer's create-project requires an empty folder, so run it in
        // Util::Tmp, then move the 2 composer files back into project root.
        $this->composerDrupalOutsideProjectRoot($drupalVersion);
        $this->moveComposerFilesToProjectRoot();

        // Modify project's scaffold and installation paths to `docroot` from
        // the default `web` for drupal/recommended-project.
        $this->modifyComposerPaths();

        Util::cleanupTmp();
        $this->io()->success("dktl get completed.");
    }

    private function validateVersion(string $version)
    {
        // Verify against semver.org's regex here:
        // https://semver.org/#is-there-a-suggested-regular-expression-regex-to-check-a-semver-string
        $semVerRegex = "^(?P<major>0|[1-9]\d*)\.(?P<minor>0|[1-9]\d*)\.(?P" .
          "<patch>0|[1-9]\d*)(?:-(?P<prerelease>(?:0|[1-9]\d*|\d*[a-zA-Z-]" .
          "[0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))" .
          "?(?:\+(?P<buildmetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$";

        if (!preg_match("#{$semVerRegex}#", $version, $matches)) {
            $this->io()->error("version format not semantic.");
            exit;
        }
        if (version_compare($version, self::DRUPAL_MIN_VERSION, "<")) {
            $this->io()->error("drupal version below minimal required.");
            exit;
        }
        $this->io()->success('semantic version validated and >= ' . self::DRUPAL_MIN_VERSION);
    }

    private function composerDrupalOutsideProjectRoot(string $version)
    {
        $createFiles = $this->taskComposerCreateProject()
            ->source("drupal/recommended-project:{$version}")
            ->target(Util::TMP_DIR)
            ->noInstall()
            ->run();
        if ($createFiles->getExitCode() != 0) {
            $this->io()->error('could not run composer create-project.');
            exit;
        }
        $this->io()->success('composer project created.');
    }

    private function moveComposerFilesToProjectRoot()
    {
        $moveFiles = $this->taskFilesystemStack()
            ->rename(
                Util::TMP_DIR . "/composer.json",
                Util::getProjectDirectory() . "/composer.json",
                true
            )
            ->rename(
                Util::TMP_DIR . "/composer.lock",
                Util::getProjectDirectory() . "/composer.lock",
                true
            )
            ->run();
        if ($moveFiles->getExitCode() != 0) {
            $this->io()->error('could not move composer files.');
            exit;
        }
        $this->io()->success('composer.json and composer.lock moved to project root.');
    }

    private function modifyComposerPaths()
    {
        $regexps = "s#web/#" . self::DRUPAL_FOLDER_NAME . "/#g";
        $installationPaths = $this
            ->taskExec("sed -i -E '{$regexps}' composer.json")
            ->run();
        if ($installationPaths->getExitCode() != 0) {
            $this->io()->error('could not Unable to modifying composer.json paths.');
            exit;
        }
        $this->io()->success('composer installation paths modified.');
    }

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
        'no-dev' => true,
        'optimize-autoloader' => false,
        'frontend' => false,
        'tag' => null,
        'branch' => null,
        ])
    {
        $this->io()->section("Running dktl make");

        // Add Drush and Dkan2 dependencies.
        $this->addDependencies($opts);

        // Symlink dirs from src into docroot.
        $this->addSymlinksToDrupalRoot();

        // @Todo: frontend
        $this->io()->success("dktl make completed.");
    }

    private function addDependencies(array $opts)
    {
        // Find Dkan2 version from options' tag or branch values.
        $dkanVersion = null;
        if ($opts['tag']) {
            $dkanVersion = $opts['tag'];
        } elseif ($opts['branch']) {
            $dkanVersion = "dev-{$opts['branch']}";
        }

        // Add Drush and Dkan2 as project dependencies.
        $dependencies = $this->taskComposerRequire()
            ->dependency("drush/drush")
            ->dependency("getdkan/dkan2", $dkanVersion)
            ->run();
        if ($dependencies->getExitCode() != 0) {
            $this->io()->error('Unable to add Drush and Dkan2 dependencies.');
            exit;
        }
    }

    private function addSymlinksToDrupalRoot()
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

    /**
     * Update the version of Drush used in the container.
     *
     * @option $yes
     *   Skip confirmation step, update no matter what. Use with caution!
     */
    public function updatedrush($opts = ['yes|y' => false])
    {
        if ($this->checkDrushCompatibility(self::DRUSH_VERSION)) {
            $this->io()->text('Drush is up-to-date!');
            return true;
        }

        $this->io()->caution(
            "This command will attempt to make changes to the root user's " .
            "composer directory and should ONLY be run if you are using dkan-tools in Docker."
        );
        $confirmation = "Continue, removing existing global/root composer files?";
        if (!$opts['yes'] && !$this->io()->confirm($confirmation)) {
            return false;
        }

        $result = $this->taskFilesystemStack()->stopOnFail()
            ->remove('/root/.composer/vendor')
            ->remove('/root/.composer/composer.lock')
            ->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('could not remove root composer files.');
            return $result;
        }
        $result = $this->taskComposerRequire()
            ->dependency('drush/drush', self::DRUSH_VERSION)
            ->dir('/root/.composer')
            ->run();
        return $result;
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

    private function mergeComposerConfig()
    {
        $drupal_config_path = Util::getProjectDirectory() . "/docroot/composer.json";
        $drupal_config = json_decode(file_get_contents($drupal_config_path));
        $custom_path = Util::getProjectDirectory() . "/src/make/composer.json";

        $include_array = $drupal_config->extra->{"merge-plugin"}->include;

        // only add the extra composr file if needed.
        if (!in_array($custom_path, $include_array) && is_file($custom_path)) {
            $include_array[] = $custom_path;
            $drupal_config->extra->{"merge-plugin"}->include = $include_array;
            file_put_contents($drupal_config_path, json_encode($drupal_config, JSON_PRETTY_PRINT));
        }
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
            $this->io()->success("Successfully linked $target to $link");
        }
        return $result;
    }

    /**
     * Download frontend App.
     */
    private function downloadFrontend()
    {
        $result = $this->taskExec('git clone')
            ->option('depth=1')
            ->option('branch', 'master')
            ->arg('https://github.com/GetDKAN/data-catalog-frontend.git')
            ->arg('frontend')
            ->dir('src')
            ->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not download front-end app');
            return $result;
        }
        $result = $this->_deleteDir('src/frontend/.git');
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not remove front-end git folder');
            return $result;
        }

        $this->io()->success('Successfully added the frontend application');
        return $result;
    }

    /**
     * Install frontend app.
     */
    public function frontendInstall()
    {
        $task = $this->taskExec("npm install")->dir("src/frontend");
        $result = $task->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not install front-end node modules');
            return $result;
        }
        $this->io()->success('Successfull');
    }

    /**
     * Build frontend app.
     */
    public function frontendBuild()
    {
        $task = $this->taskExec("npm run build")->dir("src/frontend");
        $result = $task->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not build the front-end');
            return $result;
        }
        $this->io()->success('Successfull');
    }

    public function install($opts = ['frontend' => false])
    {
        $result = $this->taskExec('drush si -y')
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

    /**
     * Edit the default DKAN composer.json file.
     */
    private function editJson($opts)
    {
        $file = Util::getProjectDirectory() . "/src/make/composer.json";
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        if ($opts['tag']) {
            $data['require']['getdkan/dkan2'] = $opts['tag'];
        } elseif ($opts['branch']) {
            $data['require']['getdkan/dkan2'] = 'dev-' . $opts['branch'];
        }
        $newFile = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($file, $newFile);

        $this->io()->success('Successfully updated the composer.json file');
    }
}
