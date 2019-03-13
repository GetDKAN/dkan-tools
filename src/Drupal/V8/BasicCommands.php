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
     * Get all necessary dependencies.
     */
    public function make($opts = ['yes|y' => false])
    {
        $this->mergeComposerConfig();
        $docroot = Util::getProjectDirectory() . "/docroot";
        $this->_exec("composer --working-dir={$docroot} update");
        $this->linkSitesDefault();
        $this->linkModules();
        $this->linkThemes();
        $this->downloadInterra();
        $this->installInterra();
        $this->buildInterra();
        $this->updateDrush();
    }

    private function updateDrush() {
        $this->_exec("rm -rf /root/.composer/vendor");
        $this->_exec("rm -rf /root/.composer/composer.lock");
        $this->_exec("rm -rf /root/.composer/composer.json");
        $this->_exec("composer global require drush/drush:9.5.2");
    }

    private function mergeComposerConfig() {
        $drupal_config_path = Util::getProjectDirectory() . "/docroot/composer.json";
        $drupal_config = json_decode(file_get_contents($drupal_config_path));
        $custom_path = Util::getProjectDirectory() . "/src/make/composer.json";

        $include_array = $drupal_config->extra->{"merge-plugin"}->include;
        $include_array[] = $custom_path;
        $drupal_config->extra->{"merge-plugin"}->include = $include_array;
        file_put_contents($drupal_config_path, json_encode($drupal_config, JSON_PRETTY_PRINT));
    }

    /**
     * Link src/site to docroot/sites/default.
     */
    private function linkSitesDefault()
    {
        if (!file_exists('src/site') || !file_exists('docroot')) {
            $this->io()->error("Could not link sites/default folder. Folders 'src/site' and 'docroot' must both be present to create the link.");
            exit;
        }

        $this->_exec('rm -rf docroot/sites/default');
        $this->_exec('ln -s ../../src/site docroot/sites/default');

        $this->io()->success('Successfully linked src/site folder to docroot/sites/default');
    }

    /**
     * Link src/modules to  docroot/sites/all/modules/custom.
     */
    private function linkModules()
    {
        if (!file_exists('src/modules') || !file_exists('docroot')) {
            $this->io()->error("Could not link modules. Folders 'src/modules' and 'docroot' must both be present to create link.");
            exit;
        }

        $result = $this->_exec('ln -s ../../src/modules docroot/modules/custom');
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not crete link');
            return $result;
        }
        $this->io()->success('Successfully linked src/modules to docroot/sites/all/modules/custom');
    }

    /**
     * Link src/themes to  docroot/sites/all/modules/themes.
     */
    private function linkThemes()
    {
        if (!file_exists('src/themes') || !file_exists('docroot')) {
            throw new \Exception("Could not link themes. Folders 'src/themes' and 'docroot' must both be present to create link.");
            return;
        }
        $result = $this->_exec('ln -s ../../src/themes docroot/themes/custom');
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not crete link');
            return $result;
        }

        $this->io()->success('Successfully linked src/themes to docroot/sites/all/themes/custom');
    }

    /**
     * Download Interra frontend.
     */
    private function downloadInterra()
    {
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
            $this->io()->error('Could not crete link');
            return $result;
        }

        $this->io()->success('Successfull');
    }

    public function install() {
        $this->_exec("dktl drush si -y");
    }
}
