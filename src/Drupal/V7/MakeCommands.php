<?php

namespace DkanTools\Drupal\V7;

use DkanTools\Util\Util;
use Symfony\Component\Yaml\Yaml;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class MakeCommands extends \Robo\Tasks
{

    /**
    * Fully make DKAN and Drupal core, overwriting existing files.
    *
    * To overwrite either Drupal core or DKAN make files, use the files in
    * /src/make
    *
    * @option $yes
    *   Remove all existing files without asking for confirmation
    * @option $keep-git
    *   Skip default behavior of deleting all .gitignore files in /docroot
    */
    public function make($opts = ['yes|y' => false, 'keep-git' => false])
    {
        $yes = (isset($opts['yes'])) ? $opts['yes'] : false;
        $make_opts = ['yes' => $yes];

        $status = $this->makeProfile($make_opts);
        if ($status) {
            $status = $this->makeDrupal($opts);
            $status = $this->dkanPatch();
        }
        return $status;
    }

    /**
    * Run the DKAN make file and apply any overrides from /config.
    *
    * @option $yes
    *   Remove all existing files without asking for confirmation
    */
    public function makeProfile($opts = ['yes|y' => false])
    {
        if (file_exists('dkan/modules/contrib')) {
            $confirmMessage = 'DKAN dependencies have already been downloaded. ' .
                'Would you like to delete and download them again?';
            if (!$opts['yes'] && !$this->io()->confirm($confirmMessage)) {
                $this->io()->warning('Make aborted');
                return false;
            }
            $this->_deleteDir([
                'dkan/modules/contrib',
                'dkan/themes/contrib',
                'dkan/libraries'
            ]);
        }

        $result = $this->taskExec('drush -y make dkan/drupal-org.make')
            ->arg('--contrib-destination=./')
            ->arg('--no-core')
            ->arg('--root=docroot')
            ->arg('--no-recursion')
            ->arg('--no-cache')
            ->arg('--verbose')
            ->arg('--overrides=src/make/dkan.make')
            ->arg('--concurrency=' . Util::drushConcurrency())
            ->arg('dkan')
            ->run();

        return $result->wasSuccessful();
    }

    /**
     * Run make for Drupal core.
     *
     * @option $yes
     *   Remove all existing files without asking for confirmation
     * @option $keep-git
     *   Skip default behavior of deleting all .gitignore files in /docroot
     */
    public function makeDrupal($opts = ['yes|y' => false, 'keep-git' => false])
    {
        if (!file_exists('dkan')) {
            throw \Exception('We need DKAN before making Drupal');
            return false;
        }
        if (!Yaml::parse(file_get_contents(Util::getProjectDirectory() . '/src/make/drupal.make'))) {
            return false;
        }
        if (file_exists('docroot')) {
            if (!$opts['yes'] && !$this->io()->confirm('docroot folder alredy exists. ' .
                'Delete it and reinstall drupal?')) {
                $this->io()->warning('Make aborted');
                return false;
            }
            $this->_deleteDir('docroot');
        }

        $concurrency = Util::drushConcurrency();

        $result = $this->taskExec('drush make -y dkan/drupal-org-core.make')
        ->arg('--root=docroot')
        ->arg('--concurrency=' . $concurrency)
        ->arg('--prepare-install')
        ->arg('--overrides=../src/make/drupal.make')
        ->arg('docroot')
        ->run();

        if ($result->getExitCode() == 0 && file_exists('docroot')) {
            $this->io()->success('Drupal core successfully downloaded to docroot folder.');
        }

        $this->linkDkan();
        $this->linkSitesDefault();
        $this->linkSrcSitesAll('modules', 'modules/custom');
        $this->linkSrcSitesAll('themes', 'themes/custom');
        if (!$opts['keep-git']) {
            $this->makeRmGit();
        }
    }

    /**
     * Apply patches to dkan.
     */
    public function dkanPatch()
    {
        $patchDir = Util::getProjectDirectory() . '/src/patches/dkan';
        $patchFiles = [];

        if (is_dir($patchDir)) {
            $patchFiles += scandir($patchDir);
        }
        foreach ($patchFiles as $fileName) {
            $info = pathinfo($fileName);
            if (in_array($info['extension'], ['patch', 'diff'])) {
                $fileRelPath = '../src/patches/dkan/' . $fileName;
                $this->say($fileRelPath);
                $result = $this->taskExec('patch -p1 --no-backup-if-mismatch -r - <')
                    ->arg($fileRelPath)
                    ->dir('dkan')
                    ->run();
                $this->say($result->getMessage());
            }
        }
    }

    /**
     * Link the DKAN folder to docroot/profiles.
     */
    private function linkDkan()
    {
        if (!file_exists('dkan') || !file_exists('docroot')) {
            $this->io()->error("Could not link profile folder. " .
                "Folders 'dkan' and 'docroot' must both be present to create link.");
            exit;
        }

        $result = $this->_exec('ln -s ../../dkan docroot/profiles/dkan');
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not create link');
            return $result;
        }

        $this->io()->success('Successfully linked DKAN to docroot/profiles');
    }

    /**
     * Link src/site to docroot/sites/default.
     */
    private function linkSitesDefault()
    {
        if (!file_exists('src/site') || !file_exists('docroot')) {
            $sitesError = "Could not link sites/default folder. " .
                "Folders 'src/site' and 'docroot' must both be present to create the link.";
            $this->io()->error($sitesError);
            exit;
        }

        $this->_exec('rm -rf docroot/sites/default');
        $this->_exec('ln -s ../../src/site docroot/sites/default');

        $this->io()->success('Successfully linked src/site folder to docroot/sites/default');
    }

    /**
     * Link src/modules to  docroot/sites/all/modules/custom.
     */
    private function linkSrcSitesAll($original, $dest)
    {
        if (!file_exists("src/$original") || !file_exists('docroot')) {
            $this->io()->error("Could not link {$original}. " .
                "Folders 'src/{$original}' and 'docroot' must both be present to create link.");
            exit;
        }

        $result = $this->_exec("ln -s ../../../../src/{$original} docroot/sites/all/{$dest}");
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not create link');
            return $result;
        }
        $this->io()->success("Successfully linked src/{$original} to docroot/sites/all/{$dest}");
    }
    
    /**
     * Remove all .git and .gitignore files from docroot and dkan.
     */
    public function makeRmGit()
    {
        foreach (['docroot', 'dkan'] as $dir) {
            $gitignores = [];
            exec("find {$dir} -type f -name '.gitignore'", $gitignores);

            foreach ($gitignores as $gitignore) {
                `rm {$gitignore}`;
                $this->io()->note("Removing: {$gitignore}");
            }

            $gits = [];
            exec("find {$dir} -type d -name '.git'", $gits);

            foreach ($gits as $git) {
                `rm -R {$git}`;
                $this->io()->note("Removing: {$git}");
            }
        }
    }
}
