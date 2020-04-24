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
            $this->standardInstallation();
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
        $result = $this->taskExec('drush si standard -y')
            ->dir(Util::getProjectDocroot())
            ->run();
        $result = $this->taskExec('drush en dkan2 dkan_admin dkan_harvest dblog config_update_ui -y')
            ->dir(Util::getProjectDocroot())
            ->run();
        $result = $this->taskExec('drush config-set system.performance css.preprocess 0 -y')
            ->dir(Util::getProjectDocroot())
            ->run();
        $result = $this->taskExec('drush config-set system.performance js.preprocess 0 -y')
            ->dir(Util::getProjectDocroot())
            ->run();
        $result = $this->taskExec('drush config-set system.site page.front "/home" -y')
            ->dir(Util::getProjectDocroot())
            ->run();
    }

    private function setupDemo()
    {
        $result = $this->taskExec('drush en dkan_dummy_content dkan_frontend -y')
            ->dir(Util::getProjectDocroot())
            ->run();
        $result = $this->taskExec('drush dkan-dummy-content:create')
            ->dir(Util::getProjectDocroot())
            ->run();
        $result = $this->taskExec('drush dkan-search:rebuild-tracker')
            ->dir(Util::getProjectDocroot())
            ->run();
        $result = $this->taskExec('drush sapi-i')
            ->dir(Util::getProjectDocroot())
            ->run();
        $result = $this->taskExec('frontend:install')
            ->dir(Util::getProjectDocroot())
            ->run();
        $result = $this->taskExec('frontend:build')
            ->dir(Util::getProjectDocroot())
            ->run();
        $result = $this->taskExec('drush cr')
            ->dir(Util::getProjectDocroot())
            ->run();
    }
}
