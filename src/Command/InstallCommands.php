<?php

namespace DkanTools\Command;

use DkanTools\Util\Util;
use Robo\Tasks;

class InstallCommands extends Tasks
{
    public function install($opts = [
        'frontend' => false,
        'existing-config' => false,
        'demo-backend' => false,
        'demo' => false
        ])
    {
        if ($opts['existing-config']) {
            $result = $this->taskExec('drush si -y --existing-config')
                ->dir(Util::getProjectDocroot())
                ->run();
        } else {
            $result = $this->standardInstallation();
        }

        if ($opts['demo-backend'] === true) {
            $result = $this->buildBackend();
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
        // Workaround for https://www.drupal.org/project/drupal/issues/3091285.
        $result = $this->taskExec('chmod u+w sites/default')
            ->dir(Util::getProjectDocroot())
            ->run();

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

    private function buildBackend()
    {
        `dktl drush en dkan_dummy_content -y`;
        `dktl drush dkan-dummy-content:create`;
        `dktl drush queue:run dkan_datastore_import`;
        `dktl drush dkan-search:rebuild-tracker`;
        return  $this->taskExec(`drush sapi-i`)
            ->dir(Util::getProjectDocroot())
            ->run();
    }

    private function setupDemo()
    {
        $this->buildBackend();
        `dktl drush en dkan_frontend -y`;
        `dktl frontend:get`;
        `dktl frontend:install`;
        `dktl frontend:build`;
        return  $this->taskExec('drush cr')
            ->dir(Util::getProjectDocroot())
            ->run();
    }
}
