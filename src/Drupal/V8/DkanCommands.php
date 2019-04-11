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
     * Run DKAN PhpUnit Tests. Additional pgpunit CLI options can be passed.
     * 
     * @see https://phpunit.de/manual/6.5/en/textui.html#textui.clioptions 
     * @param array $args  Arguments to append to phpunit command.
     */
    public function dkanTestPhpunit(array $args) {

        $proj_dir = Util::getProjectDirectory();

        $phpunit_executable = "{$proj_dir}/docroot/vendor/bin/phpunit";

        $file = "{$proj_dir}/docroot/core/lib/Drupal/Component/PhpStorage/FileStorage.php";

        $this->taskExec("sed -i.bak 's/trigger_error/\/\/trigger_error/' {$file}")
            ->run();

        $phpunitExec = $this->taskExec($phpunit_executable)
            ->option('testsuite', 'DKAN Test Suite')
            ->dir("{$proj_dir}/docroot/profiles/contrib/dkan2");
        
        foreach ($args as $arg) {
            $phpunitExec->arg($arg);
        }

        $phpunitExec->run();
        
        $this->taskExec("sed -i.bak 's/\/\/trigger_error/trigger_error/' {$file}")
            ->run();
    }
}
