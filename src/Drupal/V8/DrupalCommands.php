<?php
namespace DkanTools\Drupal\V8;

use DkanTools\Util\Util;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class DrupalCommands extends \Robo\Tasks
{
    /**
     * Run make for Drupal core.
     */
    public function drupalMake($opts = ['yes|y' => false])
    {
        $this->linkSitesDefault();
        $this->linkModules();
        $this->linkThemes();

    }


    /**
     * Get the mysql connection string.
     *
     * @todo Stop hardcoding and get from env or make dynamic.
     */
    public function getDbUrl()
    {
        return 'mysql://drupal:123@db/drupal';
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

        $result = $this->_exec('ln -s ../../../../src/modules docroot/modules/custom');
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
        $result = $this->_exec('ln -s ../../../../src/themes docroot/themes/custom');
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not crete link');
            return $result;
        }

        $this->io()->success('Successfully linked src/themes to docroot/sites/all/themes/custom');
    }
}
