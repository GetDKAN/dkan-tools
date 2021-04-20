<?php

namespace DkanTools\Command;

use DkanTools\Util\Util;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class CustomCommands extends \Robo\Tasks
{
    /**
     * Run Custom Cypress Tests.
     */
    public function customTestCypress(array $args)
    {
        $this->dkanTestUser("testuser", "2jqzOAnXS9mmcLasy", "api_user");
        $this->dkanTestUser("testeditor", "testeditor", "administrator");

        $this->taskExec("npm install cypress")
            ->dir("docroot/modules/custom")
            ->run();

        $cypress = $this->taskExec('CYPRESS_baseUrl="http://$DKTL_PROXY_DOMAIN" npx cypress run')
            ->dir("docroot/modules/custom");

        foreach ($args as $arg) {
          $cypress->arg($arg);
        }

        return $cypress->run();
    }

    /**
     * Run Custom PhpUnit Tests. Additional phpunit CLI options can be passed.
     *
     * @see https://phpunit.de/manual/6.5/en/textui.html#textui.clioptions
     * @param array $args Arguments to append to phpunit command.
     */
    public function customTestPhpunit(array $args)
    {
        $proj_dir = Util::getProjectDirectory();
        $phpunit_executable = $this->getPhpUnitExecutable();

        $phpunitExec = $this->taskExec($phpunit_executable)
            ->option('testsuite', 'Custom Test Suite')
            ->dir("{$proj_dir}/docroot/modules/custom");

        foreach ($args as $arg) {
            $phpunitExec->arg($arg);
        }

        return $phpunitExec->run();
    }

    private function getPhpUnitExecutable()
    {
        $proj_dir = Util::getProjectDirectory();

        $phpunit_executable = $phpunit_executable = "{$proj_dir}/vendor/bin/phpunit";

        if (!file_exists($phpunit_executable)) {
            $this->taskExec("dktl installphpunit")->run();
            $phpunit_executable = "phpunit";
        }

        return $phpunit_executable;
    }

}
