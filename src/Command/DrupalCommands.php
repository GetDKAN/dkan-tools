<?php
namespace DkanTools\Command;

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
        if (file_exists('dkan')) {
            if (file_exists('docroot')) {
                if (!$opts['yes'] && !$this->io()->confirm('docroot folder alredy exists. Delete it and reinstall drupal?')) {
                    $this->io()->warning('Make aborted');
                    exit;
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
        } else {
            $this->io()->error('We need DKAN before making Drupal');
        }
    }

    /**
     * Run Drupal minimal installation script. Takes mysql url as optional
     * argument.
     *
     * @todo Implement settings.php rewrite function from ahoy.
     *
     * @param string $db Mysql connection string.
     */
    public function drupalInstallMin($db = null)
    {
        $db = $db ? $db : $this->getDbUrl();
        $update = "install_configure_form.update_status_module='array(false,false)'";

        $result = $this->taskExec('drush -y si minimal')->dir('docroot')
            ->arg('--verbose')
            ->arg('--sites-subdir=default')
            ->arg('--account-pass=admin')
            ->arg("--db-url=$db")
            ->rawArg($update)
            ->run();
        if ($result->getExitCode() == 0) {
            $this->io()->success('Drupal successfully installed with minimal profile. Type "dktl docker:url" to test.');
        }
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
}
