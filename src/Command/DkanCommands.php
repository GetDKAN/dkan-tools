<?php

namespace DkanTools\Command;

use DkanTools\Util\Util;
use DkanTools\Util\TestUserTrait;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 * @param $arg use 'frontend' to run frontend cypress tests.
 */
class DkanCommands extends \Robo\Tasks
{
    use TestUserTrait;

    /**
     * Build DKAN docs with doxygen.
     */
    public function dkanDocs()
    {
        $proj_dir = Util::getProjectDirectory();
        $this->taskExec("doxygen")
            ->dir("{$proj_dir}/docroot/modules/contrib/dkan")
            ->run();
        $url = Util::getUri();
        $this->io()->text("Docs site: $url/modules/contrib/dkan/docs/index.html");
    }

    /**
     * Run DKAN Cypress Tests.
     */
    public function dkanTestCypress(array $args)
    {
        $this->createTestUsers();

        $this->taskExec("npm cache verify && cypress install")
        //$this->taskExec("npm cache clean --force && npm cache verify && npm install")
          ->dir("docroot/modules/contrib/dkan")
          ->run();

        $cypress = $this->taskExec('CYPRESS_baseUrl="http://$DKTL_PROXY_DOMAIN" npx cypress run')
            ->dir("docroot/modules/contrib/dkan");

        foreach ($args as $arg) {
          $cypress->arg($arg);
        }

        $cypress->run();
        return $this->deleteTestUsers();
    }

    /**
     * Run DKAN Dredd Tests.
     */
    public function dkanTestDredd()
    {
        $this->createTestUsers();
        $this->taskExec("npm install dredd")
            ->dir("docroot/modules/contrib/dkan")
            ->run();

        $this->taskExec("npx dredd --hookfiles=./dredd-hooks.js")
            ->dir("docroot/modules/contrib/dkan/dredd")
            ->run();
        return $this->deleteTestUsers();
    }

    /**
     * Run DKAN PhpUnit Tests. Additional phpunit CLI options can be passed.
     *
     * @see https://phpunit.de/manual/6.5/en/textui.html#textui.clioptions
     * @param array $args Arguments to append to phpunit command.
     */
    public function dkanTestPhpunit(array $args)
    {
        $this->createTestUsers();
        $proj_dir = Util::getProjectDirectory();
        $phpunit_executable = $this->getPhpUnitExecutable();

        $phpunitExec = $this->taskExec($phpunit_executable)
            ->option('testsuite', 'DKAN Test Suite')
            ->dir("{$proj_dir}/docroot/modules/contrib/dkan");

        foreach ($args as $arg) {
            $phpunitExec->arg($arg);
        }

        $phpunitExec->run();
        return $this->deleteTestUsers();
    }

    /**
     * Run DKAN PhpUnit Tests and send a coverage report to CodeClimate.
     */
    public function dkanTestPhpunitCoverage($code_climate_reporter_id)
    {
        $this->createTestUsers();
        $proj_dir = Util::getProjectDirectory();
        $dkanDir = "{$proj_dir}/docroot/modules/contrib/dkan";

        // Due to particularities of Composer, when we asked for the 2.x branch, we get a detached HEAD state.
        // Code Climate's test reporter gets information from git. If we recognized a detached HEAD state, lets
        // checkout our master branch: 2.x.
        if ($this->inGitDetachedState($dkanDir)) {
            exec("cd {$dkanDir} && git checkout 2.x");
        }

        $this->installCodeClimateTestReporter($dkanDir);

        $phpunit_executable = $this->getPhpUnitExecutable();

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
        $this->deleteTestUsers();
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

    private function inGitDetachedState($dkanDirPath)
    {
        $output = [];
        exec("cd {$dkanDirPath} && git rev-parse --abbrev-ref HEAD", $output);
        return (isset($output[0]) && $output[0] == 'HEAD');
    }


    /**
     * Create a new demo project.
     *
     * Will have frontend and sample content. Run this immediately after dktl
     * init.
     *
     * @aliases demo
     */
    public function dkanDemo()
    {
        $this->taskExecStack()
            ->stopOnFail()
            ->exec("dktl make")
            ->exec("dktl install")
            ->exec("dktl install:sample")
            ->exec("git clone -b "
                . FrontendCommands::FRONTEND_VCS_REF
                . " "
                . FrontendCommands::FRONTEND_VCS_URL
                . " " . FrontendCommands::FRONTEND_DIR)
            ->exec("dktl frontend:install")
            ->exec("dktl frontend:build")
            ->exec("dktl drush cr")
            ->run();

        $this->io()->success("Your demo site is available at: " . Util::getUri());
    }

    /**
     * Create a new dev project.
     *
     * Will have frontend and sample content. Run this immediately after dktl
     * init.
     *
     * @aliases dev
     */
    public function dkanDev()
    {
        $this->taskExecStack()
            ->stopOnFail()
            ->exec("dktl make --prefer-source")
            ->exec("dktl install")
            ->exec("dktl install:sample")
            ->exec("git clone -b "
                . FrontendCommands::FRONTEND_VCS_REF
                . " "
                . FrontendCommands::FRONTEND_VCS_URL
                . " " . FrontendCommands::FRONTEND_DIR)
            ->exec("dktl frontend:install")
            ->exec("dktl frontend:build")
            ->exec("dktl drush user:password admin admin")
            ->exec("dktl drush cr")
            ->run();

        $this->io()->success("Your dev site is available at: " . Util::getUri());
    }
}
