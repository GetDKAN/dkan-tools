<?php

namespace DkanTools\Drupal\V8;

use DkanTools\Util\Util;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class BasicCommands extends \Robo\Tasks
{

    const DRUSH_VERSION = '9.5.2';

    /**
     * Get Drupal.
     *
     * We get DKAN on the make step.
     */
    public function get($version)
    {
        Util::prepareTmp();

        $archive = $this->getDrupalArchive($version);
        $task = $this->taskExec("tar -xvzf {$archive}")->dir(Util::TMP_DIR);
        $task->run();

        // At this point we should have the unbuilt Drupal folder in tmp.
        $this->drupalTempReplace(str_replace(".tar.gz", "", $archive));
        Util::cleanupTmp();
    }

    private function getDrupalArchive($version)
    {
        $fileName = "drupal-{$version}";
        $archive = Util::TMP_DIR . "/{$fileName}";
        if (file_exists($archive)) {
            $this->io()->warning("Drupal archive $fileName.tar.gz already exists; skipping download, will attempt extraction.");
            return $archive;
        }

        $sources = [
            "https://ftp.drupal.org/files/projects/{$fileName}.tar.gz",
        ];

        $source = null;
        foreach ($sources as $s) {
            if (Util::urlExists($s)) {
                $source = $s;
                break;
            }
        }

        if (!isset($source)) {
            $this->io()->error("No archive available for Drupal $version.");
            return;
        }

        $this->io()->section("Getting Drupal from {$source}");
        $this->taskExec("wget -O {$archive} {$source}")->run();
        return $archive;
    }

    private function drupalTempReplace($tmp_drupal)
    {
        $drupal_permanent = Util::getProjectDirectory() . '/docroot';
        $replaced = false;
        if (file_exists($drupal_permanent)) {
            if ($this->io()->confirm("Are you sure you want to replace your current DKAN profile directory?")) {
                $this->_deleteDir($drupal_permanent);
                $replaced = true;
            } else {
                $this->say('Canceled.');
                return;
            }
        }
        $this->_exec('mv ' . $tmp_drupal . ' ' . $drupal_permanent);
        $verb = $replaced ? 'replaced' : 'created';
        $this->say("Drupal directory $verb.");
    }

    /**
     * Get all necessary dependencies and "make" a working codebase.
     * 
     * Running `dktl make` will:
     *   1. Modify the stock drupal composer.json file to merge in anything in src/make
     *   2. Use composer to download and build all php dependencies.
     *   3. Symlink a number of dirs from /src into docroot.
     *   4. If requested, pull the DKAN frontend application (Interra) into docroot.
     *
     * @option prefer dist|source. Defaults to `dist`. Install packages either from source or dist when available.
     * @option no-dev Skip installing packages listed in require-dev.
     * @option optimize-autoloader Convert PSR-0/4 autoloading to classmap to get a faster autoloader..
     * @option frontend Build with the DKAN frontend application
     */
    public function make($opts = [
        'yes|y' => false,
        'prefer-dist' => false,
        'no-dev'=> false,
        'optimize-autoloader'=> false,
        'frontend' => false
        ])
    {

        $docroot = Util::getProjectDirectory() . "/docroot";
        if (!file_exists($docroot)) {
            throw new \Exception("Use 'dktl get <drupal version>' before trying to make the project.");
        }

        $composer_bools = ['yes', 'no-dev', 'optimize-autoloader'];
        // build some options.
        $boolOptions = '';
        foreach ($opts as $opt => $optValue) {
            if (in_array($opt, $composer_bools)) {
                if (is_bool($optValue) && $optValue) {
                    $boolOptions .= '--' . $opt . ' ';
                }
            }
        }

        $this->mergeComposerConfig();

        $this->_exec("composer --no-progress --working-dir={$docroot} {$boolOptions} install");
        $this->docrootSymlink('src/site', 'docroot/sites/default');
        $this->docrootSymlink('src/modules', 'docroot/modules/custom');
        $this->docrootSymlink('src/themes', 'docroot/themes/custom');
        $this->docrootSymlink('docroot/vendor/bower-asset', 'docroot/libraries');
        if ($opts['frontend'] === true) {
            $this->say('BUILDING FRONTEND');
            $this->downloadInterra();
            $this->installInterra();
            $this->buildInterra();
        }

        if (!$this->checkDrushCompatibility()) {
            $this->io()->warning('Your version of Drush is incompatible with DKAN2. Please upgrade to the latest Drush 9.x! If you are using the dkan-tools docker setup, try "dktl updatedrush".');
        }
    }

    /**
     * Update the version of Drush used in the container.
     */
    public function updatedrush() {
        if ($this->checkDrushCompatibility(self::DRUSH_VERSION)) {
            $this->say('Drush is up-to-date!');
            return true;
        }
        if ($this->io()->confirm("This command will attempt to make changes to the root user's composer directory and should only be run if you are using dkan-tools in Docker. Continue?")) {
            $this->taskExec('rm -rf vendor composer.*')->dir('/root/.composer')->run();
            $result = $this->taskComposerRequire()->dependency('drush/drush', self::DRUSH_VERSION)
                ->dir('/root/.composer')
                ->run();
            return $result;
        }
    }

    private function mergeComposerConfig() {
        $drupal_config_path = Util::getProjectDirectory() . "/docroot/composer.json";
        $drupal_config = json_decode(file_get_contents($drupal_config_path));
        $custom_path = Util::getProjectDirectory() . "/src/make/composer.json";

        $include_array = $drupal_config->extra->{"merge-plugin"}->include;
        
        // only add the extra composr file if needed.
        if(!in_array($custom_path, $include_array) && is_file($custom_path))
        {
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

        if (!file_exists($target) || !file_exists('docroot')) {
            $this->io()->error("Could not link $target. Folders $target and 'docroot' must both be present to create link.");
            exit;
        }

        $result = $this->taskFilesystemStack()->stopOnFail()
            ->remove('docroot/modules/custom')
            ->symlink('src/modules', 'docroot/modules/custom')
            ->run();

        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not create link');
        }
        else {            
            $this->io()->success("Successfully linked $target to $link");
        }
        return $result;
    }

    /**
     * Download Interra frontend.
     */
    private function downloadInterra()
    {
        if (file_exists('docroot/data-catalog-frontend')) {
            if (!$this->io()->confirm('Frontend application already exists in docroot. Remove and re-install?')) {
                return false;
            }
            $this->_deleteDir('docroot/data-catalog-frontend');
        }
        $result = $this->_exec('git clone --depth=1 --branch=master https://github.com/interra/data-catalog-frontend.git docroot/data-catalog-frontend');
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not download Interra front-end');
            return $result;
        }
        $result = $this->_exec('rm -r docroot/data-catalog-frontend/.git');
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not remove Interra front-end git folder');
            return $result;
        }
        $this->io()->success('Successfull');
    }

    /**
     * Install Interra frontend.
     */
    private function installInterra()
    {

        $task = $this->taskExec("npm install")->dir("docroot/data-catalog-frontend");
        $result = $task->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not install Interra front-end node modules');
            return $result;
        }

        $this->io()->success('Successfull');
    }

    /**
     * Build Interra frontend.
     */
    private function buildInterra()
    {

        $result = $this->_exec('sed -i "s/https:\/\/interra.github.io\/data-catalog-frontend/\/data-catalog-frontend\/build/g" docroot/data-catalog-frontend/package.json');
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not install Interra front-end node modules');
            return $result;
        }

        $task = $this->taskExec("npm run build")->dir("docroot/data-catalog-frontend");
        $result = $task->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not install Interra front-end node modules');
            return $result;
        }
        $this->io()->success('Successfull');
    }

    /**
     * Link src/themes to  docroot/sites/all/modules/themes.
     */
    private function linkJsonForm()
    {
        $result = $this->_exec('ln -s vendor/bower-asset docroot/libraries');
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not create link');
            return $result;
        }

        $this->io()->success('Successfull');
    }

    public function install() {
        $this->_exec("dktl drush si -y");
    }
    
    /**
     * Proxy to the phpunit binary.
     *
     * @param array $args  Arguments to append to full phpunit command.
     */
    public function phpunit(array $args) {

        $proj_dir = Util::getProjectDirectory();

        $phpunit_executable = "{$proj_dir}/docroot/vendor/bin/phpunit";

        $phpunitExec = $this->taskExec($phpunit_executable);

        foreach ($args as $arg) {
            $phpunitExec->arg($arg);
        }

        return $phpunitExec->run();
    }

    private function checkDrushCompatibility($version = '9') {
        if (version_compare($this->getDrushVersion(), $version) >= 0) {
            return true;
        }
        return false;
    }

    private function getDrushVersion() {
        $result = $this->taskExec('drush --version')
            ->dir(Util::getProjectDocroot())
            ->printOutput(FALSE)
            ->run();
        preg_match('/.+?(\d+\.\d+\.\d+)/', $result->getMessage(), $matches);
        if (!isset($matches[1])) {
            throw new \Exception("Could not determine Drush version on this system.");
        }
        return $matches[1];
    }

}
