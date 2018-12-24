<?php
namespace DkanTools\Drupal\V7;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class DrupalCommands extends \Robo\Tasks
{
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
}
