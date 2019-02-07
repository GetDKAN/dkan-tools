<?php

namespace DkanTools\Command;

use DkanTools\Util\Util;
use Symfony\Component\Console\Input\InputOption;

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
        $this->_exec("CYPRESS_baseUrl=http://web {$proj_dir}/node_modules/cypress/bin/cypress run");
    }
}
