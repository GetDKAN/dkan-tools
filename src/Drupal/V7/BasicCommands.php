<?php

namespace DkanTools\Drupal\V7;


class BasicCommands extends \Robo\Tasks
{

    /**
     * Run DKAN DB installation.
     *
     * @option @account-pass
     *   Password to assign to admin user. Defaults to "admin".
     * @option $site-name
     *   Site name for your new Drupal site. Defaults to "DKAN".
     */
    public function install($opts = ['account-pass' => 'admin', 'site-name' => 'DKAN'])
    {
        if (!file_exists('docroot/modules') || !file_exists('dkan/modules/contrib')) {
            throw new \Exception('Codebase not fully built, install could not procede.');
        }
        $result = $this->taskExec('drush -y si dkan')
            ->dir('docroot')
            ->arg('--verbose')
            ->arg("--account-pass={$opts['account-pass']}")
            ->arg("--site-name={$opts['site-name']}")
            ->rawArg('install_configure_form.update_status_module=\'array(FALSE,FALSE)\'')
            ->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('Installation command failed.');
            return $result;
        }
        $this->io()->success('Installation completed successfully.');

        if (!file_exists('backups')) {
            $this->_mkdir('backups');
        }
        $result = $this->taskExec('drush sql-dump | gzip >')
            ->arg('../backups/last_install.sql.gz')
            ->dir('docroot')
            ->run();
        if ($result->getExitCode() != 0) {
            $this->io()->success('Backup created in "backups" folder.');
        }
    }

}