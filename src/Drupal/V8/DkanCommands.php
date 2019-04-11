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
     * 
     * Because of a limitation of using the single `$args` array to pass all
     * additional arguments, the phpunit specific arguments can be only be
     * passed without the `--` prefix to avoid conflicting with Robo\Tasks
     * built in arguments.
     * 
     * e.g. dtlk dkan:test-phpunit debug verbose coverage-html=../logs/coverage
     *
     * Note that only full option names will work and not single letter arguments
     * i.e. use `verbose` instead of `v`
     * 
     * @todo currently `--testsuite` is a hardoced argument. Could refactor if additional test suites are added
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
        
        // currently dktl only passes args as an array to the commands.
        // some sanitisation is needed.
        foreach ($args as $arg) {
            
            if(strpos($arg,'=')) {
                list($option, $value) = explode('=', $arg, 2);
                $phpunitExec->option($option, $value);
            }
            else
            {
                $phpunitExec->option($arg);
            }
        }
        
        $phpunitExec->run();
        
        $this->taskExec("sed -i.bak 's/\/\/trigger_error/trigger_error/' {$file}")
            ->run();
    }
}
