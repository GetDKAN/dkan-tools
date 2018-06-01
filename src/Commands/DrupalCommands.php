<?php
namespace DkanTools\Commands;

use DkanTools\Util\Docker;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class DrupalCommands extends \Robo\Tasks
{
    function DrupalRebuild(string $db = NULL)
    {
        if (!isset($db)) {
            $db = Docker::getDbUrl();
        }
        $this->say($db);
        $dbContainer = Docker::getDbContainer();
        $this->say($dbContainer);
        $dbContainer = Docker::getDbContainer();
        $this->say($dbContainer);
        $dbContainer = Docker::getDbContainer();
        $this->say($dbContainer);
        die();
        $concurrency=`grep -c ^processor /proc/cpuinfo`;
        $update = "install_configure_form.update_status_module='array(false,false)'";

        return $this->taskExecStack()
            ->exec("drush --root=docroot  make --concurrency=$concurrency --prepare-install dkan/drupal-org-core.make docroot --yes")
            ->exec("drush --root=docroot -y --verbose si minimal --sites-subdir=default --account-pass='admin' --db-url=$db $update")
            ->exec('ln -s ../../dkan docroot/profiles/dkan')
            ->run();
    }
}
