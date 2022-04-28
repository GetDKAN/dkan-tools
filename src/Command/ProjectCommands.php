<?php

namespace DkanTools\Command;

use Robo\Tasks;
use DkanTools\Util\TestUserTrait;

/**
 * This project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class ProjectCommands extends Tasks
{
    use TestUserTrait;

    /**
     * Run project cypress tests.
     */
    public function projectTestCypress(array $args)
    {

        $this->createTestUsers();

        $result = $this->taskExec("npm link ../../../../usr/local/bin/node_modules/cypress")
            ->dir("src/tests")
            ->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not symlink package folder');
            return $result;
        }

        $task = $this
            ->taskExec('npm install --force')
            ->dir("src/tests");
        $result = $task->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not insall test dependencies.');
            return $result;
        }
        $this->io()->success('Installation of test dependencies successful.');
        $config = file_exists("src/tests/cypress.json") ? '--config-file src/tests/cypress.json' : '';
        $cypress = $this->taskExec('CYPRESS_baseUrl="http://$DKTL_PROXY_DOMAIN" npx cypress run' . $config)
            ->dir("src/tests");

        foreach ($args as $arg) {
            $cypress->arg($arg);
        }

        $cypress->run();
        return $this->deleteTestUsers();
    }

    /**
     * Run Site PhpUnit Tests. Additional phpunit CLI options can be passed.
     *
     * @see   https://phpunit.de/manual/6.5/en/textui.html#textui.clioptions
     * @param array $args
     *   Arguments to append to phpunit command.
     */
    public function projectTestPhpunit(array $args)
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

    /**
     *
     */
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

    /**
     *
     */
    private function inGitDetachedState($dkanDirPath)
    {
        $output = [];
        exec("cd {$dkanDirPath} && git rev-parse --abbrev-ref HEAD", $output);
        return (isset($output[0]) && $output[0] == 'HEAD');
    }
}
