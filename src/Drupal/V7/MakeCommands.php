<?php
namespace DkanTools\Drupal\V7;

use DkanTools\Util\Util;

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
    * @option $keep-gitignores
    *   Skip default behavior of deleting all .gitignore files in /docroot
    */
    public function make($opts = ['yes|y' => false, 'keep-gitignores' => false])
    {
        $yes = (isset($opts['yes|y'])) ? $opts['yes|y'] : false;
        $make_opts = ['yes|y' => $yes];

        $this->makeProfile($make_opts);
        $this->makeDrupal($opts);
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
          if (!$opts['yes|y'] && !$this->io()->confirm('DKAN dependencies have already been dowloaded. Would you like to delete and dowload them again?')) {
              $this->io()->warning('Make aborted');
              return false;
          }
          $this->_deleteDir([
              'dkan/modules/contrib',
              'dkan/themes/contrib',
              'dkan/libraries'
          ]);
      }

      $this->taskExec('drush -y make dkan/drupal-org.make')
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
    }

    /**
     * Run make for Drupal core.
     *
     * @option $yes
     *   Remove all existing files without asking for confirmation
     * @option $keep-gitignores
     *   Skip default behavior of deleting all .gitignore files in /docroot
     */
    public function makeDrupal($opts = ['yes|y' => false, 'keep-gitignores' => false])
    {
        if (!file_exists('dkan')) {
            throw \Exception('We need DKAN before making Drupal');
            return false;
        }
        if (file_exists('docroot')) {
            if (!$opts['yes'] && !$this->io()->confirm('docroot folder alredy exists. Delete it and reinstall drupal?')) {
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
        $this->linkModules();
        $this->linkThemes();
        if (!$opts['keep-gitignores']) {
          $this->removeGitIgnores();
        }

    }
    /**
     * Link the DKAN folder to docroot/profiles.
     */
    private function linkDkan()
    {
        if (!file_exists('dkan') || !file_exists('docroot')) {
            $this->io()->error("Could not link profile folder. Folders 'dkan' and 'docroot' must both be present to create link.");
            exit;
        }

        $result = $this->_exec('ln -s ../../dkan docroot/profiles/dkan');
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not crete link');
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

        $result = $this->_exec('ln -s ../../../../src/modules docroot/sites/all/modules/custom');
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
        $result = $this->_exec('ln -s ../../../../src/themes docroot/sites/all/themes/custom');
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not crete link');
            return $result;
        }

        $this->io()->success('Successfully linked src/themes to docroot/sites/all/themes/custom');
    }

    /**
     * Remove all gitignores from docroot.
     */
    private function removeGitIgnores() {
        $gitignores = [];
        exec("find docroot -type f -name '.gitignore'", $gitignores);

        foreach ($gitignores as $gitignore) {
            `rm {$gitignore}`;
        }

        $gitignores = [];
        exec("find dkan -type f -name '.gitignore'", $gitignores);

        foreach ($gitignores as $gitignore) {
            `rm {$gitignore}`;
        }
    }

}
