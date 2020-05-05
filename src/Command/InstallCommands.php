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
        `dktl drush en dkan dblog config_update_ui -y`;
        `dktl drush config-set system.performance css.preprocess 0 -y`;
        `dktl drush config-set system.performance js.preprocess 0 -y`;
        return $this->taskExec('drush config-set system.site page.front "/home" -y')
            ->dir(Util::getProjectDocroot())
            ->run();
    }

    private function setupDemo()
    {
        `dktl drush en sample_content frontend -y`;
        `dktl drush dkan:sample-content:create`;
        `dktl drush queue:run datastore_import`;
        `dktl drush dkan:metastore-search:rebuild-tracker`;
        `dktl drush sapi-i`;
        `dktl frontend:install`;
        `dktl frontend:build`;
        return  $this->taskExec('drush cr')
            ->dir(Util::getProjectDocroot())
            ->run();
    }
}
