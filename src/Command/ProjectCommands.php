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
            ->dir("src/frontend")
            ->run();
        if ($result && $result->getExitCode() === 0) {
            $this->io()->success(
                'Successfully symlinked global cypress into frontend folder.'
            );
        } else {
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

        $cypress = $this->taskExec('CYPRESS_baseUrl="http://$DKTL_PROXY_DOMAIN" npx cypress run')
            ->dir("src/tests");

        $cypress->run();
        $this->deleteTestUsers();
    }

    /**
     * Run Site PhpUnit Tests. Additional phpunit CLI options can be passed.
     *
     * @param array $args
     *   Arguments to append to phpunit command.
     *
     * @see https://phpunit.de/manual/6.5/en/textui.html#textui.clioptions
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
     * Determine path to PHPUnit executable.
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
     * Ensure current git branch is not in a detached state.
     *
     * @return bool
     *   Flag for whether the current branch branch is detached.
     */
    private function inGitDetachedState($dkanDirPath)
    {
        $output = [];
        exec("cd {$dkanDirPath} && git rev-parse --abbrev-ref HEAD", $output);
        return (isset($output[0]) && $output[0] == 'HEAD');
    }
}
