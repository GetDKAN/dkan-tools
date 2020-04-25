<?php

namespace DkanTools\Command;

use DkanTools\Util\Util;
use Robo\Tasks;

class InstallCommands extends Tasks
{
    public function install($opts = ['frontend' => false, 'existing-config' => false, 'demo' => false])
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
                ->arg('dkan_frontend')
                ->dir(Util::getProjectDocroot())
                ->run();
        }
        return $result;
    }

    private function standardInstallation()
    {
        `dktl drush si standard -y`;
        `dktl drush en dkan dkan_admin dkan_harvest dblog config_update_ui -y`;
        `dktl drush config-set system.performance css.preprocess 0 -y`;
        `dktl drush config-set system.performance js.preprocess 0 -y`;
        return $this->taskExec('drush config-set system.site page.front "/home" -y')
            ->dir(Util::getProjectDocroot())
            ->run();
    }

    private function setupDemo()
    {
        `dktl drush en dkan_dummy_content dkan_frontend -y`;
        `dktl drush dkan-dummy-content:create`;
        `dktl drush queue:run dkan_datastore_import`;
        `dktl drush dkan-search:rebuild-tracker`;
        `dktl drush sapi-i`;
        `dktl frontend:install`;
        `dktl frontend:build`;
        return  $this->taskExec('drush cr')
            ->dir(Util::getProjectDocroot())
            ->run();
    }
}
