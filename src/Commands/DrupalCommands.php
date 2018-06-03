<?php
namespace DkanTools\Commands;

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
    function drupalMake($opts = ['yes|y' => false])
    {
        if (file_exists('docroot')) {
            if (!$opts['yes'] && !$this->io()->confirm('docroot folder alredy exists. Delete it and reinstall drupal?')) {
                $this->io()->warning('Make aborted');
                exit;
            }
            $this->_deleteDir('docroot');
        }

        $result = $this->taskExec('drush make -y dkan/drupal-org-core.make')
            ->arg('--root=docroot')
            ->arg('--concurrency=' . Util::drushConcurrency())
            ->arg('--prepare-install')
            ->arg('--overrides=../config/drupal-org-core.make')
            ->arg('docroot')
            ->run();

        if ($this->say($result->getExitCode()) == 0 && file_exists('docroot')) {
            $this->io()->success('Drupal core successfully downloaded to docroot folder.');
        }

        $this->drupalDkanLink();
    }

    function drupalDkanLink()
    {
        if (file_exists('dkan') && file_exists('docroot')) {
            $this->_exec('ln -s ../../dkan docroot/profiles/dkan');
            $this->io()->success('Successfully linked DKAN to docroot/profiles');
        }
        else {
            throw new \Exception("Could not link profile folder. Folders 'dkan' and 'docroot' must both be present to create link.");
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
    function drupalInstallMin($db = NULL)
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
    function getDbUrl()
    {
        return 'mysql://drupal:123@db/drupal';
    }

}
