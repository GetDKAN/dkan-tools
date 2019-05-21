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
        $result = $this->taskExec("CYPRESS_baseUrl=http://web {$proj_dir}/node_modules/cypress/bin/cypress run")->run();
        if ($result->getExitCode() != 0) {
            throw new \Exception("Cypress tests failed.");
        }
    }
}
