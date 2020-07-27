<?php

namespace DkanTools\Command;

use DkanTools\Util\Util;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class TestCommands extends \Robo\Tasks
{
    public function testCypress()
    {
        $proj_dir = Util::getProjectDirectory();
        $this->_exec("npm install cypress");
        $result = $this->taskExec("{$proj_dir}/node_modules/cypress/bin/cypress run")->run();
        if ($result->getExitCode() != 0) {
            throw new \Exception("Cypress tests failed.");
        }
    }

    /**
     * Run dkan-tools' own suite of unit tests.
     */
    public function testDktl()
    {
        $dktlDir = Util::getDktlDirectory();
        $result = $this->taskExec("{$dktlDir}/vendor/bin/phpunit")
            ->dir($dktlDir)
            ->run();
        if ($result->getExitCode() != 0) {
            throw new \Exception("DKTL unit tests failed.");
        }
    }
}
