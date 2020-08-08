<?php

namespace DkanTools\Command;

use DkanTools\Util\Util;
use Robo\Tasks;

class InstallCommands extends Tasks
{
    const DRUSH = "../vendor/bin/drush";
    /**
     * Perform Drupal/DKAN database installation
     *
     * @option existing-config
     */
    public function install($opts = ['existing-config' => false])
    {
        if ($opts['existing-config']) {
            $result = $this->taskExec('drush si -y --existing-config')
                ->dir(Util::getProjectDocroot())
                ->run();
        } else {
            $result = $this->standardInstallation();
        }

        if ($opts['demo'] === true) {
            $opts = ['frontend' => false];
            $result = $this->setupDemo();
        }
        if ($opts['frontend'] === true) {
            $result = $this->taskExec('drush en -y')
                ->arg('frontend')
                ->dir(Util::getProjectDocroot())
                ->run();
        }
        // Workaround for https://www.drupal.org/project/drupal/issues/3091285.
        $result = $this->taskExec('chmod u+w sites/default')
            ->dir(Util::getProjectDocroot())
            ->run();

        return $result;
    }

    private function standardInstallation()
    {
        $this->taskExecStack()
            ->stopOnFail()
            ->exec(self::DRUSH . ' si standard -y')
            ->exec(self::DRUSH . " en dkan config_update_ui -y")
            ->exec(self::DRUSH . " config-set system.performance css.preprocess 0 -y")
            ->exec(self::DRUSH . " config-set system.performance js.preprocess 0 -y")
            ->exec(self::DRUSH . " config-set system.site page.front \"/home\" -y")
            ->dir(Util::getProjectDocroot())
            ->run();
    }

    /**
     * Install DKAN sample content.
     */
    public function installSample()
    {
        $this->taskExecStack()
            ->stopOnFail()
            ->exec(self::DRUSH . ' en sample_content -y')
            ->exec(self::DRUSH . ' dkan:sample-content:create')
            ->exec(self::DRUSH . ' queue:run datastore_import')
            ->exec(self::DRUSH . ' dkan:metastore-search:rebuild-tracker')
            ->exec(self::DRUSH . ' sapi-i')
            ->dir(Util::getProjectDocroot())
            ->run();
    }

    /**
     * Install DKAN demo (includes frontend and sample content)
     *
     * @aliases demo
     */
    public function installDemo()
    {
        $this->install();
        $this->installSample();

        $this->taskExecStack()
            ->stopOnFail()
            ->exec(self::DRUSH . ' en frontend -y')
            ->exec('dktl frontend:get')
            ->exec('dktl frontend:install')
            ->exec('dktl frontend:build')
            ->exec(self::DRUSH . ' cr')
            ->dir(Util::getProjectDocroot())
            ->run();
    }
}
