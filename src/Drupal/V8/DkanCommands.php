<?php

namespace DkanTools\Drupal\V8;

use DkanTools\Util\Util;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class DkanCommands extends \Robo\Tasks
{
    /**
     * Run DKAN Cypress Tests.
     */
    public function dkanTestCypress() {
        $proj_dir = Util::getProjectDirectory();
        $this->taskExec("npm install cypress")
            ->dir("{$proj_dir}/docroot/profiles/contrib/dkan2")
            ->run();

        $this->taskExec("CYPRESS_baseUrl=http://web npx cypress run")
            ->dir("{$proj_dir}/docroot/profiles/contrib/dkan2")
            ->run();
    }

    /**
     * Run DKAN PhpUnit Tests.
     */
    public function dkanTestPhpunit() {
        $proj_dir = Util::getProjectDirectory();

        $phpunit_executable = "{$proj_dir}/docroot/vendor/bin/phpunit";

        $file = "{$proj_dir}/docroot/core/lib/Drupal/Component/PhpStorage/FileStorage.php";

        $this->taskExec("sed -i.bak 's/trigger_error/\/\/trigger_error/' {$file}")
            ->run();

        $this->taskExec("{$phpunit_executable} --testsuite=\"DKAN Test Suite\"")
            ->dir("{$proj_dir}/docroot/profiles/contrib/dkan2")
            ->run();

        $this->taskExec("sed -i.bak 's/\/\/trigger_error/trigger_error/' {$file}")
            ->run();
    }
}
