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
    public function dkanTestCypress()
    {
        $proj_dir = Util::getProjectDirectory();
        $this->taskExec("npm install cypress")
            ->dir("{$proj_dir}/docroot/profiles/contrib/dkan2")
            ->run();

        return $this->taskExec("CYPRESS_baseUrl=http://web npx cypress run")
            ->dir("{$proj_dir}/docroot/profiles/contrib/dkan2")
            ->run();
    }

    /**
     * Run DKAN Dredd Tests.
     */
    public function dkanTestDredd()
    {
        $proj_dir = Util::getProjectDirectory();
        $this->taskExec("npm install dredd")
            ->dir("{$proj_dir}/docroot/profiles/contrib/dkan2")
            ->run();

        return $this->taskExec("npx dredd --hookfiles=./dredd-hooks.js")
            ->dir("{$proj_dir}/docroot/profiles/contrib/dkan2/dredd")
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
            ->dir("{$proj_dir}/docroot/profiles/contrib/dkan2");

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

        putenv("CC_TEST_REPORTER_ID={$code_climate_reporter_id}");

        $proj_dir = Util::getProjectDirectory();
        $dkan_dir = "{$proj_dir}/docroot/profiles/contrib/dkan2";

        if (!file_exists("{$dkan_dir}/cc-test-reporter")) {
            $this->taskExec("curl -L https://codeclimate.com/downloads/test-reporter/test-reporter-latest-linux-amd64 > ./cc-test-reporter")
                ->dir($dkan_dir)->run();
            $this->taskExec("chmod +x ./cc-test-reporter")->dir($dkan_dir)->run();
        }

        $phpunit_executable = "phpunit";

        $this->taskExec("./cc-test-reporter before-build")->dir($dkan_dir)->run();

        $phpunitExec = $this->taskExec($phpunit_executable)
            ->option('testsuite', 'DKAN Test Suite')
            ->option('coverage-clover', 'clover.xml')
            ->dir($dkan_dir);

        $result = $phpunitExec->run();

        $this->taskExec("./cc-test-reporter after-build --coverage-input-type clover --exit-code $?")->dir($dkan_dir)->run();
        return $result;
    }
}
