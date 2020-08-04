<?php

namespace DkanTools\Command;

use DkanTools\Util\Util;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 * @param $arg use 'frontend' to run frontend cypress tests.
 */
class DkanCommands extends \Robo\Tasks
{
    /**
     * Run DKAN Cypress Tests.
     */
    public function dkanTestCypress($arg = null)
    {
        $proj_dir = Util::getProjectDirectory();

        if ($arg === 'frontend') {
            $this->taskExec("npm install cypress")
            ->dir("{$proj_dir}/docroot/frontend")
            ->run();

            return $this->taskExec('CYPRESS_baseUrl="http://$DKTL_PROXY_DOMAIN" npx cypress run')
            ->dir("{$proj_dir}/docroot/frontend")
            ->run();
        }

        $this->taskExec("npm install cypress")
        ->dir("{$proj_dir}/docroot/modules/contrib/dkan")
        ->run();

        return $this->taskExec('CYPRESS_baseUrl="http://$DKTL_PROXY_DOMAIN" npx cypress run')
            ->dir("{$proj_dir}/docroot/modules/contrib/dkan")
            ->run();
    }

    /**
     * Run DKAN Dredd Tests.
     */
    public function dkanTestDredd()
    {
        $proj_dir = Util::getProjectDirectory();
        $this->taskExec("npm install dredd")
            ->dir("{$proj_dir}/docroot/modules/contrib/dkan")
            ->run();

        return $this->taskExec("npx dredd --hookfiles=./dredd-hooks.js")
            ->dir("{$proj_dir}/docroot/modules/contrib/dkan/dredd")
            ->run();
    }

    /**
     * Run DKAN PhpUnit Tests. Additional phpunit CLI options can be passed.
     *
     * @see https://phpunit.de/manual/6.5/en/textui.html#textui.clioptions
     * @param array $args Arguments to append to phpunit command.
     */
    public function dkanTestPhpunit(array $args)
    {

        $proj_dir = Util::getProjectDirectory();

        $this->taskExec("dktl installphpunit")->run();

        $phpunit_executable = "phpunit";

        $phpunitExec = $this->taskExec($phpunit_executable)
            ->option('testsuite', 'DKAN Test Suite')
            ->dir("{$proj_dir}/docroot/modules/contrib/dkan");

        foreach ($args as $arg) {
            $phpunitExec->arg($arg);
        }

        return $phpunitExec->run();
    }

    /**
     * Run DKAN PhpUnit Tests and send a coverage report to CodeClimate.
     */
    public function dkanTestPhpunitCoverage($code_climate_reporter_id)
    {
        $this->taskExec("dktl installphpunit")->run();

        $proj_dir = Util::getProjectDirectory();
        $dkanDir = "{$proj_dir}/docroot/modules/contrib/dkan";

        // Due to particularities of Composer, when we asked for the 2.x branch, we get a detached HEAD state.
        // Code Climate's test reporter gets information from git. If we recognized a detached HEAD state, lets
        // checkout our master branch: 2.x.
        if ($this->inGitDetachedState($dkanDir)) {
            exec("cd {$dkanDir} && git checkout 2.x");
        }

        $this->installCodeClimateTestReporter($dkanDir);

        $phpunit_executable = "phpunit";

        $this->taskExec("./cc-test-reporter before-build")->dir($dkanDir)->run();

        $phpunitExec = $this->taskExec($phpunit_executable)
            ->option('testsuite', 'DKAN Test Suite')
            ->option('coverage-clover', 'clover.xml')
            ->dir($dkanDir);

        $result = $phpunitExec->run();

        $this->taskExec(
            "./cc-test-reporter after-build -r {$code_climate_reporter_id} --coverage-input-type clover --exit-code $?"
        )
            ->dir($dkanDir)
            ->silent(true)
            ->run();
        return $result;
    }

    private function installCodeClimateTestReporter($dkanDir)
    {
        if (!file_exists("{$dkanDir}/cc-test-reporter")) {
            $this->taskExec(
                "curl -L https://codeclimate.com/downloads/test-reporter/test-reporter-latest-linux-amd64 > "
                . "./cc-test-reporter"
            )
                ->dir($dkanDir)->run();
            $this->taskExec("chmod +x ./cc-test-reporter")->dir($dkanDir)->run();
        }
    }

    private function inGitDetachedState($dkanDirPath)
    {
        $output = [];
        exec("cd {$dkanDirPath} && git rev-parse --abbrev-ref HEAD", $output);
        return (isset($output[0]) && $output[0] == 'HEAD');
    }
}
