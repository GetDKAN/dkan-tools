<?php

namespace DkanTools\Command;

use Robo\Result;
use DkanTools\Util\Util;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class BasicCommands extends \Robo\Tasks
{
    /**
     * Test some things.
     */
    public function test(array $cmd)
    {
        $cmdStr = implode(' ', $cmd);
        $this->say($cmdStr);
    }

    /**
     * Run drush command on current site.
     *
     * Run drush command on current site. For instance, to clear caches, run
     * "dktl drush cc all". 
     *
     * @param array $cmd Array of arguments to create a full Drush command.
     */
    public function drush(array $cmd)
    {
        $drupal_root = Util::getProjectDocroot();
        $drushExec = $this->taskExec('drush')->dir($drupal_root);
        foreach ($cmd as $arg) {
            $drushExec->arg($arg);
        }
        return $drushExec->run();
    }

    /**
     * Proxy to composer.
     */
    public function composer(array $cmd)
    {
        $exec = $this->taskExec('composer');
        foreach ($cmd as $arg) {
            $exec->arg($arg);
        }
        return $exec->run();
    }

    /**
     * Run "drush uli" command with correct ULI argument.
     *
     * Like the "docker" group of commands, this command is actually run in
     * inside the dktl.sh script makes it to the DKAN Tools php application. It
     * simply runs the real "dktl drush" command and passes it the result of
     * "dktl surl" as the --uri argument.
     *
     * @todo Make it configurable whether this uses http or https.
     */
    public function drushUli()
    {
        throw new \Exception('Something went wrong; this command should be run through dktl.sh');
    }
}
